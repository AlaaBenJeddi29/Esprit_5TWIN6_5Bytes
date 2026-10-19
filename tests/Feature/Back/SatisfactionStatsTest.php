<?php

namespace Tests\Feature\Back;

use App\Models\Incident\Incident;
use App\Models\Incident\IncidentFeedback;
use App\Models\Infrastructure\Zone;
use App\Models\User;
use App\Services\SatisfactionStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SatisfactionStatsTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SatisfactionStatsService
    {
        return app(SatisfactionStatsService::class);
    }

    private function feedbackForIncident(
        Incident $incident,
        User $citizen,
        array $attributes = [],
        ?string $state = null,
        ?User $manager = null,
        bool $reopenRequested = false,
    ): IncidentFeedback {
        $factory = IncidentFeedback::factory()->complaint($reopenRequested);

        $factory = match ($state) {
            'acceptee' => $factory->acceptee($manager),
            'rejetee' => $factory->rejetee($manager),
            default => $factory,
        };

        return $factory->create(array_merge([
            'incident_id' => $incident->id,
            'user_id' => $citizen->id,
        ], $attributes));
    }

    /**
     * @return array{zone_a: Zone, zone_b: Zone}
     */
    private function seedMetrics(): array
    {
        $manager = User::factory()->gestionnaire()->create();
        $zoneA = Zone::factory()->create(['nom' => 'Zone Alpha']);
        $zoneB = Zone::factory()->create(['nom' => 'Zone Beta']);

        // Note 5, résolution confirmée, réclamation acceptée et rouverte.
        $citizen1 = User::factory()->citoyen()->create();
        $incident1 = Incident::factory()->traite()->create([
            'user_id' => $citizen1->id,
            'zone_id' => $zoneA->id,
        ]);
        $this->feedbackForIncident($incident1, $citizen1, [
            'rating' => 5,
            'resolution_confirmed' => true,
            'reopened_at' => now(),
        ], 'acceptee', $manager, true);

        // Note 3, réclamation refusée.
        $citizen2 = User::factory()->citoyen()->create();
        $incident2 = Incident::factory()->traite()->create([
            'user_id' => $citizen2->id,
            'zone_id' => $zoneB->id,
        ]);
        $this->feedbackForIncident($incident2, $citizen2, [
            'rating' => 3,
            'resolution_confirmed' => false,
        ], 'rejetee', $manager);

        // Note 1, réclamation encore en attente.
        $citizen3 = User::factory()->citoyen()->create();
        $incident3 = Incident::factory()->traite()->create([
            'user_id' => $citizen3->id,
            'zone_id' => $zoneA->id,
        ]);
        $this->feedbackForIncident($incident3, $citizen3, [
            'rating' => 1,
            'resolution_confirmed' => false,
        ]);

        // Note 4, évaluation sans réclamation.
        $citizen4 = User::factory()->citoyen()->create();
        $incident4 = Incident::factory()->traite()->create([
            'user_id' => $citizen4->id,
        ]);

        IncidentFeedback::factory()->create([
            'incident_id' => $incident4->id,
            'user_id' => $citizen4->id,
            'rating' => 4,
            'resolution_confirmed' => true,
        ]);

        return ['zone_a' => $zoneA, 'zone_b' => $zoneB];
    }

    public function test_summary_computes_satisfaction_and_complaint_metrics(): void
    {
        $this->seedMetrics();

        $summary = $this->service()->summary(
            now()->subDays(30)->toDateString(),
            now()->toDateString(),
        );

        $this->assertSame(4, $summary['total_feedback']);
        $this->assertSame(3.25, $summary['average_rating']);
        $this->assertSame([1 => 1, 2 => 0, 3 => 1, 4 => 1, 5 => 1], $summary['rating_distribution']);
        $this->assertSame(2, $summary['resolution_confirmed']);
        $this->assertSame(50.0, $summary['resolution_confirmation_rate']);
        $this->assertSame(3, $summary['complaints_total']);
        $this->assertSame(1, $summary['complaints_pending']);
        $this->assertSame(0, $summary['complaints_in_review']);
        $this->assertSame(1, $summary['complaints_accepted']);
        $this->assertSame(1, $summary['complaints_rejected']);
        $this->assertSame(50.0, $summary['acceptance_rate']);
        $this->assertSame(1, $summary['reopen_requested']);
        $this->assertSame(1, $summary['reopen_accepted']);
    }

    public function test_metrics_are_neutral_when_there_is_no_data(): void
    {
        $summary = $this->service()->summary(
            now()->subDays(30)->toDateString(),
            now()->toDateString(),
        );

        $this->assertSame(0, $summary['total_feedback']);
        $this->assertNull($summary['average_rating']);
        $this->assertSame([1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0], $summary['rating_distribution']);
        $this->assertNull($summary['resolution_confirmation_rate']);
        $this->assertSame(0, $summary['complaints_total']);
        $this->assertNull($summary['acceptance_rate']);
        $this->assertSame([], $summary['evolution']);
        $this->assertSame([], $summary['by_zone']);
    }

    public function test_period_filter_only_counts_feedback_submitted_inside_the_range(): void
    {
        $recentCitizen = User::factory()->citoyen()->create();
        $recentIncident = Incident::factory()->traite()->create(['user_id' => $recentCitizen->id]);
        IncidentFeedback::factory()->create([
            'incident_id' => $recentIncident->id,
            'user_id' => $recentCitizen->id,
            'rating' => 5,
        ]);

        $oldCitizen = User::factory()->citoyen()->create();
        $oldIncident = Incident::factory()->traite()->create(['user_id' => $oldCitizen->id]);
        $old = IncidentFeedback::factory()->create([
            'incident_id' => $oldIncident->id,
            'user_id' => $oldCitizen->id,
            'rating' => 1,
        ]);
        $old->created_at = now()->subDays(100);
        $old->save();

        $summary = $this->service()->summary(
            now()->subDays(30)->toDateString(),
            now()->toDateString(),
        );

        $this->assertSame(1, $summary['total_feedback']);
        $this->assertSame(5.0, $summary['average_rating']);
        $this->assertSame(1, $summary['rating_distribution'][5]);
        $this->assertSame(0, $summary['rating_distribution'][1]);
    }

    public function test_zone_breakdown_reports_total_average_and_complaints_per_zone(): void
    {
        ['zone_a' => $zoneA, 'zone_b' => $zoneB] = $this->seedMetrics();

        $summary = $this->service()->summary(
            now()->subDays(30)->toDateString(),
            now()->toDateString(),
        );

        $byZone = collect($summary['by_zone'])->keyBy('zone_id');

        $this->assertSame(2, $byZone[$zoneA->id]['total']);
        $this->assertSame(3.0, $byZone[$zoneA->id]['average_rating']);
        $this->assertSame(2, $byZone[$zoneA->id]['complaints']);

        $this->assertSame(1, $byZone[$zoneB->id]['total']);
        $this->assertSame(3.0, $byZone[$zoneB->id]['average_rating']);
        $this->assertSame(1, $byZone[$zoneB->id]['complaints']);
    }

    public function test_evolution_groups_feedback_by_month(): void
    {
        $currentCitizen = User::factory()->citoyen()->create();
        $currentIncident = Incident::factory()->traite()->create(['user_id' => $currentCitizen->id]);
        IncidentFeedback::factory()->create([
            'incident_id' => $currentIncident->id,
            'user_id' => $currentCitizen->id,
            'rating' => 5,
        ]);

        $oldCitizen = User::factory()->citoyen()->create();
        $oldIncident = Incident::factory()->traite()->create(['user_id' => $oldCitizen->id]);
        $old = IncidentFeedback::factory()->create([
            'incident_id' => $oldIncident->id,
            'user_id' => $oldCitizen->id,
            'rating' => 2,
        ]);
        $old->created_at = now()->subMonthNoOverflow();
        $old->save();

        $evolution = collect($this->service()->summary()['evolution']);

        $this->assertSame(2, $evolution->sum('total'));
        $this->assertSame(5.0, $evolution->firstWhere('period', now()->format('Y-m'))['average_rating']);
        $this->assertSame(2.0, $evolution->firstWhere('period', now()->subMonthNoOverflow()->format('Y-m'))['average_rating']);
    }

    public function test_manager_can_open_the_satisfaction_dashboard(): void
    {
        $manager = User::factory()->gestionnaire()->create();

        $this->actingAs($manager)
            ->get(route('back.complaints.stats'))
            ->assertOk()
            ->assertSee('Indicateurs de satisfaction')
            ->assertSee('Note moyenne')
            ->assertSee('Taux d\'acceptation');
    }

    public function test_custom_period_requires_both_boundary_dates(): void
    {
        $manager = User::factory()->gestionnaire()->create();

        $this->actingAs($manager)
            ->get(route('back.complaints.stats', ['period' => 'custom']))
            ->assertSessionHasErrors(['from', 'to']);
    }

    public function test_citizen_cannot_open_the_satisfaction_dashboard(): void
    {
        $citizen = User::factory()->citoyen()->create();

        $this->actingAs($citizen)
            ->get(route('back.complaints.stats'))
            ->assertForbidden();
    }
}
