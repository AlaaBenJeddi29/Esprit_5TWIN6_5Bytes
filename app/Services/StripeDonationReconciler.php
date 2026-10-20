<?php

namespace App\Services;

use App\Models\Project\Funding;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Throwable;

/**
 * Revérifie auprès de Stripe les dons restés en attente côté AquaSecure.
 *
 * Le retour navigateur et le webhook restent les déclencheurs principaux, mais
 * aucun des deux n'est garanti (onglet fermé, webhook non relayé en local). Ce
 * service permet de rattraper un paiement réellement effectué sans dépendre
 * d'une commande d'arrière-plan : la session est récupérée avec la clé serveur
 * et tous les éléments (session, don, projet, montant, devise) doivent
 * correspondre avant toute confirmation.
 */
class StripeDonationReconciler
{
    private const RECONCILE_CACHE_SECONDS = 60;

    private const RECONCILE_LIMIT = 10;

    /**
     * Confirme le don si Stripe atteste du paiement. Retourne true si le don
     * est (ou devient) payé.
     */
    public function confirmIfPaid(Funding $donation): bool
    {
        if ($donation->statut_paiement === Funding::PAIEMENT_PAYE) {
            return true;
        }

        $secret = (string) config('services.stripe.secret');
        $sessionId = (string) $donation->stripe_session_id;

        // Même garde que la création : aucune clé absente ou live n'est utilisée.
        if ($sessionId === '' || $secret === '' || str_starts_with($secret, 'sk_live_')) {
            return false;
        }

        try {
            $session = StripeCheckoutSession::retrieve($sessionId, ['api_key' => $secret]);
        } catch (Throwable $e) {
            // Aucun détail Stripe n'est persisté ; le type suffit au diagnostic.
            Log::warning('Dons Stripe : vérification du paiement impossible (don '.$donation->id.') — '.get_class($e));

            return false;
        }

        $reason = $this->mismatchReason($donation, $session, $sessionId);

        if ($reason !== null) {
            Log::info('Dons Stripe : paiement non confirmé (don '.$donation->id.') — '.$reason);

            return false;
        }

        // Même mise à jour conditionnelle que le webhook et le retour de succès :
        // un état déjà payé n'est jamais réécrit, deux confirmations concurrentes
        // sont sûres et la date d'origine du don est conservée.
        $updated = Funding::query()
            ->whereKey($donation->id)
            ->where('stripe_session_id', $sessionId)
            ->where('statut_paiement', '!=', Funding::PAIEMENT_PAYE)
            ->update([
                'statut_paiement' => Funding::PAIEMENT_PAYE,
                'date_versement' => $donation->date_versement?->toDateString() ?? now()->toDateString(),
            ]);

        if ($updated > 0) {
            Log::info('Dons Stripe : don confirmé par revérification serveur.', ['donation_id' => $donation->id]);
        }

        return $updated > 0 || $donation->fresh()?->statut_paiement === Funding::PAIEMENT_PAYE;
    }

    /**
     * Rattrape les dons en attente du citoyen lors de l'ouverture de son
     * espace. Limité et temporisé pour ne pas marteler l'API Stripe.
     */
    public function reconcileForUser(User $user): void
    {
        if (! Cache::add('donations-reconcile:'.$user->id, true, self::RECONCILE_CACHE_SECONDS)) {
            return;
        }

        Funding::query()
            ->where('donateur_id', $user->id)
            ->where('source', 'don')
            ->where('statut_paiement', Funding::PAIEMENT_EN_ATTENTE)
            ->whereNotNull('stripe_session_id')
            ->latest('id')
            ->limit(self::RECONCILE_LIMIT)
            ->get()
            ->each(fn (Funding $donation) => $this->confirmIfPaid($donation));
    }

    /** Contrôles stricts : la session récupérée doit correspondre au don local. */
    private function mismatchReason(Funding $donation, StripeCheckoutSession $session, string $sessionId): ?string
    {
        if (! hash_equals($sessionId, (string) ($session->id ?? ''))) {
            return 'identifiant de session différent';
        }

        if ((string) ($session->payment_status ?? '') !== 'paid') {
            return 'paiement non confirmé';
        }

        if ((int) ($session->metadata->donation_id ?? 0) !== (int) $donation->id) {
            return 'donation_id incohérent';
        }

        if ((int) ($session->metadata->project_id ?? 0) !== (int) $donation->project_id) {
            return 'project_id incohérent';
        }

        if ((int) ($session->amount_total ?? 0) !== (int) round((float) $donation->montant * 100)) {
            return 'montant incohérent';
        }

        if (strtolower((string) ($session->currency ?? '')) !== strtolower((string) config('donations.currency'))) {
            return 'devise incohérente';
        }

        return null;
    }
}
