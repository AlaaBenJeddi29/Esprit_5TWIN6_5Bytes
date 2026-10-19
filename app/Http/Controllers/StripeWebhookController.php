<?php

namespace App\Http\Controllers;

use App\Models\Project\Funding;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\Webhook;

/**
 * Webhook Stripe Checkout — seule source de vérité pour confirmer un don.
 *
 * Le retour navigateur (success_url) ne marque jamais un don comme payé : ici
 * la signature Stripe est vérifiée, puis le statut de paiement, la session,
 * le montant, la devise, le projet et le don sont contrôlés avant toute mise
 * à jour. Le traitement est idempotent (aucune ligne créée, mise à jour
 * conditionnelle) : un événement rejoué ne peut pas dupliquer un financement.
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $secret = (string) config('services.stripe.webhook_secret');

        Log::info('Webhook Stripe : requête reçue.', [
            'signature_present' => $request->hasHeader('Stripe-Signature'),
            'payload_bytes' => strlen($request->getContent()),
        ]);

        if ($secret === '') {
            Log::error('Webhook Stripe : STRIPE_WEBHOOK_SECRET absent — événement refusé.');

            return response('', 500);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature', ''),
                $secret,
            );
        } catch (SignatureVerificationException|UnexpectedValueException $e) {
            // Message du SDK uniquement : il ne contient ni secret ni donnée carte.
            Log::warning('Webhook Stripe : signature invalide — '.$e->getMessage());

            return response('', 400);
        }

        Log::info('Webhook Stripe : signature validée.', [
            'event_id' => (string) ($event->id ?? ''),
            'type' => (string) ($event->type ?? ''),
        ]);

        if ($event->type !== 'checkout.session.completed') {
            return response('', 200);
        }

        $session = $event->data->object;

        // Un Checkout « completed » n'est pas forcément payé (paiement différé,
        // moyen de paiement asynchrone) : seul payment_status=paid confirme.
        if ((string) ($session->payment_status ?? '') !== 'paid') {
            Log::info('Webhook Stripe : paiement non confirmé — événement ignoré.', [
                'event_id' => (string) ($event->id ?? ''),
                'payment_status' => (string) ($session->payment_status ?? ''),
            ]);

            return response('', 200);
        }

        $donationId = (int) ($session->metadata->donation_id ?? 0);
        $projectId = (int) ($session->metadata->project_id ?? 0);
        $sessionId = (string) ($session->id ?? '');

        if ($donationId <= 0 || $projectId <= 0) {
            Log::info('Webhook Stripe : événement sans métadonnées de don — ignoré.', [
                'event_id' => (string) ($event->id ?? ''),
            ]);

            return response('', 200);
        }

        $donation = Funding::query()
            ->whereKey($donationId)
            ->where('source', 'don')
            ->first();

        if ($donation === null) {
            // Métadonnées propres à l'application mais don absent : anomalie
            // possiblement transitoire. Une réponse non-2xx demande à Stripe
            // de réessayer au lieu de perdre définitivement l'événement.
            Log::error('Webhook Stripe : don introuvable — nouvelle tentative demandée à Stripe.', [
                'event_id' => (string) ($event->id ?? ''),
                'donation_id' => $donationId,
                'project_id' => $projectId,
            ]);

            return response('', 500);
        }

        if ($sessionId === ''
            || ! hash_equals((string) $donation->stripe_session_id, $sessionId)
            || (int) $donation->project_id !== $projectId) {
            Log::warning('Webhook Stripe : session inconnue ou ne correspondant à aucun don.', [
                'event_id' => (string) ($event->id ?? ''),
                'donation_id' => $donation->id,
                'project_id' => $projectId,
            ]);

            return response('', 200);
        }

        $centimesAttendus = (int) round((float) $donation->montant * 100);
        $deviseAttendue = strtolower((string) config('donations.currency'));

        if ((int) ($session->amount_total ?? 0) !== $centimesAttendus
            || strtolower((string) ($session->currency ?? '')) !== $deviseAttendue) {
            Log::warning('Webhook Stripe : montant ou devise différents du don '.$donation->id.' — ignoré.', [
                'event_id' => (string) ($event->id ?? ''),
                'attendu' => $centimesAttendus.' '.$deviseAttendue,
                'recu' => (int) ($session->amount_total ?? 0).' '.strtolower((string) ($session->currency ?? '')),
            ]);

            return response('', 200);
        }

        // Idempotence : la clause conditionnelle empêche tout double traitement
        // et garantit qu'un événement rejoué ne modifie plus rien.
        $updated = Funding::query()
            ->whereKey($donation->id)
            ->where('stripe_session_id', $sessionId)
            ->where('statut_paiement', '!=', Funding::PAIEMENT_PAYE)
            ->update([
                'statut_paiement' => Funding::PAIEMENT_PAYE,
                'date_versement' => now()->toDateString(),
            ]);

        if ($updated === 0) {
            Log::info('Webhook Stripe : don déjà confirmé — livraison répétée ignorée.', [
                'event_id' => (string) ($event->id ?? ''),
                'donation_id' => $donation->id,
            ]);
        } else {
            Log::info('Webhook Stripe : don confirmé.', [
                'event_id' => (string) ($event->id ?? ''),
                'donation_id' => $donation->id,
                'montant_centimes' => $centimesAttendus,
                'devise' => $deviseAttendue,
            ]);
        }

        return response('', 200);
    }
}
