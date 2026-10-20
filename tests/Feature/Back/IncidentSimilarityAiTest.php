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
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class IncidentSimilarityAiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'incident_similarity.ai_enabled' => true,
            'services.groq.key' => 'test-key',
            'services.groq.url' => 'https://api.groq.com/openai/v1/chat/completions',
            'services.groq.model' => 'openai/gpt-oss-120b',
            'services.groq.timeout' => 5,
            'services.groq.connect_timeout' => 2,
            'services.groq.max_completion_tokens' => 400,
            'services.groq.reasoning_effort' => 'low',
            'services.groq.delay_ms' => 0,
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

            return ($data['model'] ?? null) === 'openai/gpt-oss-120b'
                && str_contains($request->url(), 'api.groq.com')
                && ($data['response_format']['type'] ?? null) === 'json_object'
                && ($data['reasoning_effort'] ?? null) === 'low'
                && ($data['max_completion_tokens'] ?? null) === 400
                && str_contains($prompt, 'Signalement source A')
                && str_contains($prompt, 'Candidat B (identifiant interne');
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
        Http::fake(function (Request $request) {
            $candidateId = $this->candidateIdsFromRequest($request)[0];
            $content = "```json\n".$this->batchJson([
                $this->verdictPayload($candidateId, true, 77, 'Même problème constaté.'),
            ])."\n```";

            return Http::response($this->completion($content));
        });

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI, $suggestion->method);
        $this->assertStringContainsString('77/100', (string) $suggestion->ai_justification);
    }

    public function test_json_surrounded_by_text_is_accepted(): void
    {
        Http::fake(function (Request $request) {
            $candidateId = $this->candidateIdsFromRequest($request)[0];
            $content = "Voici mon analyse :\n"
                .$this->batchJson([
                    $this->verdictPayload($candidateId, false, 42, 'Problèmes différents.'),
                ])
                ."\nFin de la réponse.";

            return Http::response($this->completion($content));
        });

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
        $this->assertStringContainsString('(réponse invalide)', (string) $suggestion->ai_justification);
        $this->assertSame(IncidentSimilarity::STATUS_SUGGESTED, $suggestion->status);
    }

    public function test_ai_is_only_called_for_the_five_highest_scoring_suggestions_in_one_request(): void
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
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => count($this->candidateIdsFromRequest($request)) === 5);

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

    public function test_ai_failure_cannot_change_the_local_score(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response('Too many requests', 429, ['Retry-After' => '0']),
        ]);

        $source = $this->incident();
        $candidate = $this->incident(['zone_id' => $source->zone_id]);

        $expectedScore = app(SimilarityScorer::class)->score($source, $candidate);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame($expectedScore, $suggestion->score);
        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE, $suggestion->method);
        $this->assertSame('nouveau', $source->fresh()->status);
    }

    public function test_the_ai_key_is_never_logged(): void
    {
        Log::spy();
        Http::fake([
            'api.groq.com/*' => Http::response('Too many requests', 429, ['Retry-After' => '0']),
        ]);

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        app(IncidentSimilarityService::class)->detect($source);

        Log::shouldNotHaveReceived('warning', function (string $message, array $context): bool {
            return str_contains(json_encode($context, JSON_UNESCAPED_UNICODE) ?: '', 'test-key');
        });
    }

    public function test_a_rate_limit_honours_retry_after_and_then_succeeds(): void
    {
        $calls = 0;

        Http::fake(function (Request $request) use (&$calls) {
            $calls++;

            if ($calls === 1) {
                return Http::response('Too many requests', 429, ['Retry-After' => '0']);
            }

            $candidateId = $this->candidateIdsFromRequest($request)[0];

            return Http::response($this->completion($this->batchJson([
                $this->verdictPayload($candidateId, true, 88, 'Même problème.'),
            ])));
        });

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI, $suggestion->method);
        Http::assertSentCount(2);
    }

    public function test_a_repeated_rate_limit_retries_once_then_falls_back(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response('Too many requests', 429, ['Retry-After' => '0']),
        ]);

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE, $suggestion->method);
        $this->assertStringContainsString('(limite de débit)', (string) $suggestion->ai_justification);
        Http::assertSentCount(2);
    }

    public function test_an_empty_response_is_retried_with_more_tokens_then_falls_back(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response($this->completion('')),
        ]);

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE, $suggestion->method);
        $this->assertStringContainsString('(réponse vide)', (string) $suggestion->ai_justification);

        $recorded = Http::recorded();
        $this->assertCount(2, $recorded);
        $this->assertGreaterThan(
            $recorded[0][0]->data()['max_completion_tokens'],
            $recorded[1][0]->data()['max_completion_tokens'],
        );
    }

    public function test_a_truncated_response_is_retried_with_more_tokens_then_succeeds(): void
    {
        $calls = 0;

        Http::fake(function (Request $request) use (&$calls) {
            $calls++;

            if ($calls === 1) {
                return Http::response($this->completion('{"results": [', 'length'));
            }

            $candidateId = $this->candidateIdsFromRequest($request)[0];

            return Http::response($this->completion($this->batchJson([
                $this->verdictPayload($candidateId, true, 74, 'Même problème.'),
            ])));
        });

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI, $suggestion->method);

        $recorded = Http::recorded();
        $this->assertCount(2, $recorded);
        $this->assertGreaterThan(
            $recorded[0][0]->data()['max_completion_tokens'],
            $recorded[1][0]->data()['max_completion_tokens'],
        );
    }

    public function test_a_response_format_rejection_is_retried_without_the_parameter(): void
    {
        $calls = 0;

        Http::fake(function (Request $request) use (&$calls) {
            $calls++;

            if ($calls === 1) {
                return Http::response([
                    'error' => ['message' => 'Failed to generate JSON. Please adjust your prompt.'],
                ], 400);
            }

            $candidateId = $this->candidateIdsFromRequest($request)[0];

            return Http::response($this->completion($this->batchJson([
                $this->verdictPayload($candidateId, true, 83, 'Même problème.'),
            ])));
        });

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI, $suggestion->method);

        $recorded = Http::recorded();
        $this->assertCount(2, $recorded);
        $this->assertArrayHasKey('response_format', $recorded[0][0]->data());
        $this->assertArrayNotHasKey('response_format', $recorded[1][0]->data());
        $this->assertStringContainsString(
            'uniquement un objet JSON valide',
            (string) ($recorded[1][0]->data()['messages'][0]['content'] ?? ''),
        );
    }

    public function test_a_partial_batch_keeps_valid_items_and_falls_back_for_the_others(): void
    {
        $source = $this->incident();
        $first = $this->incident(['zone_id' => $source->zone_id]);
        $second = $this->incident(['zone_id' => $source->zone_id]);

        Http::fake(function () use ($first) {
            return Http::response($this->completion($this->batchJson([
                $this->verdictPayload($first->id, true, 90, 'Même problème.'),
            ])));
        });

        $suggestions = app(IncidentSimilarityService::class)->detect($source);

        $this->assertCount(2, $suggestions);
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($first, $second): bool {
            $candidateIds = $this->candidateIdsFromRequest($request);

            return count($candidateIds) === 2
                && in_array($first->id, $candidateIds, true)
                && in_array($second->id, $candidateIds, true);
        });

        $confirmed = $suggestions->firstWhere('similar_incident_id', $first->id);
        $fallback = $suggestions->firstWhere('similar_incident_id', $second->id);

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI, $confirmed?->method);
        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE, $fallback?->method);
        $this->assertStringContainsString('(réponse invalide)', (string) $fallback?->ai_justification);
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
            'api.groq.com/*' => Http::response($this->completion('Réponse non JSON.')),
        ]);

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE, $suggestion->method);
        $this->assertStringContainsString('(réponse invalide)', (string) $suggestion->ai_justification);
        $this->assertSame(IncidentSimilarity::STATUS_SUGGESTED, $suggestion->status);
        Http::assertSentCount(1);
    }

    public function test_an_invalid_ai_payload_keeps_the_local_suggestion(): void
    {
        Http::fake(function (Request $request) {
            $candidateId = $this->candidateIdsFromRequest($request)[0];

            return Http::response($this->completion($this->batchJson([[
                'candidate_id' => $candidateId,
                'is_same_issue' => 'oui',
                'confidence' => 140,
                'justification' => '',
            ]])));
        });

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE, $suggestion->method);
        $this->assertSame(IncidentSimilarity::STATUS_SUGGESTED, $suggestion->status);
        $this->assertStringContainsString('(réponse invalide)', (string) $suggestion->ai_justification);
    }

    public function test_an_ai_timeout_keeps_the_local_suggestion(): void
    {
        Http::fake(fn () => throw new ConnectionException('Timeout simulé.'));

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE, $suggestion->method);
        $this->assertStringContainsString('(délai dépassé)', (string) $suggestion->ai_justification);
    }

    public function test_an_ai_error_status_keeps_the_local_suggestion(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response('Service unavailable', 503),
        ]);

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE, $suggestion->method);
        $this->assertSame(IncidentSimilarity::STATUS_SUGGESTED, $suggestion->status);
        Http::assertSentCount(1);
    }

    public function test_invalid_credentials_keep_the_local_suggestion(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response('Unauthorized', 401),
        ]);

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE, $suggestion->method);
        Http::assertSentCount(1);
    }

    public function test_a_forbidden_response_keeps_the_local_suggestion(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response('Forbidden', 403),
        ]);

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE, $suggestion->method);
        Http::assertSentCount(1);
    }

    public function test_a_server_error_keeps_the_local_suggestion(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response('Internal server error', 500),
        ]);

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE, $suggestion->method);
        $this->assertSame(IncidentSimilarity::STATUS_SUGGESTED, $suggestion->status);
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
        config(['services.groq.key' => null]);
        Http::fake();

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL, $suggestion->method);
        $this->assertNull($suggestion->ai_justification);
        Http::assertNothingSent();
    }

    public function test_a_missing_api_key_logs_a_warning_and_falls_back_without_throwing(): void
    {
        Log::spy();
        config(['services.groq.key' => '   ']);
        Http::fake();

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL, $suggestion->method);
        $this->assertNull($suggestion->ai_justification);
        Http::assertNothingSent();
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'Incident similarity AI key is missing or empty.'
                && ($context['provider'] ?? null) === 'groq'
                && ! array_key_exists('key', $context))
            ->once();
    }

    public function test_provider_errors_are_logged_with_status_reason_and_provider_message(): void
    {
        Log::spy();
        Http::fake([
            'api.groq.com/*' => Http::response([
                'error' => ['message' => 'Invalid API Key'],
            ], 401),
        ]);

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        app(IncidentSimilarityService::class)->detect($source);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'Incident similarity AI request failed.'
                && ($context['status'] ?? null) === 401
                && ($context['reason'] ?? null) === 'http_error'
                && ($context['provider_message'] ?? null) === 'Invalid API Key'
                && isset($context['duration_ms']))
            ->once();
    }

    public function test_requests_target_the_groq_endpoint_and_the_configured_model(): void
    {
        $this->fakeAiContent(fn (int $candidateId) => $this->verdict(
            $candidateId,
            true,
            80,
            'Même problème.',
        ));

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        app(IncidentSimilarityService::class)->detect($source);

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();
            $systemPrompt = (string) ($data['messages'][0]['content'] ?? '');

            return str_contains($request->url(), 'api.groq.com')
                && parse_url($request->url(), PHP_URL_PATH) === '/openai/v1/chat/completions'
                && ($data['model'] ?? null) === config('services.groq.model')
                && str_contains($systemPrompt, 'JSON');
        });
    }

    public function test_a_404_keeps_the_local_suggestion(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response('Not found', 404),
        ]);

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE, $suggestion->method);
        $this->assertSame(IncidentSimilarity::STATUS_SUGGESTED, $suggestion->status);
        Http::assertSentCount(1);
    }

    public function test_an_out_of_enum_suggested_classification_keeps_the_local_suggestion(): void
    {
        $this->fakeAiContent(fn (int $candidateId) => $this->verdict(
            $candidateId,
            true,
            90,
            'Même problème.',
            'inondation',
            'extreme',
        ));

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE, $suggestion->method);
        $this->assertStringContainsString('(réponse invalide)', (string) $suggestion->ai_justification);
        $this->assertSame('nouveau', $source->fresh()->status);
    }

    public function test_valid_classification_proposals_are_advisory_only(): void
    {
        $this->fakeAiContent(fn (int $candidateId) => $this->verdict(
            $candidateId,
            true,
            88,
            'Même problème constaté.',
            'coupure',
            'critique',
        ));

        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $suggestion = app(IncidentSimilarityService::class)->detect($source)->firstOrFail();

        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI, $suggestion->method);
        $this->assertStringContainsString('Proposition indicative (non appliquée)', (string) $suggestion->ai_justification);
        $this->assertStringContainsString('Coupure', (string) $suggestion->ai_justification);
        $this->assertStringContainsString('Critique', (string) $suggestion->ai_justification);
        $this->assertSame('fuite', $source->fresh()->type);
        $this->assertSame('haute', $source->fresh()->severity);
    }

    private function fakeAiContent(callable $content): void
    {
        Http::fake(function (Request $request) use ($content) {
            $items = [];

            foreach ($this->candidateIdsFromRequest($request) as $candidateId) {
                $decoded = json_decode((string) $content($candidateId), true);

                if (is_array($decoded)) {
                    $items[] = $decoded;
                }
            }

            return Http::response($this->completion($this->batchJson($items)));
        });
    }

    /**
     * @return list<int>
     */
    private function candidateIdsFromRequest(Request $request): array
    {
        $messages = $request->data()['messages'] ?? [];
        $prompt = (string) ($messages[1]['content'] ?? '');

        preg_match_all('/--- Candidat B \(identifiant interne (\d+)\) ---/u', $prompt, $matches);

        return array_map('intval', $matches[1]);
    }

    /**
     * @param  array<string, mixed>  $verdict
     */
    private function batchJson(array $verdict): string
    {
        return json_encode(['results' => $verdict], JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    /**
     * @return array<string, mixed>
     */
    private function completion(string $content, string $finishReason = 'stop'): array
    {
        return [
            'choices' => [[
                'message' => ['content' => $content],
                'finish_reason' => $finishReason,
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function verdictPayload(
        int $candidateId,
        bool $sameIssue,
        int $confidence,
        string $justification,
        ?string $suggestedType = null,
        ?string $suggestedSeverity = null,
    ): array {
        $payload = [
            'candidate_id' => $candidateId,
            'is_same_issue' => $sameIssue,
            'confidence' => $confidence,
            'justification' => $justification,
        ];

        if ($suggestedType !== null || $suggestedSeverity !== null) {
            $payload['suggested_type'] = $suggestedType;
            $payload['suggested_severity'] = $suggestedSeverity;
        }

        return $payload;
    }

    private function verdict(
        int $candidateId,
        bool $sameIssue,
        int $confidence,
        string $justification,
        ?string $suggestedType = null,
        ?string $suggestedSeverity = null,
    ): string {
        return json_encode(
            $this->verdictPayload($candidateId, $sameIssue, $confidence, $justification, $suggestedType, $suggestedSeverity),
            JSON_UNESCAPED_UNICODE,
        ) ?: '{}';
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
