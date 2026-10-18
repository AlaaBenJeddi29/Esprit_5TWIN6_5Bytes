<?php

namespace Tests\Feature\Front;

use App\Models\AlertSubscription;
use App\Models\Drought\ConsumptionReading;
use App\Models\Drought\Restriction;
use App\Models\Drought\ScheduledCut;
use App\Models\Drought\WaterLevel;
use App\Models\Incident\Incident;
use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Zone;
use App\Models\Project\Funding;
use App\Models\Project\Project;
use App\Models\Project\ProjectDocument;
use App\Models\Quality\WaterQualityAlert;
use App\Models\Quality\WaterQualityParameter;
use App\Models\Quality\WaterQualitySample;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicAndCitizenTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_is_available_with_empty_database(): void
    {
        $this->get(route('front.home'))->assertOk();
    }

    public function test_public_pages_are_available_with_data(): void
    {
        $zone = Zone::factory()->create(['nom' => 'Zone Publique Test']);
        $infrastructure = Infrastructure::factory()->create(['zone_id' => $zone->id, 'nom' => 'Réservoir Public']);

        $sample = WaterQualitySample::factory()->nonConforme()->create(['zone_id' => $zone->id]);
        WaterQualityParameter::factory()->nonConforme()->create(['sample_id' => $sample->id]);
        WaterQualityAlert::factory()->create(['zone_id' => $zone->id, 'sample_id' => $sample->id]);

        WaterLevel::factory()->create(['zone_id' => $zone->id]);
        ConsumptionReading::factory()->create(['zone_id' => $zone->id]);
        Restriction::factory()->create(['zone_id' => $zone->id, 'titre' => 'Restriction publique test']);
        ScheduledCut::factory()->create(['zone_id' => $zone->id]);

        $project = Project::factory()->create(['zone_id' => $zone->id, 'statut' => 'en_cours']);
        ProjectDocument::factory()->create([
            'project_id' => $project->id,
            'titre' => 'Document public test',
            'est_public' => true,
        ]);

        Incident::factory()->create(['zone_id' => $zone->id]);

        $this->get(route('front.home'))->assertOk()->assertSee('Zone Publique Test');
        $this->get(route('public.quality.index'))->assertOk();
        $this->get(route('public.quality.zone', $zone))->assertOk();
        $this->get(route('public.drought.index'))->assertOk()->assertSee('Restriction publique test');
        $this->get(route('public.drought.zone', $zone))->assertOk();
        $this->get(route('public.infrastructures.index'))->assertOk()->assertSee('Réservoir Public');
        $this->get(route('public.infrastructures.show', $infrastructure))->assertOk();
        $this->get(route('public.projects.index'))->assertOk();
        $this->get(route('public.projects.show', $project))->assertOk();
        $this->get(route('front.incidents.track.form'))->assertOk();
    }

    public function test_citizen_dashboard_is_available_and_restricted_to_citizens(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $gestionnaire = User::factory()->gestionnaire()->create();
        $zone = Zone::factory()->create(['nom' => 'Zone Citoyen']);
        AlertSubscription::factory()->create(['user_id' => $citizen->id, 'zone_id' => $zone->id]);
        Incident::factory()->create(['user_id' => $citizen->id, 'zone_id' => $zone->id]);

        $this->actingAs($citizen)->get(route('citizen.dashboard'))->assertOk()->assertSee('Zone Citoyen');
        $this->actingAs($gestionnaire)->get(route('citizen.dashboard'))->assertForbidden();
    }

    public function test_citizen_can_manage_alert_subscriptions(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $zone = Zone::factory()->create();

        $this->actingAs($citizen)
            ->post(route('citizen.subscriptions.store'), [
                'zone_id' => $zone->id,
                'type_alerte' => 'qualite',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('alert_subscriptions', [
            'user_id' => $citizen->id,
            'zone_id' => $zone->id,
            'type_alerte' => 'qualite',
        ]);

        $this->actingAs($citizen)
            ->post(route('citizen.subscriptions.store'), [
                'zone_id' => $zone->id,
                'type_alerte' => 'tous',
            ])
            ->assertRedirect();

        $this->assertSame(1, AlertSubscription::count());
        $this->assertSame('tous', AlertSubscription::firstOrFail()->type_alerte);

        $subscription = AlertSubscription::firstOrFail();

        $this->actingAs($citizen)
            ->delete(route('citizen.subscriptions.destroy', $subscription))
            ->assertRedirect();

        $this->assertDatabaseCount('alert_subscriptions', 0);
    }

    public function test_citizen_cannot_delete_another_citizens_subscription(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $other = User::factory()->citoyen()->create();
        $subscription = AlertSubscription::factory()->create(['user_id' => $other->id]);

        $this->actingAs($citizen)
            ->delete(route('citizen.subscriptions.destroy', $subscription))
            ->assertForbidden();

        $this->assertDatabaseHas('alert_subscriptions', ['id' => $subscription->id]);
    }

    public function test_subscription_validation_rejects_unknown_zone_and_type(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $zone = Zone::factory()->create();

        $this->actingAs($citizen)
            ->from(route('citizen.subscriptions.index'))
            ->post(route('citizen.subscriptions.store'), [
                'zone_id' => 9999,
                'type_alerte' => 'inconnu',
            ])
            ->assertSessionHasErrors(['zone_id', 'type_alerte']);

        $this->assertDatabaseCount('alert_subscriptions', 0);
    }

    public function test_citizen_can_make_a_simulated_donation(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $project = Project::factory()->create(['statut' => 'en_cours']);

        $this->actingAs($citizen)
            ->post(route('citizen.projects.donate', $project), ['montant' => 250])
            ->assertRedirect(route('public.projects.show', $project))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('fundings', [
            'project_id' => $project->id,
            'source' => 'don',
            'donateur_id' => $citizen->id,
            'montant' => 250,
        ]);
    }

    public function test_donation_is_forbidden_for_gestionnaire_and_invalid_amount_rejected(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        $citizen = User::factory()->citoyen()->create();
        $project = Project::factory()->create();

        $this->actingAs($gestionnaire)
            ->post(route('citizen.projects.donate', $project), ['montant' => 100])
            ->assertForbidden();

        $this->actingAs($citizen)
            ->from(route('public.projects.show', $project))
            ->post(route('citizen.projects.donate', $project), ['montant' => 0])
            ->assertSessionHasErrors(['montant']);

        $this->assertSame(0, Funding::where('source', 'don')->count());
    }

    public function test_public_project_assistant_answers_without_external_ai(): void
    {
        // Ollama est simulé injoignable : l'assistant doit retomber sur son
        // secours déterministe (mots-clés) sans jamais dépendre d'un IA externe.
        Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect'));

        $project = Project::factory()->create(['budget_prevu' => 100000]);

        $this->post(route('public.projects.assistant', $project), [
            'question' => 'Quel est le budget et son financement ?',
        ])
            ->assertRedirect(route('public.projects.show', $project))
            ->assertSessionHas('assistant_answer');

        $answer = session('assistant_answer');

        $this->assertStringContainsString('100 000,00', $answer);

        $this->postJson(route('public.projects.assistant', $project), [
            'question' => 'Où en est l\'avancement ?',
        ])
            ->assertOk()
            ->assertJsonPath('disclaimer', fn ($value) => str_contains($value, 'sans fournisseur d\'IA externe'));
    }

    public function test_public_project_document_download_only_for_public_files(): void
    {
        Storage::fake('local');

        $project = Project::factory()->create();
        $public = ProjectDocument::factory()->create([
            'project_id' => $project->id,
            'chemin_fichier' => 'projects/demo/public.pdf',
            'est_public' => true,
        ]);
        $private = ProjectDocument::factory()->create([
            'project_id' => $project->id,
            'chemin_fichier' => 'projects/demo/private.pdf',
            'est_public' => false,
        ]);

        Storage::disk('local')->put($public->chemin_fichier, 'contenu public');
        Storage::disk('local')->put($private->chemin_fichier, 'contenu privé');

        $this->get(route('public.projects.documents.download', [$project, $public]))->assertOk();
        $this->get(route('public.projects.documents.download', [$project, $private]))->assertNotFound();
    }
}
