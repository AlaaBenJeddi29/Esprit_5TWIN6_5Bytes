<?php

namespace Tests\Feature\Back;

use App\Models\Incident\Incident;
use App\Models\Incident\IncidentSimilarity;
use App\Models\User;
use App\Services\IncidentSimilarityService;
use App\Services\SimilarityScorer;
use Database\Seeders\IncidentAiDemoSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IncidentAiDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'incident_similarity.ai_enabled' => false,
            'incident_similarity.window_days' => 30,
            'incident_similarity.max_candidates' => 50,
            'incident_similarity.min_score' => 60,
            'incident_similarity.max_suggestions' => 5,
            'incident_similarity.recurring_threshold' => 3,
            'services.groq.key' => null,
        ]);

        Http::preventStrayRequests();

        User::factory()->citoyen()->create();
        User::factory()->gestionnaire()->create();
    }

    public function test_repeated_seeding_is_idempotent_and_creates_the_expected_groups(): void
    {
        $this->seed(IncidentAiDemoSeeder::class);

        $firstIds = $this->demoIds();

        $this->seed(IncidentAiDemoSeeder::class);

        $this->assertSame($firstIds, $this->demoIds());
        $this->assertCount(30, $firstIds);

        $this->assertSame(3, $this->groupCount('A'));
        $this->assertSame(1, $this->groupCount('B'));
        $this->assertSame(3, $this->groupCount('C'));
        $this->assertSame(4, $this->groupCount('D'));
        $this->assertSame(7, $this->groupCount('E'));
        $this->assertSame(7, $this->groupCount('F'));
        $this->assertSame(5, $this->groupCount('G'));
    }

    public function test_all_demo_incidents_use_valid_values_and_coherent_status_history(): void
    {
        $this->seed(IncidentAiDemoSeeder::class);

        $incidents = $this->demoIncidents();

        foreach ($incidents as $incident) {
            $this->assertArrayHasKey($incident->type, Incident::TYPES);
            $this->assertArrayHasKey($incident->severity, Incident::SEVERITIES);
            $this->assertArrayHasKey($incident->status, Incident::STATUSES);
            $this->assertArrayHasKey($incident->priority, Incident::PRIORITIES);
            $this->assertNotNull($incident->reported_at);
            $this->assertNotNull($incident->created_at);
            $this->assertTrue($incident->created_at->equalTo($incident->reported_at));
            $this->assertNull($incident->ai_category);
            $this->assertNull($incident->ai_confidence);
            $this->assertNull($incident->ai_summary);

            if ($incident->status === 'nouveau') {
                continue;
            }

            $histories = $incident->statusHistories()->get();

            $this->assertNotEmpty($histories, "Historique manquant pour {$incident->reference}.");
            $this->assertSame(
                $incident->status,
                $histories->last()->nouveau_statut,
                "Dernier statut historique incohérent pour {$incident->reference}.",
            );

            foreach ($histories as $history) {
                $this->assertTrue(
                    $history->created_at->greaterThanOrEqualTo($incident->reported_at),
                    "Historique antérieur au signalement pour {$incident->reference}.",
                );
            }
        }

        $this->assertEqualsCanonicalizing(
            array_keys(Incident::STATUSES),
            $incidents->pluck('status')->unique()->values()->all(),
        );
    }

    public function test_group_e_edge_cases_are_present(): void
    {
        $this->seed(IncidentAiDemoSeeder::class);

        $a1 = $this->incident('A-01');
        $e1 = $this->incident('E-01');
        $e2 = $this->incident('E-02');
        $e3 = $this->incident('E-03');
        $e4 = $this->incident('E-04');
        $e5 = $this->incident('E-05');
        $e6 = $this->incident('E-06');
        $e7 = $this->incident('E-07');

        $this->assertSame('rejete', $e1->status);
        $this->assertNotNull($e1->rejection_reason);

        $this->assertTrue($e2->reported_at->lessThanOrEqualTo(now()->subDays(60)));

        $this->assertTrue($e3->is_duplicate);
        $this->assertSame($a1->id, $e3->duplicate_of_id);

        $this->assertNull($e4->zone_id);
        $this->assertNull($e4->infrastructure_id);
        $this->assertNull($e4->latitude);
        $this->assertNull($e4->longitude);

        $this->assertLessThan(20, mb_strlen($e5->description));
        $this->assertLessThan(20, mb_strlen($e6->description));
        $this->assertGreaterThan(500, mb_strlen($e7->description));
    }

    public function test_no_similarity_or_ai_results_are_seeded(): void
    {
        $this->seed(IncidentAiDemoSeeder::class);

        $this->assertSame(0, IncidentSimilarity::query()->count());
        $this->assertSame(0, Incident::query()
            ->where('reference', 'like', IncidentAiDemoSeeder::REFERENCE_PREFIX.'%')
            ->where(function ($query) {
                $query->whereNotNull('ai_category')
                    ->orWhereNotNull('ai_confidence')
                    ->orWhereNotNull('ai_summary');
            })
            ->count());
    }

    public function test_group_a_scores_above_threshold_and_group_c_below(): void
    {
        $this->seed(IncidentAiDemoSeeder::class);

        $scorer = app(SimilarityScorer::class);
        $minimum = (int) config('incident_similarity.min_score');

        $a1 = $this->incident('A-01');
        $a2 = $this->incident('A-02');
        $a3 = $this->incident('A-03');

        $this->assertGreaterThanOrEqual($minimum, $scorer->score($a1, $a2));
        $this->assertGreaterThanOrEqual($minimum, $scorer->score($a1, $a3));

        foreach (['C-01', 'C-02', 'C-03'] as $reference) {
            $this->assertLessThan($minimum, $scorer->score($a1, $this->incident($reference)));
        }
    }

    public function test_group_b_is_excluded_by_zone_and_group_e_by_status_and_window(): void
    {
        $this->seed(IncidentAiDemoSeeder::class);

        $service = app(IncidentSimilarityService::class);
        $a1 = $this->incident('A-01');
        $candidateIds = $service->candidates($a1)->pluck('id')->all();

        $this->assertContains($this->incident('A-02')->id, $candidateIds);
        $this->assertContains($this->incident('A-03')->id, $candidateIds);
        $this->assertContains($this->incident('E-03')->id, $candidateIds);

        $this->assertNotContains($this->incident('B-01')->id, $candidateIds);
        $this->assertNotContains($this->incident('E-01')->id, $candidateIds);
        $this->assertNotContains($this->incident('E-02')->id, $candidateIds);
    }

    public function test_group_d_produces_three_similarity_candidates_above_threshold(): void
    {
        $this->seed(IncidentAiDemoSeeder::class);

        $service = app(IncidentSimilarityService::class);
        $scorer = app(SimilarityScorer::class);
        $minimum = (int) config('incident_similarity.min_score');
        $d1 = $this->incident('D-01');

        $candidates = $service->candidates($d1)
            ->filter(fn (Incident $incident) => str_starts_with($incident->reference, IncidentAiDemoSeeder::REFERENCE_PREFIX.'D-'))
            ->values();

        $this->assertCount(3, $candidates);

        foreach ($candidates as $candidate) {
            $this->assertGreaterThanOrEqual($minimum, $scorer->score($d1, $candidate));
        }

        $this->assertSame(3, (int) config('incident_similarity.recurring_threshold'));
    }

    public function test_group_d_recurring_signal_triggers_after_local_detection(): void
    {
        $this->seed(IncidentAiDemoSeeder::class);

        $service = app(IncidentSimilarityService::class);
        $d1 = $this->incident('D-01');

        $created = $service->detect($d1);
        $recurring = $service->recurringProblem($d1);

        $this->assertCount(3, $created);
        $this->assertSame(3, $recurring['count']);
        $this->assertSame(3, $recurring['threshold']);
        $this->assertTrue($recurring['is_recurring']);
        $this->assertSame(
            0,
            IncidentSimilarity::query()->where('method', '!=', IncidentSimilarity::METHOD_LOCAL)->count(),
        );
    }

    /**
     * @return array<int, int>
     */
    private function demoIds(): array
    {
        return $this->demoIncidents()->pluck('id')->all();
    }

    /**
     * @return Collection<int, Incident>
     */
    private function demoIncidents()
    {
        return Incident::query()
            ->where('reference', 'like', IncidentAiDemoSeeder::REFERENCE_PREFIX.'%')
            ->orderBy('reference')
            ->get();
    }

    private function groupCount(string $group): int
    {
        return Incident::query()
            ->where('reference', 'like', IncidentAiDemoSeeder::REFERENCE_PREFIX.$group.'-%')
            ->count();
    }

    private function incident(string $key): Incident
    {
        return Incident::query()
            ->where('reference', IncidentAiDemoSeeder::REFERENCE_PREFIX.$key)
            ->firstOrFail();
    }
}
