<?php

namespace App\Services;

use App\Models\Incident\Incident;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Confirmation optionnelle d'incidents similaires par Groq, fournisseur
 * compatible OpenAI (/chat/completions).
 *
 * Les candidats d'un même incident sont envoyés en un seul appel (5 maximum).
 * Le service est inactif tant que INCIDENT_SIMILARITY_AI_ENABLED n'est pas
 * actif ou qu'aucune clé n'est configurée. Il ne reçoit que les descriptions
 * des incidents (jamais l'identité du déclarant ni ses coordonnées) et ne
 * relaie aucune erreur technique à l'utilisateur.
 */
class GroqAIService
{
    public const FAILURE_RATE_LIMIT = 'rate_limit';

    public const FAILURE_TIMEOUT = 'timeout';

    public const FAILURE_CONNECTION = 'connection';

    public const FAILURE_INVALID_JSON = 'invalid_json';

    public const FAILURE_EMPTY = 'empty';

    public const FAILURE_TRUNCATED = 'truncated';

    public const FAILURE_HTTP = 'http_error';

    public const FAILURE_UNAVAILABLE = 'unavailable';

    private const MAX_DESCRIPTION_LENGTH = 4000;

    private const MAX_JUSTIFICATION_LENGTH = 300;

    private const MAX_CANDIDATES = 5;

    private const MAX_REQUEST_ATTEMPTS = 4;

    private const MAX_RETRY_DELAY_SECONDS = 10.0;

    private const DEFAULT_RETRY_DELAY_SECONDS = 1.0;

    private const MAX_COMPLETION_TOKENS_CAP = 8192;

    private static ?float $lastRequestAt = null;

    public function enabled(): bool
    {
        $enabled = (bool) config('incident_similarity.ai_enabled', false);
        $apiKey = config('services.groq.key');
        $missing = ! is_string($apiKey) || trim($apiKey) === '';

        if ($enabled && $missing) {
            Log::warning('Incident similarity AI key is missing or empty.', [
                'provider' => 'groq',
                'model' => (string) config('services.groq.model', 'openai/gpt-oss-120b'),
            ]);
        }

        return $enabled && ! $missing;
    }

    /**
     * Confirme en un seul appel l'ensemble des candidats d'un incident.
     *
     * @param  list<Incident>  $candidates
     * @return array{
     *     attempted: bool,
     *     results: array<int, array{
     *         candidate_id: int,
     *         is_same_issue: bool,
     *         confidence: int,
     *         justification: string,
     *         suggested_type: string|null,
     *         suggested_severity: string|null
     *     }|null>,
     *     failures: array<int, string>
     * }
     */
    public function confirmBatch(Incident $incident, array $candidates): array
    {
        $candidates = array_values(array_filter(
            $candidates,
            fn (mixed $candidate): bool => $candidate instanceof Incident,
        ));
        $candidates = array_slice($candidates, 0, self::MAX_CANDIDATES);

        $result = [
            'attempted' => false,
            'results' => [],
            'failures' => [],
        ];

        if ($candidates === [] || ! $this->enabled()) {
            return $result;
        }

        $result['attempted'] = true;

        /** @var list<int> $candidateIds */
        $candidateIds = array_map(
            fn (Incident $candidate): int => (int) $candidate->getKey(),
            $candidates,
        );

        foreach ($candidateIds as $candidateId) {
            $result['results'][$candidateId] = null;
            $result['failures'][$candidateId] = self::FAILURE_UNAVAILABLE;
        }

        $apiKey = trim((string) config('services.groq.key'));
        $url = trim((string) config('services.groq.url', 'https://api.groq.com/openai/v1/chat/completions'));
        $model = trim((string) config('services.groq.model', 'openai/gpt-oss-120b'));

        if ($url === '' || $model === '') {
            return $result;
        }

        $maxTokens = max(256, (int) config('services.groq.max_completion_tokens', 1500));

        $response = $this->request(
            $apiKey,
            $url,
            $model,
            [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => $this->userPrompt($incident, $candidates)],
            ],
            $maxTokens,
            true,
        );

        if (! $response['ok']) {
            $reason = $response['reason'] ?? self::FAILURE_HTTP;

            foreach ($candidateIds as $candidateId) {
                $result['failures'][$candidateId] = $reason;
            }

            return $result;
        }

        $parsed = $this->parseBatch((string) $response['content'], $candidateIds, $model);

        foreach ($candidateIds as $candidateId) {
            if (isset($parsed[$candidateId])) {
                $result['results'][$candidateId] = $parsed[$candidateId];
                unset($result['failures'][$candidateId]);
            } else {
                $result['failures'][$candidateId] = self::FAILURE_INVALID_JSON;
            }
        }

        if (count($parsed) < count($candidateIds)) {
            Log::warning('Incident similarity AI returned an invalid payload.', [
                'status' => $response['status'],
                'model' => $model,
                'duration_ms' => $response['duration_ms'],
                'reason' => self::FAILURE_INVALID_JSON,
                'finish_reason' => $response['finish_reason'],
                'expected' => count($candidateIds),
                'valid' => count($parsed),
            ]);
        }

        return $result;
    }

    /**
     * Envoie une requête minimale avec exactement les mêmes paramètres de
     * transport que la détection (modèle, timeout, tokens, raisonnement).
     *
     * @return array{
     *     status: int|null,
     *     duration_ms: int,
     *     model: string,
     *     verdict: string,
     *     retry_after: float|null
     * }
     */
    public function probe(): array
    {
        $model = trim((string) config('services.groq.model'));
        $started = hrtime(true);
        $key = trim((string) config('services.groq.key'));

        if ($key === '') {
            return $this->probeResult(null, 0, $model, 'missing_key');
        }

        $url = trim((string) config('services.groq.url'));

        if ($url === '' || $model === '') {
            return $this->probeResult(null, 0, $model, 'missing_config');
        }

        $maxTokens = min(256, max(64, (int) config('services.groq.max_completion_tokens', 1500)));

        $response = $this->request(
            $key,
            $url,
            $model,
            [
                ['role' => 'system', 'content' => 'Réponds uniquement par OK, sans autre texte.'],
                ['role' => 'user', 'content' => 'Réponds OK.'],
            ],
            $maxTokens,
            false,
        );

        $duration = $this->durationMs($started);

        if ($response['ok']) {
            return $this->probeResult(
                $response['status'],
                $duration,
                $model,
                trim((string) $response['content']) === '' ? 'invalid_response' : 'ok',
                $response['retry_after'],
            );
        }

        $verdict = match ($response['reason']) {
            self::FAILURE_RATE_LIMIT => 'rate_limit',
            self::FAILURE_TIMEOUT => 'timeout',
            self::FAILURE_CONNECTION => 'connection',
            self::FAILURE_EMPTY,
            self::FAILURE_TRUNCATED,
            self::FAILURE_INVALID_JSON => 'invalid_response',
            default => match ($response['status']) {
                401 => 'invalid_key',
                403 => 'forbidden',
                404 => 'not_found',
                429 => 'rate_limit',
                default => 'http_error',
            },
        };

        return $this->probeResult(
            $response['status'],
            $duration,
            $model,
            $verdict,
            $response['retry_after'],
        );
    }

    /**
     * Motif court et sûr conservé dans ai_justification.
     */
    public static function failureJustification(string $reason): string
    {
        $label = self::failureLabel($reason);

        if ($label === null) {
            return 'Analyse IA indisponible ; la suggestion locale est conservée.';
        }

        return 'Analyse IA indisponible ('.$label.') ; la suggestion locale est conservée.';
    }

    public static function failureReasonFromJustification(?string $justification): string
    {
        if (! is_string($justification)) {
            return self::FAILURE_UNAVAILABLE;
        }

        foreach (array_keys(self::failureLabels()) as $reason) {
            $label = self::failureLabel($reason);

            if ($label !== null && str_contains($justification, '('.$label.')')) {
                return $reason;
            }
        }

        return self::FAILURE_UNAVAILABLE;
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{
     *     ok: bool,
     *     status: int|null,
     *     content: string|null,
     *     reason: string|null,
     *     provider_message: string|null,
     *     retry_after: float|null,
     *     duration_ms: int,
     *     finish_reason: string|null
     * }
     */
    private function request(
        string $apiKey,
        string $url,
        string $model,
        array $messages,
        int $maxTokens,
        bool $jsonMode,
    ): array {
        $started = hrtime(true);
        $maxTokens = max(1, $maxTokens);
        $reasoningEffort = trim((string) config('services.groq.reasoning_effort', 'low'));
        $timeout = max(1, (int) config('services.groq.timeout', 30));
        $connectTimeout = max(1, (int) config('services.groq.connect_timeout', 5));
        $connectTimeout = min($connectTimeout, $timeout);

        $attempts = 0;
        $rateLimitRetried = false;
        $formatRetried = false;
        $tokenRetried = false;
        $retryAfter = null;

        while ($attempts < self::MAX_REQUEST_ATTEMPTS) {
            $attempts++;
            $attemptStarted = hrtime(true);
            $this->waitBetweenCalls();

            $payload = [
                'model' => $model,
                'temperature' => 0.0,
                'max_completion_tokens' => $maxTokens,
                'messages' => $messages,
            ];

            if ($jsonMode) {
                $payload['response_format'] = ['type' => 'json_object'];
            }

            if ($reasoningEffort !== '' && $this->supportsReasoningEffort($model)) {
                $payload['reasoning_effort'] = $reasoningEffort;
            }

            try {
                $response = Http::withToken($apiKey)
                    ->acceptJson()
                    ->timeout($timeout)
                    ->connectTimeout($connectTimeout)
                    ->post($url, $payload);
            } catch (ConnectionException $exception) {
                $failure = $this->failure(
                    $this->connectionFailureReason($exception),
                    null,
                    null,
                    $this->durationMs($attemptStarted),
                    null,
                    $retryAfter,
                );

                $this->logFailure($failure, $model);

                return $failure;
            } catch (Throwable $exception) {
                $failure = $this->failure(
                    self::FAILURE_CONNECTION,
                    null,
                    null,
                    $this->durationMs($attemptStarted),
                    null,
                    $retryAfter,
                );

                $this->logFailure($failure, $model, $exception::class);

                return $failure;
            } finally {
                self::$lastRequestAt = microtime(true);
            }

            $status = $response->status();
            $providerMessage = $this->providerErrorMessage($response);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');
                $content = is_string($content) ? $content : '';
                $finishReason = $response->json('choices.0.finish_reason');
                $finishReason = is_string($finishReason) ? $finishReason : null;

                if (trim($content) === '' || $finishReason === 'length') {
                    $reason = trim($content) === '' ? self::FAILURE_EMPTY : self::FAILURE_TRUNCATED;

                    if (! $tokenRetried) {
                        $tokenRetried = true;
                        $maxTokens = min(self::MAX_COMPLETION_TOKENS_CAP, max($maxTokens * 2, 2048));

                        $this->logFailure(
                            $this->failure(
                                $reason,
                                $status,
                                $providerMessage,
                                $this->durationMs($attemptStarted),
                                $finishReason,
                                $retryAfter,
                            ),
                            $model,
                            'higher_token_limit_retry',
                            true,
                        );

                        continue;
                    }

                    $failure = $this->failure(
                        $reason,
                        $status,
                        $providerMessage,
                        $this->durationMs($attemptStarted),
                        $finishReason,
                        $retryAfter,
                    );

                    $this->logFailure($failure, $model);

                    return $failure;
                }

                return [
                    'ok' => true,
                    'status' => $status,
                    'content' => $content,
                    'reason' => null,
                    'provider_message' => null,
                    'retry_after' => $retryAfter,
                    'duration_ms' => $this->durationMs($started),
                    'finish_reason' => $finishReason,
                ];
            }

            if ($status === 400 && $jsonMode && ! $formatRetried && $this->isJsonModeRejection($providerMessage)) {
                $formatRetried = true;
                $jsonMode = false;
                $messages = $this->withoutJsonMode($messages);

                $this->logFailure(
                    $this->failure(
                        self::FAILURE_HTTP,
                        $status,
                        $providerMessage,
                        $this->durationMs($attemptStarted),
                        null,
                        $retryAfter,
                    ),
                    $model,
                    'response_format_retry',
                    true,
                );

                continue;
            }

            if ($status === 429 && ! $rateLimitRetried) {
                $rateLimitRetried = true;
                $retryAfter = $this->retryAfterSeconds($response, $providerMessage);

                $this->logFailure(
                    $this->failure(
                        self::FAILURE_RATE_LIMIT,
                        $status,
                        $providerMessage,
                        $this->durationMs($attemptStarted),
                        null,
                        $retryAfter,
                    ),
                    $model,
                    null,
                    true,
                );

                if ($retryAfter > 0) {
                    usleep((int) round($retryAfter * 1_000_000));
                }

                continue;
            }

            $reason = $status === 429 ? self::FAILURE_RATE_LIMIT : self::FAILURE_HTTP;

            $failure = $this->failure(
                $reason,
                $status,
                $providerMessage,
                $this->durationMs($attemptStarted),
                null,
                $retryAfter,
            );

            $this->logFailure($failure, $model);

            return $failure;
        }

        $failure = $this->failure(self::FAILURE_HTTP, null, null, $this->durationMs($started), null, $retryAfter);
        $this->logFailure($failure, $model);

        return $failure;
    }

    /**
     * Espace les appels IA consécutifs d'un même processus (GROQ_DELAY_MS).
     */
    private function waitBetweenCalls(): void
    {
        $delayMs = max(0, (int) config('services.groq.delay_ms', 1000));

        if ($delayMs === 0 || self::$lastRequestAt === null) {
            return;
        }

        $remaining = ($delayMs / 1000) - (microtime(true) - self::$lastRequestAt);

        if ($remaining > 0) {
            usleep((int) round($remaining * 1_000_000));
        }
    }

    private function supportsReasoningEffort(string $model): bool
    {
        return str_contains(strtolower($model), 'gpt-oss')
            || str_contains(strtolower($model), 'reasoning');
    }

    /**
     * @return array{
     *     ok: bool,
     *     status: int|null,
     *     content: string|null,
     *     reason: string|null,
     *     provider_message: string|null,
     *     retry_after: float|null,
     *     duration_ms: int,
     *     finish_reason: string|null
     * }
     */
    private function failure(
        string $reason,
        ?int $status,
        ?string $providerMessage,
        int $durationMs,
        ?string $finishReason = null,
        ?float $retryAfter = null,
    ): array {
        return [
            'ok' => false,
            'status' => $status,
            'content' => null,
            'reason' => $reason,
            'provider_message' => $providerMessage,
            'retry_after' => $retryAfter,
            'duration_ms' => $durationMs,
            'finish_reason' => $finishReason,
        ];
    }

    /**
     * @param  array<string, mixed>  $failure
     */
    private function logFailure(array $failure, string $model, ?string $note = null, ?bool $retried = null): void
    {
        $context = [
            'status' => $failure['status'] ?? null,
            'model' => $model,
            'duration_ms' => $failure['duration_ms'] ?? null,
            'reason' => $failure['reason'] ?? self::FAILURE_HTTP,
        ];

        if (($failure['provider_message'] ?? null) !== null) {
            $context['provider_message'] = $failure['provider_message'];
        }

        if (($failure['finish_reason'] ?? null) !== null) {
            $context['finish_reason'] = $failure['finish_reason'];
        }

        if ($note !== null) {
            $context['note'] = $note;
        }

        if ($retried !== null) {
            $context['retried'] = $retried;
        }

        Log::warning('Incident similarity AI request failed.', $context);
    }

    private function connectionFailureReason(ConnectionException $exception): string
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'timed out') || str_contains($message, 'timeout')
            ? self::FAILURE_TIMEOUT
            : self::FAILURE_CONNECTION;
    }

    private function providerErrorMessage(Response $response): ?string
    {
        $message = $response->json('error.message') ?? $response->json('message');

        if (! is_string($message) || trim($message) === '') {
            return null;
        }

        $message = trim(preg_replace('/\s+/', ' ', $message) ?? '');

        return mb_substr($message, 0, 300);
    }

    private function isJsonModeRejection(?string $providerMessage): bool
    {
        if ($providerMessage === null) {
            return false;
        }

        return stripos($providerMessage, 'json') !== false
            || stripos($providerMessage, 'response_format') !== false;
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return list<array{role: string, content: string}>
     */
    private function withoutJsonMode(array $messages): array
    {
        $messages[0]['content'] = $messages[0]['content']
            ."\nLa réponse doit être uniquement un objet JSON valide, sans texte ni balises Markdown.";

        return $messages;
    }

    /**
     * Lit Retry-After, puis x-ratelimit-reset-*, puis le message Groq
     * « try again in 1.2s ». Plafonné à 10 s.
     */
    private function retryAfterSeconds(Response $response, ?string $providerMessage): float
    {
        $header = trim((string) $response->header('Retry-After'));

        if ($header !== '') {
            if (is_numeric($header)) {
                return $this->clampRetryDelay((float) $header);
            }

            $timestamp = strtotime($header);

            if ($timestamp !== false) {
                return $this->clampRetryDelay(max(0, $timestamp - time()));
            }
        }

        foreach (['x-ratelimit-reset-tokens', 'x-ratelimit-reset-requests'] as $name) {
            $seconds = $this->durationHeaderSeconds((string) $response->header($name));

            if ($seconds !== null) {
                return $this->clampRetryDelay($seconds);
            }
        }

        if ($providerMessage !== null
            && preg_match('/try again in ([0-9.]+)\s*(ms|s|m)?/i', $providerMessage, $matches) === 1
        ) {
            $seconds = (float) $matches[1];
            $unit = strtolower($matches[2] ?? 's');

            if ($unit === 'ms') {
                $seconds /= 1000;
            } elseif ($unit === 'm') {
                $seconds *= 60;
            }

            return $this->clampRetryDelay($seconds);
        }

        return self::DEFAULT_RETRY_DELAY_SECONDS;
    }

    private function durationHeaderSeconds(string $value): ?float
    {
        if (preg_match('/^([0-9.]+)\s*(ms|s|m)?$/i', trim($value), $matches) !== 1) {
            return null;
        }

        $seconds = (float) $matches[1];
        $unit = strtolower($matches[2] ?? 's');

        if ($unit === 'ms') {
            return $seconds / 1000;
        }

        if ($unit === 'm') {
            return $seconds * 60;
        }

        return $seconds;
    }

    private function clampRetryDelay(float $seconds): float
    {
        return max(0.0, min(self::MAX_RETRY_DELAY_SECONDS, $seconds));
    }

    private function systemPrompt(): string
    {
        $types = implode(', ', array_keys(Incident::TYPES));
        $severities = implode(', ', array_keys(Incident::SEVERITIES));

        return "Tu es un assistant de gestion d'incidents d'un réseau d'eau potable.\n"
            ."Compare le signalement source A à chacun des signalements candidats B.\n"
            ."Indique pour chaque candidat si sa description concerne très probablement le même problème que A.\n"
            ."Réponds uniquement avec un objet JSON valide, sans texte ni balises Markdown :\n"
            .'{"results": [{"candidate_id": <identifiant interne de B>, "is_same_issue": true|false, "confidence": 0-100, "justification": "phrase courte", "suggested_type": "<type ou null>", "suggested_severity": "<gravité ou null>"}]}'."\n"
            ."Fournis exactement un élément par candidat B, sans omission ni doublon.\n"
            ."Recopie candidate_id exactement depuis le signalement candidat B correspondant.\n"
            ."Types autorisés : {$types}.\n"
            ."Gravités autorisées : {$severities}.\n"
            ."suggested_type et suggested_severity sont de simples propositions ; mets null en cas de doute.\n"
            ."confidence est une appréciation du modèle, pas une probabilité calibrée.\n"
            .'La justification doit faire moins de 200 caractères et ne contenir aucune donnée personnelle.';
    }

    /**
     * @param  list<Incident>  $candidates
     */
    private function userPrompt(Incident $incident, array $candidates): string
    {
        $parts = [
            'Signalement source A (identifiant interne '.$incident->getKey().") :\n"
                .$this->excerpt($incident->description),
            'Signalements candidats B (même zone, à comparer séparément) :',
        ];

        foreach ($candidates as $candidate) {
            $parts[] = '--- Candidat B (identifiant interne '.$candidate->getKey().') ---'."\n"
                .$this->excerpt($candidate->description);
        }

        return implode("\n\n", $parts);
    }

    private function excerpt(?string $description): string
    {
        $description = trim((string) $description);

        return mb_substr($description, 0, self::MAX_DESCRIPTION_LENGTH);
    }

    /**
     * Validation stricte de chaque élément renvoyé par le modèle.
     *
     * @param  list<int>  $expectedCandidateIds
     * @return array<int, array{
     *     candidate_id: int,
     *     is_same_issue: bool,
     *     confidence: int,
     *     justification: string,
     *     suggested_type: string|null,
     *     suggested_severity: string|null
     * }>
     */
    private function parseBatch(string $content, array $expectedCandidateIds, string $model): array
    {
        $decoded = $this->decodeJson($content);

        if ($decoded === null) {
            return [];
        }

        $items = null;

        if (isset($decoded['results']) && is_array($decoded['results'])) {
            $items = $decoded['results'];
        } elseif (array_is_list($decoded)) {
            $items = $decoded;
        } elseif (isset($decoded['candidate_id'])) {
            $items = [$decoded];
        }

        if (! is_array($items)) {
            return [];
        }

        $valid = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $verdict = $this->validateVerdict($item, $expectedCandidateIds, $model);

            if ($verdict !== null && ! isset($valid[$verdict['candidate_id']])) {
                $valid[$verdict['candidate_id']] = $verdict;
            }
        }

        return $valid;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @param  list<int>  $expectedCandidateIds
     * @return array{
     *     candidate_id: int,
     *     is_same_issue: bool,
     *     confidence: int,
     *     justification: string,
     *     suggested_type: string|null,
     *     suggested_severity: string|null
     * }|null
     */
    private function validateVerdict(array $decoded, array $expectedCandidateIds, string $model): ?array
    {
        $candidateId = $decoded['candidate_id'] ?? null;
        $isSameIssue = $decoded['is_same_issue'] ?? null;
        $confidence = $decoded['confidence'] ?? null;
        $justification = $decoded['justification'] ?? null;

        if (! is_int($candidateId) && ! (is_string($candidateId) && ctype_digit($candidateId))) {
            return null;
        }

        $candidateId = (int) $candidateId;

        if (! in_array($candidateId, $expectedCandidateIds, true)) {
            Log::warning('Incident similarity AI returned an unexpected candidate identifier.', [
                'candidate_id' => $candidateId,
                'model' => $model,
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

        $suggestedType = $this->validatedType($decoded['suggested_type'] ?? null);

        if ($suggestedType === false) {
            Log::warning('Incident similarity AI returned an invalid suggested type.', [
                'model' => $model,
            ]);

            return null;
        }

        $suggestedSeverity = $this->validatedSeverity($decoded['suggested_severity'] ?? null);

        if ($suggestedSeverity === false) {
            Log::warning('Incident similarity AI returned an invalid suggested severity.', [
                'model' => $model,
            ]);

            return null;
        }

        return [
            'candidate_id' => $candidateId,
            'is_same_issue' => $isSameIssue,
            'confidence' => $confidence,
            'justification' => mb_substr($justification, 0, self::MAX_JUSTIFICATION_LENGTH),
            'suggested_type' => $suggestedType,
            'suggested_severity' => $suggestedSeverity,
        ];
    }

    /**
     * @return string|null|false null si absent, false si invalide
     */
    private function validatedType(mixed $value): string|null|false
    {
        if ($value === null) {
            return null;
        }

        return is_string($value) && array_key_exists($value, Incident::TYPES) ? $value : false;
    }

    /**
     * @return string|null|false null si absent, false si invalide
     */
    private function validatedSeverity(mixed $value): string|null|false
    {
        if ($value === null) {
            return null;
        }

        return is_string($value) && array_key_exists($value, Incident::SEVERITIES) ? $value : false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(string $content): ?array
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

        foreach ([['{', '}'], ['[', ']']] as [$opening, $closing]) {
            $start = strpos($content, $opening);
            $end = strrpos($content, $closing);

            if ($start === false || $end === false || $end <= $start) {
                continue;
            }

            $decoded = json_decode(substr($content, $start, $end - $start + 1), true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @return array{status: int|null, duration_ms: int, model: string, verdict: string, retry_after: float|null}
     */
    private function probeResult(
        ?int $status,
        int $durationMs,
        string $model,
        string $verdict,
        ?float $retryAfter = null,
    ): array {
        return [
            'status' => $status,
            'duration_ms' => $durationMs,
            'model' => $model,
            'verdict' => $verdict,
            'retry_after' => $retryAfter,
        ];
    }

    private function durationMs(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }

    public static function failureLabel(string $reason): ?string
    {
        return self::failureLabels()[$reason] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private static function failureLabels(): array
    {
        return [
            self::FAILURE_RATE_LIMIT => 'limite de débit',
            self::FAILURE_TIMEOUT => 'délai dépassé',
            self::FAILURE_CONNECTION => 'connexion impossible',
            self::FAILURE_INVALID_JSON => 'réponse invalide',
            self::FAILURE_EMPTY => 'réponse vide',
            self::FAILURE_TRUNCATED => 'réponse tronquée',
            self::FAILURE_HTTP => 'erreur du fournisseur',
        ];
    }
}
