<?php

namespace App\Console\Commands;

use App\Services\GroqAIService;
use Illuminate\Console\Command;

class AiCheckCommand extends Command
{
    protected $signature = 'ai:check';

    protected $description = 'Envoie une requête minimale à Groq et affiche le statut, la durée et le verdict.';

    public function handle(GroqAIService $service): int
    {
        $result = $service->probe();

        $this->line('Modèle : '.($result['model'] !== '' ? $result['model'] : '(absent)'));
        $this->line('HTTP : '.($result['status'] ?? 'aucun'));
        $this->line('Durée : '.$result['duration_ms'].' ms');
        $this->line('Verdict : '.$this->verdictLabel($result));

        return $result['verdict'] === 'ok' ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array{status: int|null, duration_ms: int, model: string, verdict: string, retry_after: float|null}  $result
     */
    private function verdictLabel(array $result): string
    {
        return match ($result['verdict']) {
            'ok' => 'OK',
            'missing_key' => 'clé absente (GROQ_API_KEY)',
            'missing_config' => 'configuration incomplète (GROQ_API_URL / GROQ_MODEL)',
            'invalid_key' => 'clé invalide ou révoquée (401)',
            'forbidden' => 'accès refusé (403)',
            'not_found' => 'URL ou modèle introuvable (404)',
            'rate_limit' => 'limite de débit (429)'
                .($result['retry_after'] !== null
                    ? ', Retry-After : '.rtrim(rtrim(number_format($result['retry_after'], 2, ',', ''), '0'), ',').' s'
                    : ''),
            'timeout' => 'délai dépassé',
            'connection' => 'connexion impossible',
            'invalid_response' => 'réponse invalide',
            default => 'erreur côté fournisseur'
                .($result['status'] !== null ? ' (HTTP '.$result['status'].')' : ''),
        };
    }
}
