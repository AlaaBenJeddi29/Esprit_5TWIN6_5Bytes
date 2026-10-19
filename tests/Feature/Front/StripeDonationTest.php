<?php

namespace Tests\Feature\Front;

use App\Models\Project\Funding;
use App\Models\Project\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Stripe\ApiRequestor;
use Tests\Support\FakeStripeHttpClient;
use Tests\TestCase;

class StripeDonationTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.stripe.secret' => 'sk_test_fake',
            'services.stripe.webhook_secret' => self::WEBHOOK_SECRET,
            'donations.currency' => 'EUR',
            'donations.supported_currencies' => ['EUR'],
            'donations.min_amount' => 1,
            'donations.max_amount' => 5000,
        ]);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);

        parent::tearDown();
    }

    public function test_citizen_can_start_a_checkout_session(): void
    {
        $client = $this->fakeStripe();
        $citizen = User::factory()->citoyen()->create();
        $project = Project::factory()->create(['statut' => 'en_cours', 'titre' => 'Réservoir Nord']);

        $this->actingAs($citizen)
            ->post(route('citizen.projects.donate', $project), ['montant' => '250'])
            ->assertRedirect('https://checkout.stripe.test/pay/cs_test_123');

        $donation = Funding::sole();

        $this->assertSame('en_attente', $donation->statut_paiement);
        $this->assertSame('cs_test_123', $donation->stripe_session_id);
        $this->assertSame($citizen->id, $donation->donateur_id);
        $this->assertSame('don', $donation->source);

        $params = $client->requests[0]['params'];

        $this->assertSame('payment', $params['mode']);
        $this->assertSame(25000, $params['line_items'][0]['price_data']['unit_amount']);
        $this->assertSame('eur', $params['line_items'][0]['price_data']['currency']);
        $this->assertSame((string) $donation->id, $params['metadata']['donation_id']);
        $this->assertSame((string) $project->id, $params['metadata']['project_id']);
        $this->assertStringContainsString('session_id={CHECKOUT_SESSION_ID}', $params['success_url']);
        $this->assertStringContainsString('session_id={CHECKOUT_SESSION_ID}', $params['cancel_url']);
    }

    public function test_invalid_amounts_are_rejected_server_side(): void
    {
        $this->fakeStripe();
        $citizen = User::factory()->citoyen()->create();
        $project = Project::factory()->enCours()->create();

        foreach (['0', '-5', '5000.01', '12.345', 'abc'] as $montant) {
            $this->actingAs($citizen)
                ->from(route('public.projects.show', $project))
                ->post(route('citizen.projects.donate', $project), ['montant' => $montant])
                ->assertSessionHasErrors('montant');
        }

        $this->assertDatabaseCount('fundings', 0);
    }

    public function test_nonexistent_project_cannot_be_donated_to(): void
    {
        $this->fakeStripe();

        $this->actingAs(User::factory()->citoyen()->create())
            ->post('/projets/999999/don', ['montant' => 25])
            ->assertNotFound();
    }

    public function test_unauthenticated_and_non_citizen_users_cannot_donate(): void
    {
        $project = Project::factory()->enCours()->create();

        $this->post(route('citizen.projects.donate', $project), ['montant' => 25])
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->gestionnaire()->create())
            ->post(route('citizen.projects.donate', $project), ['montant' => 25])
            ->assertForbidden();

        $this->assertDatabaseCount('fundings', 0);
    }

    public function test_stripe_api_failure_marks_the_donation_failed_without_redirecting(): void
    {
        $this->fakeStripe('cURL error 28: timeout');
        $citizen = User::factory()->citoyen()->create();
        $project = Project::factory()->enCours()->create();

        $this->actingAs($citizen)
            ->post(route('citizen.projects.donate', $project), ['montant' => 25])
            ->assertRedirect(route('public.projects.show', $project))
            ->assertSessionHas('error');

        $donation = Funding::sole();

        $this->assertSame('echoue', $donation->statut_paiement);
        $this->assertNull($donation->stripe_session_id);
    }

    public function test_invalid_webhook_signature_is_rejected(): void
    {
        $donation = $this->pendingDonation();
        [$payload] = $this->webhookPayload($donation);

        $this->postWebhook($payload, 't='.time().',v1=deadbeef')
            ->assertStatus(400);

        $this->assertSame('en_attente', $donation->fresh()->statut_paiement);
    }

    public function test_paid_webhook_confirms_the_donation_and_is_idempotent(): void
    {
        $donation = $this->pendingDonation();
        [$payload, $signature] = $this->webhookPayload($donation);

        $this->postWebhook($payload, $signature)->assertOk();

        $this->assertSame('paye', $donation->fresh()->statut_paiement);
        $this->assertSame(250.0, $donation->project->fundingTotal());

        // Livraison répétée du même événement : aucun doublon, aucun double total.
        $this->postWebhook($payload, $signature)->assertOk();

        $this->assertSame(1, Funding::count());
        $this->assertSame('paye', $donation->fresh()->statut_paiement);
        $this->assertSame(250.0, $donation->project->fundingTotal());
    }

    public function test_paid_event_for_an_unknown_donation_requests_a_stripe_retry(): void
    {
        $donation = $this->pendingDonation();
        [$payload, $signature] = $this->webhookPayload($donation, [
            'metadata' => [
                'donation_id' => '999999',
                'project_id' => (string) $donation->project_id,
            ],
        ]);

        // Réponse non-2xx : Stripe réessaiera au lieu de perdre l'événement.
        $this->postWebhook($payload, $signature)->assertStatus(500);

        $this->assertSame('en_attente', $donation->fresh()->statut_paiement);
        $this->assertSame(0.0, $donation->project->fundingTotal());
    }

    public function test_event_without_aqua_secure_metadata_is_acknowledged_without_update(): void
    {
        $donation = $this->pendingDonation();
        [$payload, $signature] = $this->webhookPayload($donation, ['metadata' => (object) []]);

        $this->postWebhook($payload, $signature)->assertOk();

        $this->assertSame('en_attente', $donation->fresh()->statut_paiement);
    }

    public function test_success_page_confirms_a_paid_session_server_side(): void
    {
        $donation = $this->pendingDonation();
        $client = $this->fakeStripe(null, 'cs_test_123', $this->paidRetrievedSession($donation));

        $this->actingAs($donation->donateur)
            ->get($this->successUrl($donation))
            ->assertOk()
            ->assertSee('Votre don a été confirmé')
            ->assertViewHas('donation', fn (Funding $d) => $d->statut_paiement === Funding::PAIEMENT_PAYE);

        $this->assertSame('paye', $donation->fresh()->statut_paiement);
        $this->assertSame(250.0, $donation->project->fundingTotal());

        $this->assertSame('get', strtolower((string) $client->requests[0]['method']));
        $this->assertStringContainsString('/checkout/sessions/cs_test_123', $client->requests[0]['absUrl']);
    }

    public function test_success_page_keeps_an_unpaid_session_pending(): void
    {
        $donation = $this->pendingDonation();
        $this->fakeStripe(null, 'cs_test_123', $this->paidRetrievedSession($donation, ['payment_status' => 'unpaid']));

        $this->actingAs($donation->donateur)
            ->get($this->successUrl($donation))
            ->assertOk()
            ->assertSee('en cours de confirmation par Stripe');

        $this->assertSame('en_attente', $donation->fresh()->statut_paiement);
        $this->assertSame(0.0, $donation->project->fundingTotal());
    }

    public function test_success_page_never_confirms_a_mismatched_session(): void
    {
        $variants = [
            'session id' => fn (Funding $d) => ['id' => 'cs_test_other'],
            'donation id' => fn (Funding $d) => [
                'metadata' => ['donation_id' => '999999', 'project_id' => (string) $d->project_id],
            ],
            'project id' => fn (Funding $d) => [
                'metadata' => ['donation_id' => (string) $d->id, 'project_id' => '999999'],
            ],
            'amount' => fn (Funding $d) => ['amount_total' => 100],
            'currency' => fn (Funding $d) => ['currency' => 'usd'],
        ];

        foreach ($variants as $label => $overrides) {
            $sessionId = 'cs_test_'.md5($label);
            $donation = $this->pendingDonation($sessionId);
            $this->fakeStripe(null, $sessionId, $this->paidRetrievedSession($donation, $overrides($donation)));

            $this->actingAs($donation->donateur)
                ->get($this->successUrl($donation))
                ->assertOk();

            $this->assertSame('en_attente', $donation->fresh()->statut_paiement, $label);
            $this->assertSame(0.0, $donation->project->fundingTotal(), $label);
        }
    }

    public function test_success_page_rejects_an_unknown_session_id_before_calling_stripe(): void
    {
        $donation = $this->pendingDonation();
        $client = $this->fakeStripe();

        $this->actingAs($donation->donateur)
            ->get($this->successUrl($donation, 'cs_test_unknown'))
            ->assertNotFound();

        $this->assertSame('en_attente', $donation->fresh()->statut_paiement);
        $this->assertCount(0, $client->requests);
    }

    public function test_success_page_keeps_the_donation_pending_when_stripe_retrieval_fails(): void
    {
        $donation = $this->pendingDonation();
        $this->fakeStripe('cURL error 28: timeout');

        $this->actingAs($donation->donateur)
            ->get($this->successUrl($donation))
            ->assertOk()
            ->assertSee('en cours de confirmation par Stripe');

        $this->assertSame('en_attente', $donation->fresh()->statut_paiement);
        $this->assertSame(0.0, $donation->project->fundingTotal());
    }

    public function test_success_page_confirmation_is_idempotent(): void
    {
        $donation = $this->pendingDonation();
        $client = $this->fakeStripe(null, 'cs_test_123', $this->paidRetrievedSession($donation));

        $this->actingAs($donation->donateur)->get($this->successUrl($donation))->assertOk();
        $this->actingAs($donation->donateur)->get($this->successUrl($donation))->assertOk();

        $this->assertSame('paye', $donation->fresh()->statut_paiement);
        $this->assertSame(250.0, $donation->project->fundingTotal());
        $this->assertCount(1, $client->requests);
    }

    public function test_unpaid_session_is_not_confirmed(): void
    {
        $donation = $this->pendingDonation();
        [$payload, $signature] = $this->webhookPayload($donation, ['payment_status' => 'unpaid']);

        $this->postWebhook($payload, $signature)->assertOk();

        $this->assertSame('en_attente', $donation->fresh()->statut_paiement);
    }

    public function test_webhook_amount_mismatch_is_ignored(): void
    {
        $donation = $this->pendingDonation();
        [$payload, $signature] = $this->webhookPayload($donation, ['amount_total' => 100]);

        $this->postWebhook($payload, $signature)->assertOk();

        $this->assertSame('en_attente', $donation->fresh()->statut_paiement);
    }

    public function test_webhook_currency_mismatch_is_ignored(): void
    {
        $donation = $this->pendingDonation();
        [$payload, $signature] = $this->webhookPayload($donation, ['currency' => 'usd']);

        $this->postWebhook($payload, $signature)->assertOk();

        $this->assertSame('en_attente', $donation->fresh()->statut_paiement);
    }

    public function test_webhook_unknown_session_is_ignored(): void
    {
        $donation = $this->pendingDonation();
        [$payload, $signature] = $this->webhookPayload($donation, ['id' => 'cs_test_autre']);

        $this->postWebhook($payload, $signature)->assertOk();

        $this->assertSame('en_attente', $donation->fresh()->statut_paiement);
    }

    public function test_a_citizen_cannot_view_or_confirm_another_citizens_donation(): void
    {
        $donation = $this->pendingDonation();
        $other = User::factory()->citoyen()->create();
        $client = $this->fakeStripe(null, 'cs_test_123', $this->paidRetrievedSession($donation));

        $this->actingAs($other)
            ->get($this->successUrl($donation))
            ->assertNotFound();

        $this->assertSame('en_attente', $donation->fresh()->statut_paiement);
        $this->assertCount(0, $client->requests);
    }

    public function test_cancellation_keeps_the_donation_unpaid_and_allows_retry(): void
    {
        $this->fakeStripe(null, 'cs_test_1');
        $citizen = User::factory()->citoyen()->create();
        $project = Project::factory()->enCours()->create();

        $this->actingAs($citizen)->post(route('citizen.projects.donate', $project), ['montant' => 25]);
        $first = Funding::sole();

        $this->actingAs($citizen)
            ->get(route('citizen.projects.donation.cancel', $project).'?session_id=cs_test_1')
            ->assertOk();

        $this->assertSame('annule', $first->fresh()->statut_paiement);
        $this->assertSame(0.0, $project->fundingTotal());

        // Retenter crée une nouvelle intention de don, sans toucher à l'ancienne.
        $this->fakeStripe(null, 'cs_test_2');
        $this->actingAs($citizen)
            ->post(route('citizen.projects.donate', $project), ['montant' => 30])
            ->assertRedirect('https://checkout.stripe.test/pay/cs_test_2');

        $this->assertSame(2, Funding::count());
        $this->assertSame('annule', $first->fresh()->statut_paiement);
        $this->assertSame(0.0, $project->fundingTotal());
    }

    public function test_contribution_totals_include_confirmed_payments_only(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $project = Project::factory()->create(['budget_prevu' => 1000]);

        $this->createDonation($project, $citizen, 400, 'cs_paid', 'paye');
        $this->createDonation($project, $citizen, 100, 'cs_pending', 'en_attente');
        $this->createDonation($project, $citizen, 200, 'cs_cancelled', 'annule');
        $this->createDonation($project, $citizen, 300, 'cs_failed', 'echoue');

        // Financement historique sans statut de paiement : toujours compté.
        $project->fundings()->create([
            'source' => 'municipal',
            'montant' => 500,
            'date_versement' => now()->toDateString(),
        ]);

        $this->assertSame(900.0, $project->fundingTotal());

        $this->actingAs($citizen)
            ->get(route('citizen.dashboard'))
            ->assertOk()
            ->assertViewHas('donationsTotal', 400.0);
    }

    private function fakeStripe(
        ?string $connectionError = null,
        string $sessionId = 'cs_test_123',
        ?array $retrieveSession = null,
    ): FakeStripeHttpClient {
        $client = new FakeStripeHttpClient($connectionError, $sessionId, $retrieveSession);
        ApiRequestor::setHttpClient($client);

        return $client;
    }

    /**
     * Session renvoyée par Stripe lors de la revérification du retour de succès.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function paidRetrievedSession(Funding $donation, array $overrides = []): array
    {
        return array_merge([
            'id' => $donation->stripe_session_id,
            'object' => 'checkout.session',
            'payment_status' => 'paid',
            'amount_total' => (int) round((float) $donation->montant * 100),
            'currency' => 'eur',
            'metadata' => [
                'donation_id' => (string) $donation->id,
                'project_id' => (string) $donation->project_id,
            ],
        ], $overrides);
    }

    private function successUrl(Funding $donation, ?string $sessionId = null): string
    {
        return route('citizen.projects.donation.success', $donation->project)
            .'?session_id='.($sessionId ?? $donation->stripe_session_id);
    }

    private function pendingDonation(string $sessionId = 'cs_test_123'): Funding
    {
        $citizen = User::factory()->citoyen()->create();
        $project = Project::factory()->enCours()->create();

        return $this->createDonation($project, $citizen, 250, $sessionId, Funding::PAIEMENT_EN_ATTENTE);
    }

    private function createDonation(
        Project $project,
        User $citizen,
        float $montant,
        string $sessionId,
        string $statut,
    ): Funding {
        return $project->fundings()->create([
            'source' => 'don',
            'donateur_id' => $citizen->id,
            'montant' => $montant,
            'date_versement' => now()->toDateString(),
            'statut_paiement' => $statut,
            'stripe_session_id' => $sessionId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $sessionOverrides
     * @return array{0: string, 1: string}
     */
    private function webhookPayload(Funding $donation, array $sessionOverrides = []): array
    {
        $session = array_merge([
            'id' => $donation->stripe_session_id,
            'object' => 'checkout.session',
            'mode' => 'payment',
            'livemode' => false,
            'client_reference_id' => (string) $donation->id,
            'payment_status' => 'paid',
            'amount_total' => (int) round((float) $donation->montant * 100),
            'currency' => 'eur',
            'metadata' => [
                'donation_id' => (string) $donation->id,
                'project_id' => (string) $donation->project_id,
            ],
        ], $sessionOverrides);

        $payload = json_encode([
            'id' => 'evt_test_'.uniqid(),
            'object' => 'event',
            'api_version' => '2025-11-17.clover',
            'created' => time(),
            'livemode' => false,
            'pending_webhooks' => 1,
            'type' => 'checkout.session.completed',
            'data' => ['object' => $session],
        ], JSON_UNESCAPED_UNICODE);

        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, self::WEBHOOK_SECRET);

        return [$payload, 't='.$timestamp.',v1='.$signature];
    }

    private function postWebhook(string $payload, string $signature): TestResponse
    {
        return $this->call('POST', '/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ], $payload);
    }
}
