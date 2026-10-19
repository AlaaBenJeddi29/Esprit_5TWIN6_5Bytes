<?php

namespace Tests\Feature\Back;

use App\Models\Incident\Incident;
use App\Models\Incident\IncidentSimilarity;
use App\Models\Infrastructure\Zone;
use App\Models\User;
use App\Services\IncidentSimilarityService;
use App\Services\SimilarityScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IncidentSimilarityAiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'incident_similarity.ai_enabled' => true,
            'services.xai.api_key' => 'test-key',
            'services.xai.url' => 'https://api.x.ai/v1/chat/completions',
            'services.xai.model' => 'grok-4.7',
            'services.xai.timeout' => 5,
        ]);
    }

    public function test_a_valid_ai_response_annotates_the_suggestion(): void
    {
        $this->fakeAiContent(fn (int $candidateId) => $this->verdict(
            $candidateId,
            true,
            91,
            'Même fuite au même endroit.',
        ));

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestions = app(IncidentSimilarityService::class)->detect($source);
        $suggestion = $suggestions->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI, $suggestion->method);
        $this->assertStringContainsString('IA : même problème probable', (string) $suggestion->ai_justification);
        $this->assertStringContainsString('91/100', (string) $suggestion->ai_justification);
        $this->assertStringContainsString('non calibré', (string) $suggestion->ai_justification);
        $this->assertStringContainsString('Même fuite au même endroit.', (string) $suggestion->ai_justification);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $data = $request->data();
            $prompt = (string) ($data['messages'][1]['content'] ?? '');

            return ($data['model'] ?? null) === 'grok-4.7'
                && ($data['response_format']['type'] ?? null) === 'json_object'
                && str_contains($prompt, 'Signalement source A')
                && str_contains($prompt, 'Signalement candidat B');
        });
    }

    public function test_a_negative_ai_verdict_is_stored_with_its_own_method(): void
    {
        $this->fakeAiContent(fn (int $candidateId) => $this->verdict(
            $candidateId,
            false,
            42,
            'Problèmes différents.',
        ));

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI_NEGATIVE, $suggestion->method);
        $this->assertStringContainsString('IA : problème probablement différent', (string) $suggestion->ai_justification);
        $this->assertStringContainsString('42/100', (string) $suggestion->ai_justification);
        $this->assertSame(IncidentSimilarity::STATUS_SUGGESTED, $suggestion->status);
    }

    public function test_markdown_fenced_json_is_accepted(): void
    {
        $this->fakeAiContent(fn (int $candidateId) => "```json\n"
            .$this->verdict($candidateId, true, 77, 'Même problème constaté.')
            ."\n```");

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI, $suggestion->method);
        $this->assertStringContainsString('77/100', (string) $suggestion->ai_justification);
    }

    public function test_json_surrounded_by_text_is_accepted(): void
    {
        $this->fakeAiContent(fn (int $candidateId) => "Voici mon analyse :\n"
            .$this->verdict($candidateId, false, 42, 'Problèmes différents.')
            ."\nFin de la réponse.");

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI_NEGATIVE, $suggestion->method);
        $this->assertStringContainsString('IA : problème probablement différent', (string) $suggestion->ai_justification);
    }

    public function test_an_ai_response_for_another_candidate_is_rejected(): void
    {
        $this->fakeAiContent(fn () => $this->verdict(999999, true, 95, 'Réponse associée au mauvais candidat.'));

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE, $suggestion->method);
        $this->assertStringContainsString('Analyse IA indisponible', (string) $suggestion->ai_justification);
        $this->assertSame(IncidentSimilarity::STATUS_SUGGESTED, $suggestion->status);
    }

    public function test_ai_is_only_called_for_the_five_highest_scoring_suggestions(): void
    {
        config([
            'incident_similarity.max_suggestions' => 7,
            'incident_similarity.ai_max_candidates' => 5,
        ]);

        $this->fakeAiContent(fn (int $candidateId) => $this->verdict(
            $candidateId,
            true,
            80,
            'Signalements proches.',
        ));

        $source = $this->incident();

        $highIds = [];
        for ($index = 0; $index < 5; $index++) {
            $highIds[] = $this->incident(['zone_id' => $source->zone_id])->id;
        }

        $lowIds = [];
        for ($index = 0; $index < 2; $index++) {
            $lowIds[] = $this->incident([
                'zone_id' => $source->zone_id,
                'title' => "Fuite d'eau rue commerçante",
                'description' => 'Fuite eau chaussée devant rue commerçante.',
            ])->id;
        }

        $suggestions = app(IncidentSimilarityService::class)->detect($source);

        $this->assertCount(7, $suggestions);
        Http::assertSentCount(5);

        $annotatedIds = $suggestions
            ->where('method', IncidentSimilarity::METHOD_LOCAL_AI)
            ->pluck('similar_incident_id')
            ->sort()
            ->values()
            ->all();

        $localIds = $suggestions
            ->where('method', IncidentSimilarity::METHOD_LOCAL)
            ->pluck('similar_incident_id')
            ->sort()
            ->values()
            ->all();

        sort($highIds);
        sort($lowIds);

        $this->assertSame($highIds, $annotatedIds);
        $this->assertSame($lowIds, $localIds);
    }

    public function test_ai_confidence_cannot_change_the_local_score_or_the_statuses(): void
    {
        $this->fakeAiContent(fn (int $candidateId) => $this->verdict(
            $candidateId,
            true,
            100,
            'Le modèle est certain.',
        ));

        $source = $this->incident();
        $candidate = $this->incident(['zone_id' => $source->zone_id]);

        $expectedScore = app(SimilarityScorer::class)->score($source, $candidate);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame($expectedScore, $suggestion->score);
        $this->assertSame(IncidentSimilarity::STATUS_SUGGESTED, $suggestion->status);
        $this->assertSame('nouveau', $source->fresh()->status);
        $this->assertFalse($source->fresh()->is_duplicate);
        $this->assertFalse($candidate->fresh()->is_duplicate);
    }

    public function test_a_rate_limited_ai_response_keeps_the_local_suggestion(): void
    {
        Http::fake([
            'api.x.ai/*' => Http::response('Too many requests', 429),
        ]);

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE, $suggestion->method);
        $this->assertStringContainsString('Analyse IA indisponible', (string) $suggestion->ai_justification);
        $this->assertSame(IncidentSimilarity::STATUS_SUGGESTED, $suggestion->status);
    }

    public function test_only_descriptions_are_sent_to_the_ai_provider(): void
    {
        $this->fakeAiContent(fn (int $candidateId) => $this->verdict(
            $candidateId,
            true,
            80,
            'Signalements proches.',
        ));

        $reporter = User::factory()->citoyen()->create([
            'name' => 'Jean Dupont',
            'email' => 'jean.dupont@example.com',
        ]);

        $source = $this->incident(['user_id' => $reporter->id]);
        $candidate = $this->incident(['user_id' => $reporter->id, 'zone_id' => $source->zone_id]);

        app(IncidentSimilarityService::class)->detect($source);

        Http::assertSent(function (Request $request) use ($candidate): bool {
            $body = json_encode($request->data(), JSON_UNESCAPED_UNICODE);
            $prompt = (string) ($request->data()['messages'][1]['content'] ?? '');

            return ! str_contains($body, 'Jean Dupont')
                && ! str_contains($body, 'jean.dupont@example.com')
                && str_contains($prompt, 'identifiant interne '.$candidate->id);
        });
    }

    public function test_invalid_json_keeps_the_local_suggestion(): void
    {
        Http::fake([
            'api.x.ai/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => 'Réponse non JSON.'],
                ]],
            ]),
        ]);

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE, $suggestion->method);
        $this->assertSame(IncidentSimilarity::STATUS_SUGGESTED, $suggestion->status);
    }

    public function test_an_invalid_ai_payload_keeps_the_local_suggestion(): void
    {
        $this->fakeAiContent(fn (int $candidateId) => json_encode([
            'candidate_id' => $candidateId,
            'is_same_issue' => 'oui',
            'confidence' => 140,
            'justification' => '',
        ]));

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE, $suggestion->method);
        $this->assertSame(IncidentSimilarity::STATUS_SUGGESTED, $suggestion->status);
    }

    public function test_an_ai_timeout_keeps_the_local_suggestion(): void
    {
        Http::fake(fn () => throw new ConnectionException('Timeout simulé.'));

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE, $suggestion->method);
        $this->assertStringContainsString('Analyse IA indisponible', (string) $suggestion->ai_justification);
    }

    public function test_an_ai_error_status_keeps_the_local_suggestion(): void
    {
        Http::fake([
            'api.x.ai/*' => Http::response('Service unavailable', 503),
        ]);

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE, $suggestion->method);
        $this->assertSame(IncidentSimilarity::STATUS_SUGGESTED, $suggestion->status);
    }

    public function test_invalid_credentials_keep_the_local_suggestion(): void
    {
        Http::fake([
            'api.x.ai/*' => Http::response('Unauthorized', 401),
        ]);

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE, $suggestion->method);
        Http::assertSentCount(1);
    }

    public function test_ai_is_not_called_when_disabled(): void
    {
        config(['incident_similarity.ai_enabled' => false]);
        Http::fake();

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL, $suggestion->method);
        $this->assertNull($suggestion->ai_justification);
        Http::assertNothingSent();
    }

    public function test_ai_is_not_called_without_an_api_key(): void
    {
        config(['services.xai.api_key' => null]);
        Http::fake();

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL, $suggestion->method);
        $this->assertNull($suggestion->ai_justification);
        Http::assertNothingSent();
    }

    private function fakeAiContent(callable $content): void
    {
        Http::fake(function (Request $request) use ($content) {
            $candidateId = $this->candidateIdFromRequest($request);

            return Http::response([
                'choices' => [[
                    'message' => ['content' => $content($candidateId)],
                ]],
            ]);
        });
    }

    private function candidateIdFromRequest(Request $request): int
    {
        $messages = $request->data()['messages'] ?? [];
        $prompt = (string) ($messages[1]['content'] ?? '');

        preg_match('/Signalement candidat B \(identifiant interne (\d+)\)/u', $prompt, $matches);

        return (int) ($matches[1] ?? 0);
    }

    private function verdict(int $candidateId, bool $sameIssue, int $confidence, string $justification): string
    {
        return json_encode([
            'candidate_id' => $candidateId,
            'is_same_issue' => $sameIssue,
            'confidence' => $confidence,
            'justification' => $justification,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function incident(array $overrides = []): Incident
    {
        return Incident::factory()->create(array_merge([
            'zone_id' => Zone::factory(),
            'type' => 'fuite',
            'title' => "Fuite d'eau sur la chaussée devant l'école",
            'description' => "Une fuite importante d'eau potable inonde la chaussée devant l'école depuis ce matin.",
            'severity' => 'haute',
            'status' => 'nouveau',
            'priority' => 'haute',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'reported_at' => now(),
        ], $overrides));
    }
}
