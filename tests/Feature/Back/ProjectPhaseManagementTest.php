<?php

namespace Tests\Feature\Back;

use App\Models\Project\Project;
use App\Models\Project\ProjectPhase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectPhaseManagementTest extends TestCase
{
    use RefreshDatabase;

    private function gestionnaire(): User
    {
        return User::factory()->gestionnaire()->create();
    }

    private function phasePayload(ProjectPhase $phase, array $overrides = []): array
    {
        return array_merge([
            'nom' => 'Phase actualisée',
            'contractor_id' => $phase->contractor_id,
            'date_debut' => now()->subMonth()->toDateString(),
            'date_fin' => now()->addMonths(2)->toDateString(),
            'cout' => 45000.50,
            'avancement' => 65,
        ], $overrides);
    }

    public function test_gestionnaire_can_update_a_project_phase(): void
    {
        $phase = ProjectPhase::factory()->create(['nom' => 'Ancienne phase', 'avancement' => 10]);

        $this->actingAs($this->gestionnaire())
            ->put(route('back.projects.phases.update', [$phase->project_id, $phase]), $this->phasePayload($phase))
            ->assertRedirect(route('back.projects.show', $phase->project_id))
            ->assertSessionHas('success', 'La phase « Phase actualisée » a été mise à jour.');

        $this->assertDatabaseHas('project_phases', [
            'id' => $phase->id,
            'nom' => 'Phase actualisée',
            'cout' => 45000.50,
            'avancement' => 65,
        ]);
    }

    public function test_project_phase_update_rejects_invalid_values(): void
    {
        $phase = ProjectPhase::factory()->create(['nom' => 'Phase stable']);

        $this->actingAs($this->gestionnaire())
            ->from(route('back.projects.phases.edit', [$phase->project_id, $phase]))
            ->put(route('back.projects.phases.update', [$phase->project_id, $phase]), $this->phasePayload($phase, [
                'nom' => '',
                'contractor_id' => 999999,
                'date_fin' => now()->subYear()->toDateString(),
                'cout' => -10,
                'avancement' => 150,
            ]))
            ->assertRedirect(route('back.projects.phases.edit', [$phase->project_id, $phase]))
            ->assertSessionHasErrors(['nom', 'contractor_id', 'date_fin', 'cout', 'avancement']);

        $this->assertDatabaseHas('project_phases', [
            'id' => $phase->id,
            'nom' => 'Phase stable',
        ]);
    }

    public function test_gestionnaire_can_delete_a_project_phase(): void
    {
        $phase = ProjectPhase::factory()->create();

        $this->actingAs($this->gestionnaire())
            ->delete(route('back.projects.phases.destroy', [$phase->project_id, $phase]))
            ->assertRedirect(route('back.projects.show', $phase->project_id))
            ->assertSessionHas('success');

        $this->assertModelMissing($phase);
    }

    public function test_a_phase_cannot_be_modified_through_another_project(): void
    {
        $phase = ProjectPhase::factory()->create();
        $otherProject = Project::factory()->create();

        $this->actingAs($this->gestionnaire())
            ->put(route('back.projects.phases.update', [$otherProject, $phase]), $this->phasePayload($phase))
            ->assertNotFound();

        $this->actingAs($this->gestionnaire())
            ->delete(route('back.projects.phases.destroy', [$otherProject, $phase]))
            ->assertNotFound();

        $this->assertModelExists($phase);
    }

    public function test_citizen_cannot_update_or_delete_project_phases(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $phase = ProjectPhase::factory()->create();

        $this->actingAs($citizen)
            ->put(route('back.projects.phases.update', [$phase->project_id, $phase]), $this->phasePayload($phase))
            ->assertForbidden();
        $this->actingAs($citizen)
            ->delete(route('back.projects.phases.destroy', [$phase->project_id, $phase]))
            ->assertForbidden();

        $this->assertModelExists($phase);
    }
}
