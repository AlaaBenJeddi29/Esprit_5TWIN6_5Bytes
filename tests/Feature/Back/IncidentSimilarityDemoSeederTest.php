<?php

namespace Tests\Feature\Back;

use App\Models\Incident\Incident;
use App\Models\Incident\IncidentSimilarity;
use App\Services\IncidentSimilarityService;
use App\Services\SimilarityScorer;
use Database\Seeders\IncidentSimilarityDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IncidentSimilarityDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'incident_similarity.ai_enabled' => false,
            'services.groq.key' => null,
        ]);

        Http::preventStrayRequests();
    }

    public function test_repeated_seeding_is_idempotent_and_does_not_duplicate_suggestions(): void
    {
        $this->seed(IncidentSimilarityDemoSeeder::class);

        $firstIds = $this->demoIds();

        $this->seed(IncidentSimilarityDemoSeeder::class);

        $secondIds = $this->demoIds();

        $this->assertCount(16, $secondIds);
        $this->assertSame($firstIds, $secondIds);
        $this->assertSame(
            4,
            IncidentSimilarity::query()->where('status', IncidentSimilarity::STATUS_SUGGESTED)->count(),
        );
    }

    public function test_strong_demo_pairs_are_suggested_above_threshold(): void
    {
        $this->seed(IncidentSimilarityDemoSeeder::class);

        $expected = [
            ['a1', 'a2', 77],
            ['a1', 'a3', 72],
            ['b1', 'b2', 71],
            ['b1', 'b3', 70],
        ];

        foreach ($expected as [$sourceKey, $candidateKey, $expectedScore]) {
            $source = $this->demoIncident($sourceKey);
            $candidate = $this->demoIncident($candidateKey);

            $this->assertSame(
                $expectedScore,
                app(SimilarityScorer::class)->score($source, $candidate),
                "Score local inattendu pour {$source->reference} → {$candidate->reference}.",
            );

            $suggestion = IncidentSimilarity::query()
                ->where('incident_id', $source->id)
                ->where('similar_incident_id', $candidate->id)
                ->first();

            $this->assertNotNull($suggestion);
            $this->assertSame($expectedScore, $suggestion->score);
            $this->assertSame(IncidentSimilarity::STATUS_SUGGESTED, $suggestion->status);
            $this->assertSame(IncidentSimilarity::METHOD_LOCAL, $suggestion->method);
        }
    }

    public function test_unrelated_and_low_text_pairs_remain_below_threshold(): void
    {
        $this->seed(IncidentSimilarityDemoSeeder::class);

        $scorer = app(SimilarityScorer::class);
        $service = app(IncidentSimilarityService::class);
        $source = $this->demoIncident('a1');

        $included = $service->candidates($source)->pluck('id')->all();

        $pairs = [
            ['d1', 'd2', 40],
            ['a1', 'e1', 35],
            ['a1', 'e5', 29],
            ['a1', 'e6', 52],
        ];

        foreach ($pairs as [$sourceKey, $candidateKey, $expectedScore]) {
            $pairSource = $this->demoIncident($sourceKey);
            $candidate = $this->demoIncident($candidateKey);

            $this->assertContains($candidate->id, $service->candidates($pairSource)->pluck('id')->all());
            $this->assertSame($expectedScore, $scorer->score($pairSource, $candidate));
            $this->assertLessThan(
                (int) config('incident_similarity.min_score'),
                $expectedScore,
            );
            $this->assertDatabaseMissing('incident_similarities', [
                'incident_id' => $pairSource->id,
                'similar_incident_id' => $candidate->id,
            ]);
        }

        $this->assertContains($this->demoIncident('e1')->id, $included);
        $this->assertContains($this->demoIncident('e5')->id, $included);
        $this->assertContains($this->demoIncident('e6')->id, $included);
    }

    public function test_rejected_out_of_window_and_other_zone_candidates_are_excluded(): void
    {
        $this->seed(IncidentSimilarityDemoSeeder::class);

        $source = $this->demoIncident('a1');
        $candidateIds = app(IncidentSimilarityService::class)
            ->candidates($source)
            ->pluck('id')
            ->all();

        $this->assertNotContains($this->demoIncident('e2')->id, $candidateIds, 'Un incident rejeté ne doit pas être candidat.');
        $this->assertNotContains($this->demoIncident('e3')->id, $candidateIds, 'Un incident hors fenêtre ne doit pas être candidat.');
        $this->assertNotContains($this->demoIncident('e4')->id, $candidateIds, 'Un incident d’une autre zone ne doit pas être candidat.');

        foreach (['e2', 'e3', 'e4'] as $key) {
            $this->assertDatabaseMissing('incident_similarities', [
                'incident_id' => $source->id,
                'similar_incident_id' => $this->demoIncident($key)->id,
            ]);
        }
    }

    public function test_identical_text_in_another_zone_follows_the_sql_preselection(): void
    {
        $this->seed(IncidentSimilarityDemoSeeder::class);

        $source = $this->demoIncident('c1');
        $candidate = $this->demoIncident('c2');

        $this->assertNotSame($source->zone_id, $candidate->zone_id);
        $this->assertSame(78, app(SimilarityScorer::class)->score($source, $candidate));
        $this->assertNotContains(
            $candidate->id,
            app(IncidentSimilarityService::class)->candidates($source)->pluck('id')->all(),
        );
        $this->assertDatabaseMissing('incident_similarities', [
            'incident_id' => $source->id,
            'similar_incident_id' => $candidate->id,
        ]);
    }

    public function test_groq_cannot_change_the_local_score_or_the_suggestion_status(): void
    {
        $this->seed(IncidentSimilarityDemoSeeder::class);

        config([
            'incident_similarity.ai_enabled' => true,
            'services.groq.key' => 'test-key',
            'services.groq.url' => 'https://api.groq.com/openai/v1/chat/completions',
            'services.groq.model' => 'openai/gpt-oss-120b',
            'services.groq.timeout' => 5,
            'services.groq.delay_ms' => 0,
        ]);

        $source = $this->demoIncident('a1');
        $candidate = $this->demoIncident('a2');
        $expectedScore = app(SimilarityScorer::class)->score($source, $candidate);
        $sameIssue = true;

        Http::fake(function (Request $request) use (&$sameIssue) {
            $results = [];

            foreach ($this->candidateIdsFromRequest($request) as $candidateId) {
                $results[] = [
                    'candidate_id' => $candidateId,
                    'is_same_issue' => $sameIssue,
                    'confidence' => $sameIssue ? 100 : 0,
                    'justification' => $sameIssue ? 'Même fuite au même endroit.' : 'Problèmes différents.',
                ];
            }

            return Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode(['results' => $results], JSON_UNESCAPED_UNICODE)],
                    'finish_reason' => 'stop',
                ]],
            ]);
        });

        $positive = $this->suggestionFor($source, $candidate);

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI, $positive?->method);
        $this->assertSame($expectedScore, $positive?->score);
        $this->assertSame(IncidentSimilarity::STATUS_SUGGESTED, $positive?->status);
        $this->assertSame('nouveau', $source->fresh()->status);
        $this->assertFalse($source->fresh()->is_duplicate);

        $sameIssue = false;

        $negative = $this->suggestionFor($source, $candidate);

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI_NEGATIVE, $negative?->method);
        $this->assertSame($expectedScore, $negative?->score);
        $this->assertSame(IncidentSimilarity::STATUS_SUGGESTED, $negative?->status);
        $this->assertSame('nouveau', $source->fresh()->status);
    }

    public function test_seeder_works_without_ai_credentials_and_sends_nothing(): void
    {
        config(['incident_similarity.ai_enabled' => true]);
        Http::fake();

        $this->seed(IncidentSimilarityDemoSeeder::class);

        $suggestions = IncidentSimilarity::query()
            ->where('status', IncidentSimilarity::STATUS_SUGGESTED)
            ->get();

        $this->assertCount(4, $suggestions);
        $this->assertTrue($suggestions->every(fn (IncidentSimilarity $suggestion): bool => $suggestion->method === IncidentSimilarity::METHOD_LOCAL
            && $suggestion->ai_justification === null));

        Http::assertNothingSent();
    }

    public function test_seeder_preserves_existing_records(): void
    {
        $existing = Incident::factory()->create([
            'title' => 'Signalement réel à conserver',
            'status' => 'traite',
        ]);
        $other = Incident::factory()->create();

        $existingSimilarity = IncidentSimilarity::create([
            'incident_id' => $existing->id,
            'similar_incident_id' => $other->id,
            'score' => 91,
            'method' => IncidentSimilarity::METHOD_LOCAL,
            'status' => IncidentSimilarity::STATUS_DUPLICATE,
        ]);

        $this->seed(IncidentSimilarityDemoSeeder::class);

        $this->assertDatabaseHas('incidents', [
            'id' => $existing->id,
            'title' => 'Signalement réel à conserver',
            'status' => 'traite',
        ]);
        $this->assertDatabaseHas('incidents', ['id' => $other->id]);
        $this->assertDatabaseHas('incident_similarities', [
            'id' => $existingSimilarity->id,
            'status' => IncidentSimilarity::STATUS_DUPLICATE,
            'score' => 91,
        ]);
        $this->assertSame(16, Incident::query()->where('reference', 'like', 'INC-SIM-%')->count());
    }

    /**
     * @return array<string, int>
     */
    private function demoIds(): array
    {
        return Incident::query()
            ->where('reference', 'like', 'INC-SIM-%')
            ->orderBy('reference')
            ->pluck('id', 'reference')
            ->all();
    }

    private function demoIncident(string $key): Incident
    {
        return Incident::query()
            ->where('reference', IncidentSimilarityDemoSeeder::REFERENCES[$key])
            ->firstOrFail();
    }

    private function suggestionFor(Incident $source, Incident $candidate): ?IncidentSimilarity
    {
        app(IncidentSimilarityService::class)->detect($source->fresh());

        return IncidentSimilarity::query()
            ->where('incident_id', $source->id)
            ->where('similar_incident_id', $candidate->id)
            ->first();
    }

    /**
     * @return list<int>
     */
    private function candidateIdsFromRequest(Request $request): array
    {
        $prompt = (string) ($request->data()['messages'][1]['content'] ?? '');

        preg_match_all('/--- Candidat B \(identifiant interne (\d+)\) ---/u', $prompt, $matches);

        return array_map('intval', $matches[1]);
    }
}
