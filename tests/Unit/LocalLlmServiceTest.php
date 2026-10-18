<?php

namespace Tests\Unit;

use App\Services\LocalLlmService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Tests unitaires de la couche IA locale (Ollama). Tout HTTP est simulé :
 * aucun serveur Ollama, aucune base de données et aucun accès Internet
 * ne sont nécessaires.
 */
class LocalLlmServiceTest extends TestCase
{
    private const URL = 'http://localhost:11434';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.local_llm' => [
                'enabled' => true,
                'url' => self::URL,
                'model' => 'llama3.2',
                'timeout' => 120,
                'connect_timeout' => 5,
                'temperature' => 0.3,
            ],
        ]);

        Http::preventStrayRequests();
    }

    private function service(): LocalLlmService
    {
        return new LocalLlmService;
    }

    public function test_generate_returns_text_when_ollama_answers(): void
    {
        Http::fake([
            self::URL.'/api/generate' => Http::response(['response' => '  Analyse locale générée.  ']),
        ]);

        $resultat = $this->service()->generer('Analyse ces chiffres.');

        $this->assertTrue($resultat['success']);
        $this->assertSame('Analyse locale générée.', $resultat['texte']);
        $this->assertNull($resultat['code']);
        $this->assertNull($resultat['message']);

        Http::assertSent(function ($request) {
            return $request['model'] === 'llama3.2'
                && $request['stream'] === false
                && $request['options']['temperature'] === 0.3;
        });
    }

    public function test_generate_accepts_model_and_temperature_overrides(): void
    {
        Http::fake([
            self::URL.'/api/generate' => Http::response(['response' => 'ok']),
        ]);

        $resultat = $this->service()->generer('x', ['model' => 'qwen2.5:3b', 'temperature' => 0.7]);

        $this->assertTrue($resultat['success']);

        Http::assertSent(function ($request) {
            return $request['model'] === 'qwen2.5:3b'
                && $request['options']['temperature'] === 0.7;
        });
    }

    public function test_generate_adds_scheme_when_url_has_none(): void
    {
        config(['services.local_llm.url' => 'localhost:11434']);

        Http::fake([
            'localhost:11434/api/generate' => Http::response(['response' => 'ok']),
        ]);

        $resultat = $this->service()->generer('x');

        $this->assertTrue($resultat['success']);
    }

    public function test_generate_fails_gracefully_when_ollama_is_unreachable(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect'));

        $resultat = $this->service()->generer('x');

        $this->assertFalse($resultat['success']);
        $this->assertSame('indisponible', $resultat['code']);
        $this->assertSame('', $resultat['texte']);
    }

    public function test_generate_reports_timeout_as_distinct_code(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 120000 milliseconds'));

        $resultat = $this->service()->generer('x');

        $this->assertFalse($resultat['success']);
        $this->assertSame('expiration', $resultat['code']);
    }

    public function test_generate_reports_http_error_status(): void
    {
        Http::fake([
            self::URL.'/api/generate' => Http::response('service indisponible', 500),
        ]);

        $resultat = $this->service()->generer('x');

        $this->assertFalse($resultat['success']);
        $this->assertSame('statut_http', $resultat['code']);
    }

    public function test_generate_rejects_unexpected_response_shape(): void
    {
        // réponse sans clé « response »
        Http::fake([self::URL.'/api/generate' => Http::response(['error' => 'boom'])]);
        $this->assertSame('reponse_invalide', $this->service()->generer('x')['code']);

        // « response » non texte
        Http::fake([self::URL.'/api/generate' => Http::response(['response' => 42])]);
        $this->assertSame('reponse_invalide', $this->service()->generer('x')['code']);

        // corps non JSON
        Http::fake([self::URL.'/api/generate' => Http::response('<html>pas du json</html>')]);
        $this->assertSame('reponse_invalide', $this->service()->generer('x')['code']);
    }

    public function test_generate_rejects_blank_response(): void
    {
        Http::fake([self::URL.'/api/generate' => Http::response(['response' => '   '])]);

        $resultat = $this->service()->generer('x');

        $this->assertFalse($resultat['success']);
        $this->assertSame('reponse_vide', $resultat['code']);
    }

    public function test_generate_refuses_non_local_hosts_without_any_request(): void
    {
        config(['services.local_llm.url' => 'https://api.exemple-externe.com']);

        Http::fake();

        $resultat = $this->service()->generer('données confidentielles du projet');

        $this->assertFalse($resultat['success']);
        $this->assertSame('hote_non_local', $resultat['code']);
        Http::assertNothingSent();
    }

    public function test_generate_does_nothing_when_layer_disabled(): void
    {
        config(['services.local_llm.enabled' => false]);

        Http::fake();

        $resultat = $this->service()->generer('x');

        $this->assertFalse($resultat['success']);
        $this->assertSame('desactive', $resultat['code']);
        Http::assertNothingSent();
    }

    public function test_failure_logs_never_contain_the_prompt(): void
    {
        Log::spy();

        Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect'));

        $this->service()->generer('MESSAGE-TOP-SECRET du dossier projet');

        Log::shouldNotHaveReceived('warning', function (string $message): bool {
            return str_contains($message, 'MESSAGE-TOP-SECRET');
        });
    }

    public function test_is_available_when_configured_model_is_installed(): void
    {
        Http::fake([
            self::URL.'/api/tags' => Http::response(['models' => [
                ['name' => 'qwen2.5:3b'],
                ['name' => 'llama3.2:latest'],
            ]]),
        ]);

        $this->assertTrue($this->service()->estDisponible());
    }

    public function test_is_available_matches_model_variants(): void
    {
        Http::fake([
            self::URL.'/api/tags' => Http::response(['models' => [['name' => 'llama3.2:3b-instruct']]]),
        ]);

        $this->assertTrue($this->service()->estDisponible());
    }

    public function test_is_not_available_when_model_missing_or_answer_invalid(): void
    {
        Http::fake([
            self::URL.'/api/tags' => Http::response(['models' => [['name' => 'qwen2.5:3b']]]),
        ]);
        $this->assertFalse($this->service()->estDisponible());

        Http::fake([
            self::URL.'/api/tags' => Http::response(['pas_de_modeles' => true]),
        ]);
        $this->assertFalse($this->service()->estDisponible());
    }

    public function test_is_not_available_when_ollama_does_not_answer(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect'));

        $this->assertFalse($this->service()->estDisponible());
    }

    public function test_is_not_available_for_non_local_host(): void
    {
        config(['services.local_llm.url' => 'https://api.exemple-externe.com']);

        Http::fake();

        $this->assertFalse($this->service()->estDisponible());
        Http::assertNothingSent();
    }

    public function test_defaults_apply_when_configuration_is_empty(): void
    {
        config(['services.local_llm' => []]);

        Http::fake([
            'localhost:11434/api/generate' => Http::response(['response' => 'ok']),
        ]);

        $resultat = $this->service()->generer('x');

        $this->assertTrue($resultat['success']);

        Http::assertSent(function ($request) {
            return $request['model'] === 'llama3.2';
        });
    }
}
