<?php

namespace Tests\Feature\Project;

use App\Models\Project\Funding;
use App\Models\Project\Project;
use App\Models\Project\ProjectPhase;
use App\Models\User;
use App\Services\ProjectDeadlineIndicators;
use App\Services\ProjectMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_funding_gap_and_rate_are_computed_from_recorded_fundings(): void
    {
        $project = Project::factory()->create(['budget_prevu' => 1000]);

        Funding::factory()->municipal()->create(['project_id' => $project->id, 'montant' => 250]);
        Funding::factory()->don()->create(['project_id' => $project->id, 'montant' => 250]);

        $metrics = new ProjectMetrics($project);

        $this->assertSame(500.0, $metrics->fundingTotal);
        $this->assertSame(500.0, $metrics->fundingGap);
        $this->assertSame(50.0, $metrics->fundingRate);
        $this->assertFalse($metrics->overFunded);
        $this->assertSame(0.0, $metrics->overFunding);
    }

    public function test_funding_rate_is_rounded_to_one_decimal(): void
    {
        $project = Project::factory()->create(['budget_prevu' => 300]);
        Funding::factory()->municipal()->create(['project_id' => $project->id, 'montant' => 100]);

        $this->assertSame(33.3, (new ProjectMetrics($project))->fundingRate);
    }

    public function test_overfunding_is_flagged_and_gap_never_negative(): void
    {
        $project = Project::factory()->create(['budget_prevu' => 1000]);
        Funding::factory()->municipal()->create(['project_id' => $project->id, 'montant' => 1500]);

        $metrics = new ProjectMetrics($project);

        $this->assertTrue($metrics->overFunded);
        $this->assertSame(500.0, $metrics->overFunding);
        $this->assertSame(0.0, $metrics->fundingGap);
        $this->assertSame(150.0, $metrics->fundingRate);
    }

    public function test_zero_budget_is_safe_and_never_flagged_as_overfunded(): void
    {
        $project = Project::factory()->create(['budget_prevu' => 0]);
        Funding::factory()->municipal()->create(['project_id' => $project->id, 'montant' => 100]);

        $metrics = new ProjectMetrics($project);

        $this->assertSame(0.0, $metrics->fundingRate);
        $this->assertSame(0.0, $metrics->fundingGap);
        $this->assertFalse($metrics->overFunded);
        $this->assertSame(0.0, $metrics->overFunding);
    }

    public function test_project_without_phases_has_zero_costs_and_overdue_count(): void
    {
        $metrics = new ProjectMetrics(Project::factory()->create());

        $this->assertSame(0, $metrics->phaseCount);
        $this->assertSame(0.0, $metrics->phasesCostTotal);
        $this->assertSame(0, $metrics->overduePhaseCount);
    }

    public function test_overdue_phases_only_count_dated_incomplete_ones(): void
    {
        $project = Project::factory()->enCours()->create();

        ProjectPhase::factory()->create([
            'project_id' => $project->id,
            'date_fin' => now()->subDays(10),
            'avancement' => 40,
        ]);
        ProjectPhase::factory()->create([
            'project_id' => $project->id,
            'date_fin' => now()->subDays(5),
            'avancement' => 100, // terminée : pas en retard
        ]);
        ProjectPhase::factory()->create([
            'project_id' => $project->id,
            'date_fin' => now()->addMonth(),
            'avancement' => 10,
        ]);

        $metrics = new ProjectMetrics($project);

        $this->assertSame(3, $metrics->phaseCount);
        $this->assertSame(1, $metrics->overduePhaseCount);
        $this->assertSame(
            (float) $project->projectPhases()->sum('cout'),
            $metrics->phasesCostTotal,
        );
    }

    public function test_cancelled_project_phases_are_not_counted_as_overdue(): void
    {
        $project = Project::factory()->create(['statut' => 'annulé']);
        ProjectPhase::factory()->create([
            'project_id' => $project->id,
            'date_fin' => now()->subWeek(),
            'avancement' => 20,
        ]);

        $this->assertSame(0, (new ProjectMetrics($project))->overduePhaseCount);
    }

    public function test_deadline_status_depends_on_date_and_status(): void
    {
        $enRetard = Project::factory()->enCours()->create(['date_fin_prevue' => now()->subDay()]);
        $termine = Project::factory()->termine()->create(['date_fin_prevue' => now()->subMonth()]);
        $annule = Project::factory()->create(['statut' => 'annulé', 'date_fin_prevue' => now()->subMonth()]);
        $aVenir = Project::factory()->enCours()->create(['date_fin_prevue' => now()->addMonth()]);

        $this->assertSame(ProjectDeadlineIndicators::ECHEANCE_DEPASSEE, (new ProjectMetrics($enRetard))->deadlineStatus);
        $this->assertSame(ProjectDeadlineIndicators::ECHEANCE_A_JOUR, (new ProjectMetrics($termine))->deadlineStatus);
        $this->assertSame(ProjectDeadlineIndicators::ECHEANCE_A_JOUR, (new ProjectMetrics($annule))->deadlineStatus);
        $this->assertSame(ProjectDeadlineIndicators::ECHEANCE_A_JOUR, (new ProjectMetrics($aVenir))->deadlineStatus);
    }

    public function test_missing_deadline_is_handled_without_database_row(): void
    {
        $project = Project::factory()->make(['date_fin_prevue' => null]);

        $this->assertSame(ProjectDeadlineIndicators::ECHEANCE_AUCUNE, (new ProjectMetrics($project))->deadlineStatus);
    }

    public function test_back_office_show_displays_metrics_and_overfunding_warning(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        $project = Project::factory()->create(['budget_prevu' => 1000, 'titre' => 'Projet métriques']);
        Funding::factory()->municipal()->create(['project_id' => $project->id, 'montant' => 1500]);

        $this->actingAs($gestionnaire)
            ->get(route('back.projects.show', $project))
            ->assertOk()
            ->assertSee('Taux de financement')
            ->assertSee('Reste à financer')
            ->assertSee('Phases en retard')
            ->assertSee('Échéance')
            ->assertSee('Sur-financement détecté')
            ->assertSee('1 500,00');
    }

    public function test_front_show_displays_public_financials_only(): void
    {
        $project = Project::factory()->create(['budget_prevu' => 1000, 'titre' => 'Projet public']);
        Funding::factory()->municipal()->create(['project_id' => $project->id, 'montant' => 1500]);
        ProjectPhase::factory()->create([
            'project_id' => $project->id,
            'date_fin' => now()->subDay(),
            'avancement' => 10,
        ]);

        $this->get(route('public.projects.show', $project))
            ->assertOk()
            ->assertSee('Reste à financer')
            ->assertSee('Budget prévu')
            ->assertDontSee('Sur-financement')
            ->assertDontSee('Phases en retard');
    }
}
