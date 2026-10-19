<?php

namespace Tests\Feature\Back;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class AiCheckCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'incident_similarity.ai_enabled' => true,
            'services.groq.key' => 'gsk_test_secret_value',
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

    public function test_a_successful_check_exits_zero_without_printing_the_key(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'OK'], 'finish_reason' => 'stop']],
            ]),
        ]);

        [$exitCode, $output] = $this->runCheck();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Modèle : openai/gpt-oss-120b', $output);
        $this->assertStringContainsString('HTTP : 200', $output);
        $this->assertStringContainsString('Durée :', $output);
        $this->assertStringContainsString('Verdict : OK', $output);
        $this->assertStringNotContainsString('gsk_test_secret_value', $output);
        Http::assertSentCount(1);

        Http::assertSent(fn (Request $request): bool => ($request->data()['model'] ?? null) === 'openai/gpt-oss-120b'
            && ($request->data()['max_completion_tokens'] ?? null) === 256
            && ($request->data()['reasoning_effort'] ?? null) === 'low');
    }

    public function test_a_missing_key_exits_non_zero_without_calling_the_provider(): void
    {
        config(['services.groq.key' => null]);
        Http::fake();

        [$exitCode, $output] = $this->runCheck();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Verdict : clé absente', $output);
        Http::assertNothingSent();
    }

    public function test_an_invalid_key_is_reported(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response(['error' => ['message' => 'Invalid API Key']], 401),
        ]);

        [$exitCode, $output] = $this->runCheck();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('HTTP : 401', $output);
        $this->assertStringContainsString('clé invalide', $output);
        $this->assertStringNotContainsString('Invalid API Key', $output);
    }

    public function test_a_wrong_url_or_model_is_reported(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response(['error' => ['message' => 'model_not_found']], 404),
        ]);

        [$exitCode, $output] = $this->runCheck();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('HTTP : 404', $output);
        $this->assertStringContainsString('URL ou modèle introuvable', $output);
    }

    public function test_a_rate_limit_is_reported_with_retry_after(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response(['error' => ['message' => 'rate limit']], 429, [
                'Retry-After' => '0',
            ]),
        ]);

        [$exitCode, $output] = $this->runCheck();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('HTTP : 429', $output);
        $this->assertStringContainsString('limite de débit', $output);
        $this->assertStringContainsString('Retry-After', $output);
        Http::assertSentCount(2);
    }

    public function test_a_network_timeout_is_reported(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        [$exitCode, $output] = $this->runCheck();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('délai dépassé', $output);
    }

    public function test_a_server_error_is_reported(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response('Internal server error', 500),
        ]);

        [$exitCode, $output] = $this->runCheck();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('HTTP : 500', $output);
        $this->assertStringContainsString('erreur côté fournisseur', $output);
    }

    public function test_an_empty_response_is_reported_as_invalid(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => ''], 'finish_reason' => 'stop']],
            ]),
        ]);

        [$exitCode, $output] = $this->runCheck();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('réponse invalide', $output);
        Http::assertSentCount(2);
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runCheck(): array
    {
        $output = new BufferedOutput;
        $exitCode = Artisan::call('ai:check', [], $output);

        return [$exitCode, $output->fetch()];
    }
}
