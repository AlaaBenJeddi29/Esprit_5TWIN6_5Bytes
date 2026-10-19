<?php

namespace Tests\Feature\Project;

use App\Models\Project\Project;
use App\Models\Project\ProjectPhase;
use App\Models\User;
use App\Services\ProjectDeadlineIndicators;
use App\Services\ProjectMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectDeadlineIndicatorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_is_overdue_when_end_date_passed_and_incomplete(): void
    {
        $phase = ProjectPhase::factory()->make([
            'date_fin' => now()->subDays(3),
            'avancement' => 40,
        ]);

        $this->assertSame(ProjectDeadlineIndicators::ECHEANCE_DEPASSEE, ProjectDeadlineIndicators::phaseStatus($phase));

        $explication = ProjectDeadlineIndicators::phaseExplanation($phase);
        $this->assertStringContainsString($phase->date_fin->format('d/m/Y'), $explication);
        $this->assertStringContainsString('dépassée de 3 jour(s)', $explication);
        $this->assertStringContainsString('40 %', $explication);
    }

    public function test_completed_phase_is_never_overdue_even_with_inconsistent_progress(): void
    {
        $terminee = ProjectPhase::factory()->make([
            'date_fin' => now()->subWeek(),
            'avancement' => 100,
        ]);
        $avancementExcessif = ProjectPhase::factory()->make([
            'date_fin' => now()->subWeek(),
            'avancement' => 150,
        ]);

        $this->assertSame(ProjectDeadlineIndicators::ECHEANCE_A_JOUR, ProjectDeadlineIndicators::phaseStatus($terminee));
        $this->assertSame(ProjectDeadlineIndicators::ECHEANCE_A_JOUR, ProjectDeadlineIndicators::phaseStatus($avancementExcessif));
        $this->assertNull(ProjectDeadlineIndicators::phaseExplanation($terminee));
    }

    public function test_phase_upcoming_and_boundary_dates(): void
    {
        $this->assertSame(
            ProjectDeadlineIndicators::ECHEANCE_PROCHE,
            ProjectDeadlineIndicators::phaseStatus(ProjectPhase::factory()->make(['date_fin' => now()->addWeek(), 'avancement' => 10])),
        );

        // Bornes du seuil « proche » (APPROACHING_DAYS = 14).
        $this->assertSame(ProjectDeadlineIndicators::ECHEANCE_PROCHE, ProjectDeadlineIndicators::phaseStatus(ProjectPhase::factory()->make(['date_fin' => today(), 'avancement' => 10])));
        $this->assertSame(ProjectDeadlineIndicators::ECHEANCE_PROCHE, ProjectDeadlineIndicators::phaseStatus(ProjectPhase::factory()->make(['date_fin' => today()->addDays(14), 'avancement' => 10])));
        $this->assertSame(ProjectDeadlineIndicators::ECHEANCE_A_JOUR, ProjectDeadlineIndicators::phaseStatus(ProjectPhase::factory()->make(['date_fin' => today()->addDays(15), 'avancement' => 10])));
        $this->assertSame(ProjectDeadlineIndicators::ECHEANCE_DEPASSEE, ProjectDeadlineIndicators::phaseStatus(ProjectPhase::factory()->make(['date_fin' => today()->subDay(), 'avancement' => 10])));
    }

    public function test_phase_explanation_for_today_says_aujourd_hui(): void
    {
        $phase = ProjectPhase::factory()->make(['date_fin' => today(), 'avancement' => 80]);

        $this->assertSame(ProjectDeadlineIndicators::ECHEANCE_PROCHE, ProjectDeadlineIndicators::phaseStatus($phase));
        $this->assertStringContainsString("aujourd'hui", ProjectDeadlineIndicators::phaseExplanation($phase));
    }

    public function test_phase_without_end_date_has_no_indicator(): void
    {
        $phase = ProjectPhase::factory()->make(['date_fin' => null, 'avancement' => 10]);

        $this->assertSame(ProjectDeadlineIndicators::ECHEANCE_AUCUNE, ProjectDeadlineIndicators::phaseStatus($phase));
        $this->assertNull(ProjectDeadlineIndicators::phaseExplanation($phase));
    }

    public function test_project_overdue_only_when_open_and_deadline_passed(): void
    {
        $enRetard = Project::factory()->enCours()->create(['date_fin_prevue' => now()->subMonth()]);
        $termine = Project::factory()->termine()->create(['date_fin_prevue' => now()->subMonth()]);
        $annule = Project::factory()->create(['statut' => 'annulé', 'date_fin_prevue' => now()->subMonth()]);
        $aVenir = Project::factory()->enCours()->create(['date_fin_prevue' => now()->addMonth()]);

        $this->assertSame(ProjectDeadlineIndicators::ECHEANCE_DEPASSEE, ProjectDeadlineIndicators::projectStatus($enRetard));
        $this->assertSame(ProjectDeadlineIndicators::ECHEANCE_A_JOUR, ProjectDeadlineIndicators::projectStatus($termine));
        $this->assertSame(ProjectDeadlineIndicators::ECHEANCE_A_JOUR, ProjectDeadlineIndicators::projectStatus($annule));
        $this->assertSame(ProjectDeadlineIndicators::ECHEANCE_A_JOUR, ProjectDeadlineIndicators::projectStatus($aVenir));

        $this->assertTrue(ProjectDeadlineIndicators::projectIsClosed($termine));
        $this->assertFalse(ProjectDeadlineIndicators::projectIsClosed($enRetard));

        $explication = ProjectDeadlineIndicators::projectExplanation($enRetard);
        $this->assertStringContainsString($enRetard->date_fin_prevue->format('d/m/Y'), $explication);
        $this->assertStringContainsString('statut « en cours »', $explication);
    }

    public function test_project_upcoming_and_without_deadline(): void
    {
        $proche = Project::factory()->enCours()->create(['date_fin_prevue' => now()->addDays(5)]);
        $sansEcheance = Project::factory()->make(['date_fin_prevue' => null]);

        $this->assertSame(ProjectDeadlineIndicators::ECHEANCE_PROCHE, ProjectDeadlineIndicators::projectStatus($proche));
        $this->assertStringContainsString('dans 5 jour(s)', ProjectDeadlineIndicators::projectExplanation($proche));

        $this->assertSame(ProjectDeadlineIndicators::ECHEANCE_AUCUNE, ProjectDeadlineIndicators::projectStatus($sansEcheance));
        $this->assertNull(ProjectDeadlineIndicators::projectExplanation($sansEcheance));
    }

    public function test_project_metrics_delegates_deadline_status(): void
    {
        $proche = Project::factory()->enCours()->create(['date_fin_prevue' => now()->addDays(7)]);
        $enRetard = Project::factory()->enCours()->create(['date_fin_prevue' => now()->subDay()]);

        $this->assertSame(ProjectDeadlineIndicators::ECHEANCE_PROCHE, (new ProjectMetrics($proche))->deadlineStatus);
        $this->assertSame(ProjectDeadlineIndicators::ECHEANCE_DEPASSEE, (new ProjectMetrics($enRetard))->deadlineStatus);
    }

    public function test_back_index_shows_deadline_and_overdue_phase_badges(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        $projet = Project::factory()->enCours()->create([
            'titre' => 'Projet en retard index',
            'date_fin_prevue' => now()->subMonth(),
        ]);
        ProjectPhase::factory()->create(['project_id' => $projet->id, 'date_fin' => now()->subWeek(), 'avancement' => 20]);
        ProjectPhase::factory()->create(['project_id' => $projet->id, 'date_fin' => now()->subDays(3), 'avancement' => 60]);

        $this->actingAs($gestionnaire)
            ->get(route('back.projects.index'))
            ->assertOk()
            ->assertSee('Échéance dépassée')
            ->assertSee('2 phase(s) en retard');
    }

    public function test_back_index_shows_nothing_for_closed_projects(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        $projet = Project::factory()->termine()->create([
            'titre' => 'Projet terminé index',
            'date_fin_prevue' => now()->subMonth(),
        ]);
        ProjectPhase::factory()->create(['project_id' => $projet->id, 'date_fin' => now()->subWeek(), 'avancement' => 20]);

        $this->actingAs($gestionnaire)
            ->get(route('back.projects.index'))
            ->assertOk()
            ->assertDontSee('Échéance dépassée')
            ->assertDontSee('phase(s) en retard');
    }

    public function test_back_show_displays_phase_badge_and_project_explanation(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        $projet = Project::factory()->enCours()->create([
            'titre' => 'Projet détail retard',
            'date_fin_prevue' => now()->subDays(10),
        ]);
        ProjectPhase::factory()->create([
            'project_id' => $projet->id,
            'date_fin' => now()->subDays(2),
            'avancement' => 30,
        ]);

        $this->actingAs($gestionnaire)
            ->get(route('back.projects.show', $projet))
            ->assertOk()
            ->assertSee('En retard')
            ->assertSee('échéance dépassée de 10 jour(s)')
            ->assertSee('Dépassée');
    }

    public function test_back_show_hides_phase_badges_for_cancelled_project(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        $projet = Project::factory()->create([
            'statut' => 'annulé',
            'titre' => 'Projet annulé détail',
            'date_fin_prevue' => now()->subMonth(),
        ]);
        ProjectPhase::factory()->create([
            'project_id' => $projet->id,
            'date_fin' => now()->subWeek(),
            'avancement' => 30,
        ]);

        $this->actingAs($gestionnaire)
            ->get(route('back.projects.show', $projet))
            ->assertOk()
            ->assertDontSee('En retard');
    }

    public function test_phase_edit_page_shows_indicator(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        $projet = Project::factory()->enCours()->create();
        $phase = ProjectPhase::factory()->create([
            'project_id' => $projet->id,
            'date_fin' => now()->subDays(4),
            'avancement' => 50,
        ]);

        $this->actingAs($gestionnaire)
            ->get(route('back.projects.phases.edit', [$projet, $phase]))
            ->assertOk()
            ->assertSee('Échéance du '.$phase->date_fin->format('d/m/Y').' dépassée de 4 jour(s)');
    }

    public function test_front_show_displays_public_indicators_and_hides_them_for_cancelled(): void
    {
        $projet = Project::factory()->enCours()->create([
            'titre' => 'Projet public retard',
            'date_fin_prevue' => now()->subWeek(),
        ]);
        ProjectPhase::factory()->create([
            'project_id' => $projet->id,
            'date_fin' => now()->subDay(),
            'avancement' => 25,
        ]);

        $this->get(route('public.projects.show', $projet))
            ->assertOk()
            ->assertSee('Échéance dépassée')
            ->assertSee('En retard');

        $annule = Project::factory()->create([
            'statut' => 'annulé',
            'titre' => 'Projet public annulé',
            'date_fin_prevue' => now()->subWeek(),
        ]);
        ProjectPhase::factory()->create([
            'project_id' => $annule->id,
            'date_fin' => now()->subDay(),
            'avancement' => 25,
        ]);

        $this->get(route('public.projects.show', $annule))
            ->assertOk()
            ->assertDontSee('Échéance dépassée')
            ->assertDontSee('En retard');
    }
}
