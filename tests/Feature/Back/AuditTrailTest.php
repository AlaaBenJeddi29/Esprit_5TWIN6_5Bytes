<?php

namespace Tests\Feature\Back;

use App\Models\Infrastructure\Zone;
use App\Models\Project\AuditEvent;
use App\Models\Project\Contractor;
use App\Models\Project\Funding;
use App\Models\Project\Project;
use App\Models\Project\ProjectDocument;
use App\Models\Project\ProjectPhase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Stripe\ApiRequestor;
use Tests\Support\FakeStripeHttpClient;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);

        parent::tearDown();
    }

    private function projectPayload(array $overrides = []): array
    {
        return array_merge([
            'titre' => 'Remplacement canalisation principale',
            'type' => 'réparation',
            'description' => 'Réfection du réseau principal.',
            'budget_prevu' => 150000,
            'date_debut' => now()->format('Y-m-d'),
            'date_fin_prevue' => now()->addMonths(6)->format('Y-m-d'),
            'statut' => 'planifié',
            'avancement_pourcentage' => 0,
            'zone_id' => Zone::factory()->create()->id,
            'infrastructure_id' => null,
            'responsable_id' => User::factory()->gestionnaire()->create()->id,
        ], $overrides);
    }

    public function test_project_creation_is_logged_with_actor_and_project_link(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();

        $this->actingAs($gestionnaire)
            ->post(route('back.projects.store'), $this->projectPayload())
            ->assertRedirect();

        $project = Project::firstOrFail();
        $event = AuditEvent::where('action', 'project.created')->firstOrFail();

        $this->assertSame($gestionnaire->id, $event->user_id);
        $this->assertSame($project->id, $event->subject_id);
        $this->assertSame('project', $event->subject_type);
        $this->assertSame($project->id, $event->project_id);
        $this->assertStringContainsString($project->titre, $event->summary);
    }

    public function test_project_update_logs_only_changed_fields_with_formatted_dates(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        $project = Project::factory()->create([
            'statut' => 'planifié',
            'date_debut' => '2026-01-15',
            'date_fin_prevue' => '2026-06-15',
        ]);

        $payload = [
            'titre' => $project->titre,
            'type' => $project->type,
            'description' => $project->description,
            'budget_prevu' => (float) $project->budget_prevu,
            'date_debut' => $project->date_debut->format('Y-m-d'),
            'date_fin_prevue' => '2027-03-15',
            'statut' => 'en_cours',
            'avancement_pourcentage' => $project->avancement_pourcentage,
            'zone_id' => $project->zone_id,
            'infrastructure_id' => null,
            'responsable_id' => $project->responsable_id,
        ];

        $this->actingAs($gestionnaire)
            ->put(route('back.projects.update', $project), $payload)
            ->assertRedirect(route('back.projects.show', $project));

        $event = AuditEvent::where('action', 'project.updated')->firstOrFail();

        $this->assertSame($project->id, $event->project_id);
        $this->assertStringContainsString('statut : planifié → en_cours', $event->summary);
        $this->assertStringContainsString('date_fin_prevue', $event->summary);
        $this->assertStringContainsString('15/06/2026 → 15/03/2027', $event->summary);
        // Les champs inchangés ne doivent pas apparaître dans le résumé.
        $this->assertStringNotContainsString('budget_prevu :', $event->summary);
        $this->assertStringNotContainsString('titre :', $event->summary);
        $this->assertStringNotContainsString('date_debut :', $event->summary);
    }

    public function test_phase_funding_and_document_creations_are_logged(): void
    {
        Storage::fake('local');

        $gestionnaire = User::factory()->gestionnaire()->create();
        $project = Project::factory()->create();

        $this->actingAs($gestionnaire)->post(route('back.projects.phases.store', $project), [
            'nom' => 'Phase 1 - Études',
            'contractor_id' => Contractor::factory()->create()->id,
            'date_debut' => now()->format('Y-m-d'),
            'date_fin' => now()->addMonth()->format('Y-m-d'),
            'cout' => 25000,
            'avancement' => 0,
        ])->assertRedirect();

        $this->actingAs($gestionnaire)->post(route('back.projects.fundings.store', $project), [
            'source' => 'municipal',
            'donateur_id' => null,
            'montant' => 50000,
            'date_versement' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $this->actingAs($gestionnaire)->post(route('back.projects.documents.store', $project), [
            'titre' => 'Rapport d\'étude',
            'fichier' => UploadedFile::fake()->create('rapport.pdf', 100, 'application/pdf'),
            'type' => 'rapport',
        ])->assertRedirect();

        $this->assertSame(1, AuditEvent::where('action', 'phase.created')->count());
        $this->assertSame(1, AuditEvent::where('action', 'funding.created')->count());
        $this->assertSame(1, AuditEvent::where('action', 'document.created')->count());

        foreach (['phase', 'funding', 'document'] as $subject) {
            $this->assertSame(
                $project->id,
                AuditEvent::where('subject_type', $subject)->firstOrFail()->project_id,
            );
        }
    }

    public function test_funding_update_and_delete_are_logged(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        $project = Project::factory()->create();
        $funding = Funding::factory()->municipal()->create([
            'project_id' => $project->id,
            'montant' => 1000,
        ]);

        $this->actingAs($gestionnaire)->put(route('back.projects.fundings.update', [$project, $funding]), [
            'source' => 'municipal',
            'donateur_id' => null,
            'montant' => 2500,
            'date_versement' => $funding->date_versement->format('Y-m-d'),
        ])->assertRedirect(route('back.projects.show', $project));

        $event = AuditEvent::where('action', 'funding.updated')->firstOrFail();
        $this->assertStringContainsString('montant', $event->summary);
        $this->assertStringContainsString('2500.00', $event->summary);

        $this->actingAs($gestionnaire)
            ->delete(route('back.projects.fundings.destroy', [$project, $funding]))
            ->assertRedirect(route('back.projects.show', $project));

        $deleteEvent = AuditEvent::where('action', 'funding.deleted')->firstOrFail();
        $this->assertStringContainsString('Financement', $deleteEvent->summary);
    }

    public function test_document_replacement_summary_does_not_leak_storage_paths(): void
    {
        Storage::fake('local');

        $gestionnaire = User::factory()->gestionnaire()->create();
        $project = Project::factory()->create();

        $this->actingAs($gestionnaire)->post(route('back.projects.documents.store', $project), [
            'titre' => 'Plan initial',
            'fichier' => UploadedFile::fake()->create('plan.pdf', 50, 'application/pdf'),
            'type' => 'plan',
        ])->assertRedirect();

        $document = ProjectDocument::firstOrFail();
        $oldPath = $document->chemin_fichier;

        $this->actingAs($gestionnaire)->put(route('back.projects.documents.update', [$project, $document]), [
            'titre' => 'Plan révisé',
            'type' => 'plan',
            'fichier' => UploadedFile::fake()->create('plan-v2.pdf', 60, 'application/pdf'),
        ])->assertRedirect(route('back.projects.show', $project));

        $newPath = $document->fresh()->chemin_fichier;
        $event = AuditEvent::where('action', 'document.updated')->firstOrFail();

        $this->assertStringContainsString('fichier : remplacé', $event->summary);
        $this->assertStringNotContainsString($oldPath, $event->summary);
        $this->assertStringNotContainsString($newPath, $event->summary);
    }

    public function test_project_deletion_keeps_audit_row_and_nulls_project_link(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        $project = Project::factory()->create();

        $this->actingAs($gestionnaire)
            ->delete(route('back.projects.destroy', $project))
            ->assertRedirect(route('back.projects.index'));

        $event = AuditEvent::where('action', 'project.deleted')->firstOrFail();

        $this->assertNull($event->project_id);
        $this->assertStringContainsString($project->titre, $event->summary);
        $this->assertSame(1, AuditEvent::count());
    }

    public function test_citizen_donation_is_logged_exactly_once(): void
    {
        config(['services.stripe.secret' => 'sk_test_fake']);
        ApiRequestor::setHttpClient(new FakeStripeHttpClient);

        $citizen = User::factory()->citoyen()->create();
        $project = Project::factory()->create(['statut' => 'en_cours']);

        $this->actingAs($citizen)
            ->post(route('citizen.projects.donate', $project), ['montant' => 75])
            ->assertRedirect('https://checkout.stripe.test/pay/cs_test_123');

        $this->assertSame(1, AuditEvent::where('action', 'funding.created')->count());

        $event = AuditEvent::firstOrFail();
        $this->assertSame($citizen->id, $event->user_id);
        $this->assertSame($project->id, $event->project_id);
        $this->assertSame('funding', $event->subject_type);
    }

    public function test_writes_without_authenticated_actor_log_nothing(): void
    {
        Project::factory()->create();
        Funding::factory()->create();
        ProjectPhase::factory()->create();

        $this->assertSame(0, AuditEvent::count());
    }

    public function test_audit_index_is_restricted_to_back_office_roles(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        $citizen = User::factory()->citoyen()->create();

        $this->get(route('back.audit-events.index'))->assertRedirect(route('login'));
        $this->actingAs($citizen)->get(route('back.audit-events.index'))->assertForbidden();
        $this->actingAs($gestionnaire)->get(route('back.audit-events.index'))->assertOk();
    }

    public function test_audit_index_shows_events_and_filters_by_project(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        $projectA = Project::factory()->create();
        $projectB = Project::factory()->create();

        $this->actingAs($gestionnaire)->post(route('back.projects.fundings.store', $projectA), [
            'source' => 'municipal',
            'donateur_id' => null,
            'montant' => 50000,
            'date_versement' => now()->format('Y-m-d'),
        ]);

        $this->actingAs($gestionnaire)->post(route('back.projects.fundings.store', $projectB), [
            'source' => 'privé',
            'donateur_id' => null,
            'montant' => 12000,
            'date_versement' => now()->format('Y-m-d'),
        ]);

        $this->actingAs($gestionnaire)
            ->get(route('back.audit-events.index'))
            ->assertOk()
            ->assertSee('Financement')
            ->assertSee($gestionnaire->name);

        $filtered = $this->actingAs($gestionnaire)
            ->get(route('back.audit-events.index', ['project_id' => $projectA->id]));

        $filtered->assertOk()->assertSee('50000');
        $filtered->assertDontSee('12000');
    }

    public function test_audit_entries_have_no_edit_or_delete_routes(): void
    {
        $this->assertTrue(Route::has('back.audit-events.index'));
        $this->assertFalse(Route::has('back.audit-events.create'));
        $this->assertFalse(Route::has('back.audit-events.store'));
        $this->assertFalse(Route::has('back.audit-events.edit'));
        $this->assertFalse(Route::has('back.audit-events.update'));
        $this->assertFalse(Route::has('back.audit-events.destroy'));
    }
}
