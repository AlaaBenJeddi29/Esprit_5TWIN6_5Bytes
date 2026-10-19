<?php

namespace Tests\Feature\Back;

use App\Models\Incident\Incident;
use App\Models\Incident\IncidentSimilarity;
use App\Models\Infrastructure\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DetectSimilarIncidentsCommandTest extends TestCase
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

        Http::preventStrayRequests();
    }

    public function test_command_summary_reports_ai_confirmations(): void
    {
        $this->fakeSuccessfulBatch();

        $zone = Zone::factory()->create();
        $this->incident($zone);
        $this->incident($zone);

        $this->artisan('incidents:detect-similar', ['--days' => 30])
            ->expectsOutputToContain('confirmée(s) (local_ai)')
            ->assertSuccessful();

        $this->assertSame(
            2,
            IncidentSimilarity::query()->where('method', IncidentSimilarity::METHOD_LOCAL_AI)->count(),
        );
        Http::assertSentCount(2);
    }

    public function test_command_summary_reports_fallback_reasons(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response('Too many requests', 429, ['Retry-After' => '0']),
        ]);

        $zone = Zone::factory()->create();
        $this->incident($zone);
        $this->incident($zone);

        $this->artisan('incidents:detect-similar', ['--days' => 30])
            ->expectsOutputToContain('limite de débit : 2')
            ->assertSuccessful();

        $suggestions = IncidentSimilarity::query()
            ->where('method', IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE)
            ->get();

        $this->assertCount(2, $suggestions);
        $this->assertTrue($suggestions->every(
            fn (IncidentSimilarity $suggestion): bool => str_contains((string) $suggestion->ai_justification, '(limite de débit)'),
        ));
    }

    public function test_retry_failed_only_updates_pending_unavailable_suggestions(): void
    {
        $this->fakeSuccessfulBatch();

        $zone = Zone::factory()->create();
        $source = $this->incident($zone);
        $candidate = $this->incident($zone);
        $confirmedCandidate = $this->incident($zone);
        $decidedCandidate = $this->incident($zone);

        $pending = IncidentSimilarity::factory()->create([
            'incident_id' => $source->id,
            'similar_incident_id' => $candidate->id,
            'method' => IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE,
            'ai_justification' => 'Analyse IA indisponible ; la suggestion locale est conservée.',
            'status' => IncidentSimilarity::STATUS_SUGGESTED,
        ]);

        $confirmed = IncidentSimilarity::factory()->create([
            'incident_id' => $source->id,
            'similar_incident_id' => $confirmedCandidate->id,
            'method' => IncidentSimilarity::METHOD_LOCAL_AI,
            'ai_justification' => 'IA : même problème probable. Indice déclaré par le modèle : 90/100.',
            'status' => IncidentSimilarity::STATUS_SUGGESTED,
        ]);

        $decided = IncidentSimilarity::factory()->create([
            'incident_id' => $source->id,
            'similar_incident_id' => $decidedCandidate->id,
            'method' => IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE,
            'ai_justification' => 'Analyse IA indisponible ; la suggestion locale est conservée.',
            'status' => IncidentSimilarity::STATUS_DUPLICATE,
            'reviewed_at' => now(),
        ]);

        $this->artisan('incidents:detect-similar', ['--retry-failed' => true])
            ->expectsOutputToContain('1 suggestion(s) rejouée(s)')
            ->assertSuccessful();

        $pending->refresh();
        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI, $pending->method);
        $this->assertStringContainsString('IA : même problème probable', (string) $pending->ai_justification);

        $confirmed->refresh();
        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI, $confirmed->method);
        $this->assertSame(
            'IA : même problème probable. Indice déclaré par le modèle : 90/100.',
            $confirmed->ai_justification,
        );

        $decided->refresh();
        $this->assertSame(IncidentSimilarity::STATUS_DUPLICATE, $decided->status);
        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE, $decided->method);
        $this->assertSame(
            'Analyse IA indisponible ; la suggestion locale est conservée.',
            $decided->ai_justification,
        );

        Http::assertSentCount(1);
    }

    public function test_retry_failed_does_nothing_when_ai_is_disabled(): void
    {
        config(['incident_similarity.ai_enabled' => false]);
        Http::fake();

        $zone = Zone::factory()->create();
        $source = $this->incident($zone);
        $candidate = $this->incident($zone);

        $pending = IncidentSimilarity::factory()->create([
            'incident_id' => $source->id,
            'similar_incident_id' => $candidate->id,
            'method' => IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE,
            'ai_justification' => 'Analyse IA indisponible ; la suggestion locale est conservée.',
            'status' => IncidentSimilarity::STATUS_SUGGESTED,
        ]);

        $this->artisan('incidents:detect-similar', ['--retry-failed' => true])
            ->expectsOutputToContain('IA désactivée')
            ->assertSuccessful();

        $pending->refresh();
        $this->assertSame(IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE, $pending->method);
        Http::assertNothingSent();
    }

    private function fakeSuccessfulBatch(): void
    {
        Http::fake(function (Request $request) {
            $prompt = (string) ($request->data()['messages'][1]['content'] ?? '');

            preg_match_all('/--- Candidat B \(identifiant interne (\d+)\) ---/u', $prompt, $matches);

            $results = array_map(fn (int $candidateId): array => [
                'candidate_id' => $candidateId,
                'is_same_issue' => true,
                'confidence' => 88,
                'justification' => 'Même problème.',
            ], array_map('intval', $matches[1]));

            return Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode(['results' => $results], JSON_UNESCAPED_UNICODE)],
                    'finish_reason' => 'stop',
                ]],
            ]);
        });
    }

    private function incident(Zone $zone): Incident
    {
        return Incident::factory()->create([
            'zone_id' => $zone->id,
            'type' => 'fuite',
            'title' => "Fuite d'eau devant l'école",
            'description' => "Une fuite importante d'eau potable inonde la chaussée devant l'école depuis ce matin.",
            'severity' => 'haute',
            'status' => 'nouveau',
            'priority' => 'haute',
            'reported_at' => now(),
        ]);
    }
}
