<?php

namespace App\Services;

use App\Models\Incident\Incident;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Confirmation optionnelle d'une paire d'incidents par un fournisseur IA
 * compatible OpenAI (/chat/completions).
 *
 * Le service est inactif tant que INCIDENT_SIMILARITY_AI_ENABLED n'est pas
 * actif ou qu'aucune clé n'est configurée. Il ne reçoit que les descriptions
 * des incidents (jamais l'identité du déclarant ni ses coordonnées) et ne
 * relaie aucune erreur technique à l'utilisateur.
 */
class GrokAIService
{
    private const MAX_DESCRIPTION_LENGTH = 4000;

    private const MAX_JUSTIFICATION_LENGTH = 300;

    public function enabled(): bool
    {
        $apiKey = config('services.xai.api_key');

        return (bool) config('incident_similarity.ai_enabled', false)
            && is_string($apiKey)
            && trim($apiKey) !== '';
    }

    /**
     * @return array{candidate_id: int, is_same_issue: bool, confidence: int, justification: string}|null
     */
    public function confirm(Incident $incident, Incident $candidate): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $url = (string) config('services.xai.url', 'https://api.x.ai/v1/chat/completions');
        $model = (string) config('services.xai.model', 'grok-4.7');
        $timeout = max(3, (int) config('services.xai.timeout', 8));

        try {
            $response = Http::withToken(trim((string) config('services.xai.api_key')))
                ->acceptJson()
                ->timeout($timeout)
                ->connectTimeout(min(5, $timeout))
                ->post($url, [
                    'model' => $model,
                    'temperature' => 0.0,
                    'max_tokens' => 250,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user', 'content' => $this->userPrompt($incident, $candidate)],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Incident similarity AI returned an error status.', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $this->parse(
                $response->json('choices.0.message.content'),
                (int) $candidate->getKey(),
            );
        } catch (Throwable $exception) {
            Log::warning('Incident similarity AI unavailable, falling back to local score.', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function systemPrompt(): string
    {
        return "Tu es un assistant de gestion d'incidents d'un réseau d'eau potable.\n"
            ."Compare le signalement source A et le signalement candidat B.\n"
            ."Indique si leurs descriptions concernent très probablement le même problème.\n"
            ."Réponds uniquement avec un objet JSON valide, sans texte ni balises Markdown :\n"
            .'{"candidate_id": <identifiant interne de B>, "is_same_issue": true|false, "confidence": 0-100, "justification": "phrase courte"}'."\n"
            ."Recopie candidate_id exactement depuis le signalement candidat B.\n"
            ."confidence est une appréciation du modèle, pas une probabilité calibrée.\n"
            .'La justification doit faire moins de 200 caractères et ne contenir aucune donnée personnelle.';
    }

    private function userPrompt(Incident $incident, Incident $candidate): string
    {
        return 'Signalement source A (identifiant interne '.$incident->getKey().") :\n"
            .$this->excerpt($incident->description)
            ."\n\nSignalement candidat B (identifiant interne ".$candidate->getKey().") :\n"
            .$this->excerpt($candidate->description);
    }

    private function excerpt(?string $description): string
    {
        $description = trim((string) $description);

        return mb_substr($description, 0, self::MAX_DESCRIPTION_LENGTH);
    }

    /**
     * Validation stricte de la réponse du modèle, y compris l'identifiant
     * du candidat pour écarter une réponse associée au mauvais signalement.
     *
     * @return array{candidate_id: int, is_same_issue: bool, confidence: int, justification: string}|null
     */
    private function parse(mixed $content, int $expectedCandidateId): ?array
    {
        if (! is_string($content) || trim($content) === '') {
            return null;
        }

        $decoded = $this->decodeJsonObject($content);

        if ($decoded === null) {
            return null;
        }

        $candidateId = $decoded['candidate_id'] ?? null;
        $isSameIssue = $decoded['is_same_issue'] ?? null;
        $confidence = $decoded['confidence'] ?? null;
        $justification = $decoded['justification'] ?? null;

        if (! is_int($candidateId) && ! (is_string($candidateId) && ctype_digit($candidateId))) {
            return null;
        }

        if ((int) $candidateId !== $expectedCandidateId) {
            Log::warning('Incident similarity AI returned an unexpected candidate identifier.', [
                'expected' => $expectedCandidateId,
            ]);

            return null;
        }

        if (! is_bool($isSameIssue) || ! is_numeric($confidence) || ! is_string($justification)) {
            return null;
        }

        $confidence = (int) round((float) $confidence);

        if ($confidence < 0 || $confidence > 100) {
            return null;
        }

        $justification = trim(preg_replace('/\s+/', ' ', $justification) ?? '');

        if ($justification === '') {
            return null;
        }

        return [
            'candidate_id' => (int) $candidateId,
            'is_same_issue' => $isSameIssue,
            'confidence' => $confidence,
            'justification' => mb_substr($justification, 0, self::MAX_JUSTIFICATION_LENGTH),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonObject(string $content): ?array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', trim($content)) ?? trim($content);

        $decoded = json_decode($content, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $content, $matches) === 1) {
            $decoded = json_decode(trim($matches[1]), true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($content, $start, $end - $start + 1), true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
