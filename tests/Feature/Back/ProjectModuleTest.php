<?php

namespace Tests\Feature\Back;

use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Zone;
use App\Models\Project\Contractor;
use App\Models\Project\Funding;
use App\Models\Project\Project;
use App\Models\Project\ProjectDocument;
use App\Models\Project\ProjectPhase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectModuleTest extends TestCase
{
    use RefreshDatabase;

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

    private function phasePayload(array $overrides = []): array
    {
        return array_merge([
            'nom' => 'Phase 1 - Études',
            'contractor_id' => Contractor::factory()->create()->id,
            'date_debut' => now()->format('Y-m-d'),
            'date_fin' => now()->addMonth()->format('Y-m-d'),
            'cout' => 25000,
            'avancement' => 0,
        ], $overrides);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('back.projects.index'))->assertRedirect(route('login'));
        $this->get(route('back.contractors.index'))->assertRedirect(route('login'));
    }

    public function test_citizen_cannot_access_the_module(): void
    {
        $citizen = User::factory()->citoyen()->create();

        $this->actingAs($citizen)->get(route('back.projects.index'))->assertForbidden();
        $this->actingAs($citizen)->get(route('back.contractors.index'))->assertForbidden();
    }

    public function test_gestionnaire_can_view_lists(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();

        $this->actingAs($gestionnaire)->get(route('back.projects.index'))->assertOk()->assertSee('Projets');
        $this->actingAs($gestionnaire)->get(route('back.contractors.index'))->assertOk()->assertSee('Entreprises');
    }

    public function test_gestionnaire_can_create_a_contractor(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();

        $response = $this->actingAs($gestionnaire)->post(route('back.contractors.store'), [
            'nom' => 'Hydro Services',
            'specialite' => 'Rénovation canalisations',
            'email' => 'contact@hydro-services.fr',
            'telephone' => '+33 1 23 45 67 89',
            'adresse' => '12 rue des Eaux, 69000 Lyon',
        ]);

        $contractor = Contractor::first();

        $this->assertNotNull($contractor);
        $response->assertRedirect(route('back.contractors.show', $contractor));
        $this->assertDatabaseHas('contractors', ['email' => 'contact@hydro-services.fr']);
    }

    public function test_contractor_validation_fails_with_invalid_data(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        Contractor::factory()->create(['email' => 'doublon@example.fr']);

        $this->actingAs($gestionnaire)
            ->from(route('back.contractors.create'))
            ->post(route('back.contractors.store'), [
                'nom' => '',
                'specialite' => '',
                'email' => 'doublon@example.fr',
                'telephone' => '',
                'adresse' => '',
            ])
            ->assertSessionHasErrors(['nom', 'specialite', 'email', 'telephone', 'adresse']);
    }

    public function test_contractor_can_be_updated_and_deleted_when_unused(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        $contractor = Contractor::factory()->create();

        $this->actingAs($gestionnaire)->put(route('back.contractors.update', $contractor), [
            'nom' => 'Hydro Services SA',
            'specialite' => 'Électromécanique',
            'email' => $contractor->email,
            'telephone' => $contractor->telephone,
            'adresse' => $contractor->adresse,
        ])->assertRedirect(route('back.contractors.show', $contractor));

        $this->assertDatabaseHas('contractors', ['id' => $contractor->id, 'nom' => 'Hydro Services SA']);

        $this->actingAs($gestionnaire)
            ->delete(route('back.contractors.destroy', $contractor))
            ->assertRedirect(route('back.contractors.index'));

        $this->assertDatabaseMissing('contractors', ['id' => $contractor->id]);
    }

    public function test_contractor_with_phases_cannot_be_deleted(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        $project = Project::factory()->create();
        $contractor = Contractor::factory()->create();
        ProjectPhase::factory()->create([
            'project_id' => $project->id,
            'contractor_id' => $contractor->id,
        ]);

        $this->actingAs($gestionnaire)
            ->delete(route('back.contractors.destroy', $contractor))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('contractors', ['id' => $contractor->id]);
    }

    public function test_gestionnaire_can_create_a_project(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();

        $response = $this->actingAs($gestionnaire)->post(route('back.projects.store'), $this->projectPayload());

        $project = Project::first();

        $this->assertNotNull($project);
        $response->assertRedirect(route('back.projects.show', $project));
        $this->assertDatabaseHas('projects', ['titre' => 'Remplacement canalisation principale']);
    }

    public function test_project_validation_rejects_invalid_data(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        $zone = Zone::factory()->create();

        $this->actingAs($gestionnaire)
            ->from(route('back.projects.create'))
            ->post(route('back.projects.store'), $this->projectPayload([
                'type' => 'invalide',
                'statut' => 'inexistant',
                'date_debut' => now()->format('Y-m-d'),
                'date_fin_prevue' => now()->subMonth()->format('Y-m-d'),
                'zone_id' => $zone->id,
                'responsable_id' => User::factory()->citoyen()->create()->id,
            ]))
            ->assertSessionHasErrors(['type', 'statut', 'date_fin_prevue', 'responsable_id']);

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_gestionnaire_can_update_a_project(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        $project = Project::factory()->create(['statut' => 'planifié']);

        $response = $this->actingAs($gestionnaire)->put(route('back.projects.update', $project), $this->projectPayload([
            'zone_id' => $project->zone_id,
            'responsable_id' => $project->responsable_id,
            'statut' => 'en_cours',
            'avancement_pourcentage' => 40,
            'titre' => 'Titre mis à jour',
        ]));

        $response->assertRedirect(route('back.projects.show', $project));
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'statut' => 'en_cours',
            'titre' => 'Titre mis à jour',
        ]);
    }

    public function test_gestionnaire_can_add_a_phase_to_a_project(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        $project = Project::factory()->create();

        $response = $this->actingAs($gestionnaire)->post(
            route('back.projects.phases.store', $project),
            $this->phasePayload()
        );

        $response->assertRedirect(route('back.projects.show', $project));
        $this->assertDatabaseHas('project_phases', [
            'project_id' => $project->id,
            'nom' => 'Phase 1 - Études',
        ]);
    }

    public function test_phase_scoped_to_wrong_project_returns_not_found(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        $projectA = Project::factory()->create();
        $projectB = Project::factory()->create();
        $phase = ProjectPhase::factory()->create(['project_id' => $projectB->id]);

        $this->actingAs($gestionnaire)
            ->get(route('back.projects.phases.edit', [$projectA, $phase]))
            ->assertNotFound();
    }

    public function test_gestionnaire_can_add_funding_and_document(): void
    {
        Storage::fake('local');

        $gestionnaire = User::factory()->gestionnaire()->create();
        $project = Project::factory()->create();

        $this->actingAs($gestionnaire)->post(route('back.projects.fundings.store', $project), [
            'source' => 'municipal',
            'donateur_id' => null,
            'montant' => 50000,
            'date_versement' => now()->format('Y-m-d'),
        ])->assertRedirect(route('back.projects.show', $project));

        $this->actingAs($gestionnaire)->post(route('back.projects.documents.store', $project), [
            'titre' => 'Rapport d\'étude',
            'fichier' => UploadedFile::fake()->create('rapport.pdf', 100, 'application/pdf'),
            'type' => 'rapport',
        ])->assertRedirect(route('back.projects.show', $project));

        $document = ProjectDocument::where('project_id', $project->id)->firstOrFail();

        $this->assertDatabaseHas('fundings', ['project_id' => $project->id, 'source' => 'municipal']);
        $this->assertStringStartsWith("projects/{$project->id}/documents/", $document->chemin_fichier);
        Storage::disk('local')->assertExists($document->chemin_fichier);
    }

    public function test_document_upload_is_required_and_rejects_unsupported_files(): void
    {
        Storage::fake('local');

        $gestionnaire = User::factory()->gestionnaire()->create();
        $project = Project::factory()->create();

        $this->actingAs($gestionnaire)
            ->from(route('back.projects.documents.create', $project))
            ->post(route('back.projects.documents.store', $project), [
                'titre' => 'Sans fichier',
                'type' => 'rapport',
            ])
            ->assertSessionHasErrors('fichier');

        $this->actingAs($gestionnaire)
            ->from(route('back.projects.documents.create', $project))
            ->post(route('back.projects.documents.store', $project), [
                'titre' => 'Exécutable',
                'fichier' => UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload'),
                'type' => 'rapport',
            ])
            ->assertSessionHasErrors('fichier');

        $this->assertDatabaseCount('project_documents', 0);
    }

    public function test_document_file_is_replaced_and_removed_with_the_record(): void
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
        Storage::disk('local')->assertExists($oldPath);

        $this->actingAs($gestionnaire)->put(route('back.projects.documents.update', [$project, $document]), [
            'titre' => 'Plan initial (renommé)',
            'type' => 'plan',
        ])->assertRedirect(route('back.projects.show', $project));

        $this->assertSame($oldPath, $document->fresh()->chemin_fichier);
        Storage::disk('local')->assertExists($oldPath);

        $this->actingAs($gestionnaire)->put(route('back.projects.documents.update', [$project, $document]), [
            'titre' => 'Plan révisé',
            'type' => 'plan',
            'fichier' => UploadedFile::fake()->create('plan-v2.pdf', 60, 'application/pdf'),
        ])->assertRedirect(route('back.projects.show', $project));

        $document->refresh();
        $newPath = $document->chemin_fichier;

        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($newPath);

        $this->actingAs($gestionnaire)
            ->delete(route('back.projects.documents.destroy', [$project, $document]))
            ->assertRedirect(route('back.projects.show', $project));

        Storage::disk('local')->assertMissing($newPath);
        $this->assertDatabaseCount('project_documents', 0);
    }

    public function test_donor_can_only_be_set_for_donation_source(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        $citoyen = User::factory()->citoyen()->create();
        $project = Project::factory()->create();

        $this->actingAs($gestionnaire)
            ->from(route('back.projects.fundings.create', $project))
            ->post(route('back.projects.fundings.store', $project), [
                'source' => 'municipal',
                'donateur_id' => $citoyen->id,
                'montant' => 1000,
                'date_versement' => now()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('donateur_id');

        $this->assertDatabaseCount('fundings', 0);

        $this->actingAs($gestionnaire)->post(route('back.projects.fundings.store', $project), [
            'source' => 'don',
            'donateur_id' => $citoyen->id,
            'montant' => 100,
            'date_versement' => now()->format('Y-m-d'),
        ])->assertRedirect(route('back.projects.show', $project));

        $this->assertDatabaseHas('fundings', [
            'project_id' => $project->id,
            'source' => 'don',
            'donateur_id' => $citoyen->id,
        ]);
    }

    public function test_funding_validation_rejects_unknown_source_and_invalid_donateur(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        $project = Project::factory()->create();

        $this->actingAs($gestionnaire)
            ->from(route('back.projects.fundings.create', $project))
            ->post(route('back.projects.fundings.store', $project), [
                'source' => 'inconnue',
                'donateur_id' => User::factory()->gestionnaire()->create()->id,
                'montant' => -10,
                'date_versement' => now()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors(['source', 'donateur_id', 'montant']);

        $this->assertDatabaseCount('fundings', 0);
    }

    public function test_deleting_a_project_cascades(): void
    {
        Storage::fake('local');

        $gestionnaire = User::factory()->gestionnaire()->create();
        $project = Project::factory()->create();
        ProjectPhase::factory()->create(['project_id' => $project->id]);
        Funding::factory()->create(['project_id' => $project->id]);
        $document = ProjectDocument::factory()->create(['project_id' => $project->id]);
        Storage::disk('local')->put($document->chemin_fichier, 'contenu');

        $this->actingAs($gestionnaire)
            ->delete(route('back.projects.destroy', $project))
            ->assertRedirect(route('back.projects.index'));

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertDatabaseCount('project_phases', 0);
        $this->assertDatabaseCount('fundings', 0);
        $this->assertDatabaseCount('project_documents', 0);
        Storage::disk('local')->assertMissing($document->chemin_fichier);
    }

    public function test_nested_child_forms_render(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        $project = Project::factory()->create();
        $phase = ProjectPhase::factory()->create(['project_id' => $project->id]);
        $funding = Funding::factory()->create(['project_id' => $project->id]);
        $document = ProjectDocument::factory()->create(['project_id' => $project->id]);

        $this->actingAs($gestionnaire)->get(route('back.projects.phases.create', $project))->assertOk();
        $this->actingAs($gestionnaire)->get(route('back.projects.phases.edit', [$project, $phase]))->assertOk();
        $this->actingAs($gestionnaire)->get(route('back.projects.fundings.create', $project))->assertOk();
        $this->actingAs($gestionnaire)->get(route('back.projects.fundings.edit', [$project, $funding]))->assertOk();
        $this->actingAs($gestionnaire)->get(route('back.projects.documents.create', $project))->assertOk();
        $this->actingAs($gestionnaire)->get(route('back.projects.documents.edit', [$project, $document]))->assertOk();
        $this->actingAs($gestionnaire)->get(route('back.projects.show', $project))->assertOk();
    }

    public function test_project_detail_paginates_each_section_independently(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        $project = Project::factory()->create();
        $lastPhase = null;

        foreach (range(1, 16) as $i) {
            $lastPhase = ProjectPhase::factory()->create([
                'project_id' => $project->id,
                'nom' => sprintf('Phase test %02d', $i),
            ]);
            Funding::factory()->municipal()->create([
                'project_id' => $project->id,
                'montant' => 1000 + $i,
            ]);
            ProjectDocument::factory()->create([
                'project_id' => $project->id,
                'titre' => sprintf('Document test %02d', $i),
            ]);
        }

        $this->actingAs($gestionnaire)
            ->get(route('back.projects.show', ['project' => $project, 'phases_page' => 2]))
            ->assertOk()
            ->assertSee('Phases (16)')
            ->assertSee('Financements (16)')
            ->assertSee('Documents (16)')
            ->assertSee('16–16')
            ->assertSee('sur 16 phases')
            ->assertSee('sur 16 financements')
            ->assertSee('sur 16 documents')
            ->assertSee('Phase test 16')
            ->assertDontSee('Phase test 01')
            ->assertSee('1 001,00')
            ->assertDontSee('1 016,00')
            ->assertSee('Document test 01')
            ->assertDontSee('Document test 16')
            ->assertSee(route('back.projects.phases.edit', [$project, $lastPhase]), false);

        $this->actingAs($gestionnaire)
            ->get(route('back.projects.show', [
                'project' => $project,
                'phases_page' => 2,
                'fundings_page' => 2,
                'documents_page' => 2,
            ]))
            ->assertOk()
            ->assertSee('Phase test 16')
            ->assertSee('1 016,00')
            ->assertSee('Document test 16')
            ->assertDontSee('Phase test 01')
            ->assertDontSee('Document test 01');
    }

    public function test_out_of_range_section_page_redirects_to_last_valid_page(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        $project = Project::factory()->create();

        ProjectPhase::factory()->create(['project_id' => $project->id, 'nom' => 'Phase première']);
        Funding::factory()->municipal()->create(['project_id' => $project->id]);

        foreach (range(1, 15) as $i) {
            ProjectPhase::factory()->create(['project_id' => $project->id]);
            Funding::factory()->municipal()->create(['project_id' => $project->id]);
        }

        $response = $this->actingAs($gestionnaire)
            ->get(route('back.projects.show', [
                'project' => $project,
                'phases_page' => 99,
                'fundings_page' => 2,
            ]));

        $response->assertRedirect();

        $location = (string) $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame(
            parse_url(route('back.projects.show', $project), PHP_URL_PATH),
            parse_url($location, PHP_URL_PATH)
        );
        $this->assertSame('2', (string) $query['phases_page']);
        $this->assertSame('2', (string) $query['fundings_page']);

        $this->actingAs($gestionnaire)
            ->get(route('back.projects.show', ['project' => $project, 'phases_page' => 'invalide']))
            ->assertOk()
            ->assertSee('Phase première');
    }

    public function test_project_detail_empty_sections_render_without_pagination(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        $project = Project::factory()->create();

        $this->actingAs($gestionnaire)
            ->get(route('back.projects.show', $project))
            ->assertOk()
            ->assertSee('Aucune phase')
            ->assertSee('Aucun financement')
            ->assertSee('Aucun document')
            ->assertDontSee('Aucun résultat');
    }

    public function test_project_can_be_linked_to_an_infrastructure(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        $zone = Zone::factory()->create();
        $infrastructure = Infrastructure::factory()->create(['zone_id' => $zone->id]);

        $this->actingAs($gestionnaire)->post(route('back.projects.store'), $this->projectPayload([
            'zone_id' => $zone->id,
            'infrastructure_id' => $infrastructure->id,
        ]))->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'zone_id' => $zone->id,
            'infrastructure_id' => $infrastructure->id,
        ]);
    }
}
