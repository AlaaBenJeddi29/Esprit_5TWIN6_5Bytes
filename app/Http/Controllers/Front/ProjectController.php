<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Concerns\ResolvesListingFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectAssistantRequest;
use App\Http\Requests\StoreDonationRequest;
use App\Models\Infrastructure\Zone;
use App\Models\Project\Funding;
use App\Models\Project\Project;
use App\Models\Project\ProjectDocument;
use App\Services\ProjectAssistant;
use App\Services\ProjectMetrics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProjectController extends Controller
{
    use ResolvesListingFilters;

    public function __construct(private readonly ProjectAssistant $assistant) {}

    public function index(Request $request): View
    {
        $zoneId = $request->integer('zone_id');
        $statut = $request->string('statut')->toString();
        $search = $request->string('search')->toString();
        $perPage = $this->listingPerPage($request);

        $projects = Project::query()
            ->with(['zone', 'responsable'])
            ->when($search !== '', fn ($query) => $query->where('titre', 'like', "%{$search}%"))
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId))
            ->when(in_array($statut, Project::STATUTS, true), fn ($query) => $query->where('statut', $statut))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return view('front.projects.index', [
            'projects' => $projects,
            'zones' => Zone::orderBy('nom')->get(),
            'statuts' => Project::STATUTS,
            'search' => $search,
            'currentZone' => $zoneId ?: null,
            'currentStatut' => $statut,
            'perPage' => $perPage,
        ]);
    }

    public function show(Project $project): View
    {
        $project->load([
            'zone',
            'infrastructure',
            'responsable',
            'projectPhases.contractor',
            'fundings.donateur',
            'publicDocuments',
        ]);

        $donsConfirmes = $project->fundings
            ->filter(fn (Funding $funding) => $funding->isDonation() && $funding->estConfirmee());

        $donationsCount = $donsConfirmes->count();
        $donationsTotal = (float) $donsConfirmes->sum('montant');

        return view('front.projects.show', [
            'project' => $project,
            'metrics' => new ProjectMetrics($project),
            'donationsCount' => $donationsCount,
            'donationsTotal' => $donationsTotal,
            'fundingsConfirmees' => $project->fundings->filter(fn (Funding $funding) => $funding->estConfirmee()),
            'assistantAnswer' => session('assistant_answer'),
            'assistantQuestion' => session('assistant_question'),
            'assistantSuggestions' => $this->assistant->suggestions(),
        ]);
    }

    /**
     * Don citoyen : enregistre une intention de don en attente puis redirige
     * vers Stripe Checkout. Aucun montant, identifiant ou statut fourni par le
     * navigateur n'est repris tel quel au-delà de la validation serveur.
     */
    public function donate(StoreDonationRequest $request, Project $project): RedirectResponse
    {
        abort_if($project->statut === 'annulé', 404);

        $devise = (string) config('donations.currency');

        if (! in_array($devise, (array) config('donations.supported_currencies'), true)) {
            Log::error('Dons Stripe : devise non supportée configurée ('.$devise.').');

            return redirect()->route('public.projects.show', $project)
                ->with('error', 'Les dons en ligne ne sont pas disponibles pour le moment.');
        }

        $secret = (string) config('services.stripe.secret');

        // Mode test uniquement : une clé live est refusée, jamais utilisée.
        if ($secret === '' || str_starts_with($secret, 'sk_live_')) {
            Log::error('Dons Stripe indisponibles : STRIPE_SECRET absent ou clé live refusée.');

            return redirect()->route('public.projects.show', $project)
                ->with('error', 'Le paiement en ligne n\'est pas configuré.');
        }

        $montant = round((float) $request->validated('montant'), 2);
        $centimes = (int) round($montant * 100);

        if ($centimes <= 0) {
            return redirect()->route('public.projects.show', $project)
                ->withErrors(['montant' => 'Le montant du don doit être supérieur à zéro.']);
        }

        $donation = $project->fundings()->create([
            'source' => 'don',
            'donateur_id' => $request->user()->id,
            'montant' => $montant,
            'date_versement' => now()->toDateString(),
            'statut_paiement' => Funding::PAIEMENT_EN_ATTENTE,
        ]);

        try {
            $session = StripeCheckoutSession::create([
                'mode' => 'payment',
                'client_reference_id' => (string) $donation->id,
                'customer_email' => $request->user()->email,
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($devise),
                        'unit_amount' => $centimes,
                        'product_data' => ['name' => mb_substr('Don – '.$project->titre, 0, 250)],
                    ],
                ]],
                'metadata' => [
                    'donation_id' => (string) $donation->id,
                    'project_id' => (string) $project->id,
                ],
                'success_url' => route('citizen.projects.donation.success', $project).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('citizen.projects.donation.cancel', $project).'?session_id={CHECKOUT_SESSION_ID}',
            ], ['api_key' => $secret]);
        } catch (Throwable $e) {
            // Message serveur uniquement : aucune donnée Stripe sensible n'est renvoyée au navigateur.
            Log::warning('Dons Stripe : création de session impossible (don '.$donation->id.') — '.$e->getMessage());

            $donation->update(['statut_paiement' => Funding::PAIEMENT_ECHOUE]);

            return redirect()
                ->route('public.projects.show', $project)
                ->with('error', 'Le paiement n\'a pas pu être initié. Vous n\'avez pas été débité.');
        }

        // Référence technique interne : ni une modification métier, ni une
        // donnée à journaliser dans l'audit.
        $donation->updateQuietly(['stripe_session_id' => $session->id]);

        return redirect()->away($session->url);
    }

    /**
     * Retour Stripe après paiement. Le webhook reste la source primaire ; si le
     * citoyen revient avant sa livraison, la session est revérifiée auprès de
     * Stripe côté serveur. L'identifiant d'URL seul ne prouve jamais le paiement.
     */
    public function donationSuccess(Request $request, Project $project): View
    {
        $donation = $this->donationDuCitoyen($request, $project, $request->query('session_id'));

        if ($donation->statut_paiement !== Funding::PAIEMENT_PAYE) {
            $this->confirmDonationFromStripeSession($donation);
            $donation->refresh();
        }

        return view('front.projects.donation-success', [
            'project' => $project,
            'donation' => $donation,
        ]);
    }

    /** Retour Stripe après abandon : le don reste impayé et peut être retenté. */
    public function donationCancel(Request $request, Project $project): View
    {
        $donation = $this->donationDuCitoyen($request, $project, $request->query('session_id'));

        if ($donation->statut_paiement === Funding::PAIEMENT_EN_ATTENTE) {
            $donation->update(['statut_paiement' => Funding::PAIEMENT_ANNULE]);
        }

        return view('front.projects.donation-cancel', [
            'project' => $project,
            'donation' => $donation,
        ]);
    }

    /**
     * Un citoyen ne peut consulter que ses propres dons, sur le projet concerné
     * et rattachés à la session Stripe annoncée — sinon 404 (aucune fuite).
     */
    private function donationDuCitoyen(Request $request, Project $project, mixed $sessionId): Funding
    {
        abort_unless(
            is_string($sessionId) && $sessionId !== '' && strlen($sessionId) <= 255,
            404
        );

        return Funding::query()
            ->where('project_id', $project->id)
            ->where('donateur_id', $request->user()->id)
            ->where('source', 'don')
            ->where('stripe_session_id', $sessionId)
            ->firstOrFail();
    }

    /**
     * Revérifie la session Checkout auprès de Stripe et confirme le don si, et
     * seulement si, toutes les preuves de paiement correspondent au don local.
     */
    private function confirmDonationFromStripeSession(Funding $donation): void
    {
        $secret = (string) config('services.stripe.secret');
        $sessionId = (string) $donation->stripe_session_id;

        // Même garde que la création : aucune clé absente ou live n'est utilisée.
        if ($sessionId === '' || $secret === '' || str_starts_with($secret, 'sk_live_')) {
            return;
        }

        try {
            $session = StripeCheckoutSession::retrieve($sessionId, ['api_key' => $secret]);
        } catch (Throwable $e) {
            // Aucun détail Stripe n'est affiché au citoyen ; le type suffit au diagnostic.
            Log::warning('Dons Stripe : vérification du retour de succès impossible (don '.$donation->id.') — '.get_class($e));

            return;
        }

        $reason = $this->stripeSessionMismatchReason($donation, $session, $sessionId);

        if ($reason !== null) {
            Log::info('Dons Stripe : retour de succès non confirmé (don '.$donation->id.') — '.$reason);

            return;
        }

        // Même mise à jour conditionnelle que le webhook : un état déjà payé
        // n'est jamais réécrit et deux confirmations concurrentes sont sûres.
        $updated = Funding::query()
            ->whereKey($donation->id)
            ->where('stripe_session_id', $sessionId)
            ->where('statut_paiement', '!=', Funding::PAIEMENT_PAYE)
            ->update([
                'statut_paiement' => Funding::PAIEMENT_PAYE,
                'date_versement' => now()->toDateString(),
            ]);

        if ($updated > 0) {
            Log::info('Dons Stripe : don confirmé via le retour de succès.', ['donation_id' => $donation->id]);
        }
    }

    /** Contrôles stricts : la session récupérée doit correspondre au don local. */
    private function stripeSessionMismatchReason(Funding $donation, StripeCheckoutSession $session, string $sessionId): ?string
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

    public function downloadDocument(Project $project, ProjectDocument $document): StreamedResponse
    {
        abort_unless($document->project_id === $project->id, 404);
        abort_unless($document->est_public, 404);
        abort_unless(Storage::disk('local')->exists($document->chemin_fichier), 404);

        return Storage::disk('local')->download($document->chemin_fichier, $document->titre);
    }

    public function assistant(ProjectAssistantRequest $request, Project $project): RedirectResponse|JsonResponse
    {
        $answer = $this->assistant->answer($project, $request->validated('question'));

        if ($request->expectsJson()) {
            return response()->json([
                'question' => $request->validated('question'),
                'answer' => $answer,
                'disclaimer' => 'Réponse générée localement à partir des données du projet, sans fournisseur d\'IA externe.',
            ]);
        }

        return redirect()
            ->route('public.projects.show', $project)
            ->with('assistant_answer', $answer)
            ->with('assistant_question', $request->validated('question'));
    }
}
