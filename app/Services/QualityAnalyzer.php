<?php

namespace App\Services;

use App\Models\Infrastructure\Zone;
use App\Models\Quality\QualityThreshold;
use App\Models\Quality\WaterQualityAlert;
use App\Models\Quality\WaterQualityParameter;
use App\Models\Quality\WaterQualitySample;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class QualityAnalyzer
{
    /**
     * Seuils de secours utilisés lorsqu'aucun seuil n'est configuré.
     *
     * @var array<string, array{min: float|null, max: float|null, unite: string}>
     */
    public const DEFAULT_THRESHOLDS = [
        'ph' => ['min' => 6.5, 'max' => 8.5, 'unite' => 'pH'],
        'turbidite' => ['min' => 0, 'max' => 5, 'unite' => 'NTU'],
        'chlore' => ['min' => 0.1, 'max' => 1.0, 'unite' => 'mg/L'],
        'nitrates' => ['min' => 0, 'max' => 50, 'unite' => 'mg/L'],
        'plomb' => ['min' => 0, 'max' => 10, 'unite' => 'µg/L'],
        'bacteries' => ['min' => 0, 'max' => 0, 'unite' => 'UFC/100 mL'],
        'conductivite' => ['min' => 0, 'max' => 2500, 'unite' => 'µS/cm'],
    ];

    private const AI_SUMMARY_CACHE_PREFIX = 'quality.ai_summary.';

    private const AI_SUMMARY_TTL_DAYS = 30;

    private const AI_UNAVAILABLE_TTL_MINUTES = 10;

    /**
     * Analyse un prélèvement : conformité par paramètre, score global et alertes.
     *
     * @param  list<array{parametre: string, valeur: float|int|string, unite?: string}>  $parameters
     * @return array{statut: string, score: float, non_conformes: int, alertes: int, resume_ia: string|null, anomalies_ia: list<string>}
     */
    public function analyze(WaterQualitySample $sample, array $parameters, ?int $authorId = null): array
    {
        $analysis = DB::transaction(fn () => $this->analyzeMeasurements($sample, $parameters));

        $aiSummary = $this->aiSummary($sample, $analysis['ai_payload']);

        return [
            'statut' => $analysis['statut'],
            'score' => $analysis['score'],
            'non_conformes' => $analysis['non_conformes'],
            'alertes' => $analysis['alertes'],
            'resume_ia' => $aiSummary['resume'] ?? null,
            'anomalies_ia' => $aiSummary['anomalies'] ?? [],
        ];
    }

    /**
     * Résumé IA conservé en cache pour ce prélèvement, s'il est disponible.
     *
     * @return array{resume: string, anomalies: list<string>, genere_le: string|null}|null
     */
    public function cachedSummary(WaterQualitySample $sample): ?array
    {
        $cached = Cache::get(self::AI_SUMMARY_CACHE_PREFIX.$sample->id);

        if (! is_array($cached) || ! is_string($cached['resume'] ?? null) || trim($cached['resume']) === '') {
            return null;
        }

        return [
            'resume' => $cached['resume'],
            'anomalies' => is_array($cached['anomalies'] ?? null) ? array_values($cached['anomalies']) : [],
            'genere_le' => is_string($cached['genere_le'] ?? null) ? $cached['genere_le'] : null,
        ];
    }

    /**
     * Résumé affiché sur la fiche d'un prélèvement. Réutilise le cache s'il
     * est disponible, sinon tente une génération à partir des mesures déjà
     * enregistrées, sans recalculer le score ni recréer les alertes.
     *
     * @return array{resume: string, anomalies: list<string>, genere_le: string|null}|null
     */
    public function summaryForDisplay(WaterQualitySample $sample): ?array
    {
        $cached = $this->cachedSummary($sample);

        if ($cached !== null) {
            return $cached;
        }

        $apiKey = config('services.gemini.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            return null;
        }

        $sample->loadMissing(['zone', 'parameters']);

        if ($sample->parameters->isEmpty()) {
            return null;
        }

        $aiParameters = $sample->parameters
            ->map(function (WaterQualityParameter $parameter) use ($sample): array {
                $threshold = $this->thresholdFor($sample, $parameter->parametre);

                return [
                    'code' => $parameter->parametre,
                    'parametre' => QualityThreshold::PARAMETRES[$parameter->parametre] ?? $parameter->parametre,
                    'valeur' => (float) $parameter->valeur,
                    'unite' => $parameter->unite,
                    'seuil_min' => $parameter->seuil_min !== null ? (float) $parameter->seuil_min : null,
                    'seuil_max' => $parameter->seuil_max !== null ? (float) $parameter->seuil_max : null,
                    'origine_seuil' => $threshold ? 'base_de_donnees' : 'valeur_par_defaut',
                    'conforme' => (bool) $parameter->est_conforme,
                ];
            })
            ->values()
            ->all();

        $payload = [
            'zone' => $sample->zone?->nom,
            'date_prelevement' => $sample->date_prelevement?->toIso8601String(),
            'statut' => (string) $sample->statut,
            'score' => (float) ($sample->score ?? 0),
            'non_conformes' => $sample->parameters
                ->filter(fn (WaterQualityParameter $parameter): bool => ! (bool) $parameter->est_conforme)
                ->count(),
            'parametres' => $aiParameters,
        ];

        $result = $this->aiSummary($sample, $payload);

        if ($result === null) {
            return null;
        }

        return [
            'resume' => $result['resume'],
            'anomalies' => $result['anomalies'],
            'genere_le' => $result['genere_le'] ?? now()->format('d/m/Y H:i'),
        ];
    }

    /**
     * Synthèse publique d'une zone (dernier prélèvement, tendance, alertes).
     *
     * @return array{latest: WaterQualitySample|null, score_moyen: float|null, tendance: string, alertes_actives: int, conformite_90j: float|null}
     */
    public function zoneSummary(Zone $zone): array
    {
        $latest = WaterQualitySample::query()
            ->where('zone_id', $zone->id)
            ->with('parameters')
            ->latest('date_prelevement')
            ->first();

        $recent = WaterQualitySample::query()
            ->where('zone_id', $zone->id)
            ->whereNotNull('score')
            ->latest('date_prelevement')
            ->limit(6)
            ->get();

        $scoreMoyen = $recent->isNotEmpty() ? round((float) $recent->avg('score'), 2) : null;
        $tendance = 'stable';

        if ($recent->count() >= 3) {
            $half = intdiv($recent->count(), 2);
            $recentAvg = (float) $recent->take($half)->avg('score');
            $olderAvg = (float) $recent->slice($half)->avg('score');

            if ($recentAvg > $olderAvg + 2) {
                $tendance = 'amelioration';
            } elseif ($recentAvg < $olderAvg - 2) {
                $tendance = 'degradation';
            }
        }

        $total90 = WaterQualitySample::query()
            ->where('zone_id', $zone->id)
            ->where('date_prelevement', '>=', Carbon::now()->subDays(90))
            ->whereIn('statut', ['conforme', 'non_conforme'])
            ->count();

        $conformes90 = WaterQualitySample::query()
            ->where('zone_id', $zone->id)
            ->where('date_prelevement', '>=', Carbon::now()->subDays(90))
            ->where('statut', 'conforme')
            ->count();

        return [
            'latest' => $latest,
            'score_moyen' => $scoreMoyen,
            'tendance' => $tendance,
            'alertes_actives' => WaterQualityAlert::where('zone_id', $zone->id)->where('statut', 'active')->count(),
            'conformite_90j' => $total90 > 0 ? round(($conformes90 / $total90) * 100, 1) : null,
        ];
    }

    /**
     * Indicateurs réels du module qualité, indépendants des filtres d'affichage.
     *
     * @return array{total: int, conformes: int, non_conformes: int, alertes_actives: int, dernier_prelevement: Carbon|null}
     */
    public function statistics(): array
    {
        $counts = WaterQualitySample::query()
            ->select('statut', DB::raw('COUNT(*) as total'))
            ->groupBy('statut')
            ->pluck('total', 'statut');

        $latest = WaterQualitySample::query()
            ->orderByDesc('date_prelevement')
            ->first(['date_prelevement']);

        return [
            'total' => (int) $counts->sum(),
            'conformes' => (int) ($counts['conforme'] ?? 0),
            'non_conformes' => (int) ($counts['non_conforme'] ?? 0),
            'alertes_actives' => WaterQualityAlert::query()->where('statut', 'active')->count(),
            'dernier_prelevement' => $latest?->date_prelevement,
        ];
    }

    /**
     * Mesures réelles des derniers prélèvements, paramètre par paramètre.
     *
     * @return list<array{parametre: string, label: string, unite: string, points: list<array{label: string, valeur: float, conforme: bool, seuil_min: float|null, seuil_max: float|null}>, min: float, max: float, seuil_min: float|null, seuil_max: float|null, dernier: array{label: string, valeur: float, conforme: bool, seuil_min: float|null, seuil_max: float|null}|null}>
     */
    public function parameterTrends(?Zone $zone = null, int $limit = 12): array
    {
        $samples = WaterQualitySample::query()
            ->with('parameters')
            ->when($zone, fn ($query) => $query->where('zone_id', $zone->id))
            ->orderByDesc('date_prelevement')
            ->limit(max(1, $limit))
            ->get()
            ->sortBy(fn (WaterQualitySample $sample) => $sample->date_prelevement?->getTimestamp() ?? 0)
            ->values();

        $series = [];

        foreach ($samples as $sample) {
            $date = $sample->date_prelevement?->format('d/m/Y') ?? '—';

            foreach ($sample->parameters as $parameter) {
                $code = (string) $parameter->parametre;

                $series[$code] ??= [
                    'parametre' => $code,
                    'label' => QualityThreshold::PARAMETRES[$code] ?? ucfirst($code),
                    'unite' => (string) $parameter->unite,
                    'points' => [],
                ];

                if (filled($parameter->unite)) {
                    $series[$code]['unite'] = (string) $parameter->unite;
                }

                $series[$code]['points'][] = [
                    'label' => $date,
                    'valeur' => (float) $parameter->valeur,
                    'conforme' => (bool) $parameter->est_conforme,
                    'seuil_min' => $parameter->seuil_min !== null ? (float) $parameter->seuil_min : null,
                    'seuil_max' => $parameter->seuil_max !== null ? (float) $parameter->seuil_max : null,
                ];
            }
        }

        $ordered = [];

        foreach (QualityThreshold::PARAMETRES as $code => $label) {
            if (isset($series[$code])) {
                $ordered[] = $this->finalizeTrend($series[$code]);
                unset($series[$code]);
            }
        }

        foreach ($series as $trend) {
            $ordered[] = $this->finalizeTrend($trend);
        }

        return $ordered;
    }

    /**
     * @param  array{parametre: string, label: string, unite: string, points: list<array{label: string, valeur: float, conforme: bool, seuil_min: float|null, seuil_max: float|null}>}  $trend
     * @return array{parametre: string, label: string, unite: string, points: list<array{label: string, valeur: float, conforme: bool, seuil_min: float|null, seuil_max: float|null}>, min: float, max: float, seuil_min: float|null, seuil_max: float|null, dernier: array{label: string, valeur: float, conforme: bool, seuil_min: float|null, seuil_max: float|null}|null}
     */
    private function finalizeTrend(array $trend): array
    {
        $values = array_column($trend['points'], 'valeur');
        $lastKey = array_key_last($trend['points']);

        $seuilMin = null;
        $seuilMax = null;

        foreach (array_reverse($trend['points']) as $point) {
            $seuilMin ??= $point['seuil_min'];
            $seuilMax ??= $point['seuil_max'];

            if ($seuilMin !== null && $seuilMax !== null) {
                break;
            }
        }

        $trend['min'] = $values === [] ? 0.0 : (float) min($values);
        $trend['max'] = $values === [] ? 0.0 : (float) max($values);
        $trend['seuil_min'] = $seuilMin;
        $trend['seuil_max'] = $seuilMax;
        $trend['dernier'] = $lastKey !== null ? $trend['points'][$lastKey] : null;

        return $trend;
    }

    /**
     * Prélèvements d'un périmètre, du plus ancien au plus récent, avec mesures et alertes.
     * Un limite à null charge l'historique complet du périmètre.
     *
     * @return Collection<int, WaterQualitySample>
     */
    public function zoneHistory(Zone $zone, ?int $limit = 20): Collection
    {
        $query = WaterQualitySample::query()
            ->where('zone_id', $zone->id)
            ->with([
                'parameters' => fn ($query) => $query->orderBy('id'),
                'alerts',
            ])
            ->orderByDesc('date_prelevement');

        if ($limit !== null) {
            $query->limit(max(1, $limit));
        }

        return $query
            ->get()
            ->sortBy(fn (WaterQualitySample $sample) => $sample->date_prelevement?->getTimestamp() ?? 0)
            ->values();
    }

    /**
     * Données complètes d'un périmètre pour le rapport PDF.
     * Toutes les requêtes sont strictement limitées à la zone fournie.
     *
     * @return array{
     *     zone: Zone,
     *     summary: array<string, mixed>,
     *     latest: WaterQualitySample|null,
     *     history: Collection<int, WaterQualitySample>,
     *     alerts: Collection<int, WaterQualityAlert>,
     *     trendDisponible: bool,
     *     counts: array{total: int, conformes: int, non_conformes: int, en_attente: int},
     *     firstDate: Carbon|null,
     *     lastDate: Carbon|null
     * }
     */
    public function zoneReport(Zone $zone): array
    {
        $history = $this->zoneHistory($zone, null);
        $summary = $this->zoneSummary($zone);

        $alerts = WaterQualityAlert::query()
            ->where('zone_id', $zone->id)
            ->with('sample')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return [
            'zone' => $zone,
            'summary' => $summary,
            'latest' => $summary['latest'],
            'history' => $history,
            'alerts' => $alerts,
            'trendDisponible' => $history->take(-6)->whereNotNull('score')->count() >= 3,
            'counts' => [
                'total' => $history->count(),
                'conformes' => $history->where('statut', 'conforme')->count(),
                'non_conformes' => $history->where('statut', 'non_conforme')->count(),
                'en_attente' => $history->where('statut', 'en_attente')->count(),
            ],
            'firstDate' => $history->first()?->date_prelevement,
            'lastDate' => $history->last()?->date_prelevement,
        ];
    }

    public function thresholdFor(WaterQualitySample $sample, string $parametre): ?QualityThreshold
    {
        return QualityThreshold::query()
            ->where('parametre', $parametre)
            ->where('actif', true)
            ->where(fn ($query) => $query->whereNull('zone_id')->orWhere('zone_id', $sample->zone_id))
            ->orderByRaw('zone_id IS NULL')
            ->first();
    }

    /**
     * @param  list<array{parametre: string, valeur: float|int|string, unite?: string}>  $parameters
     * @return array{statut: string, score: float, non_conformes: int, alertes: int, ai_payload: array<string, mixed>}
     */
    private function analyzeMeasurements(WaterQualitySample $sample, array $parameters): array
    {
        $sample->parameters()->delete();

        $activeAlerts = $sample->alerts()->where('statut', 'active')->get()->keyBy('parametre');
        $keptAlertIds = [];
        $nonConformes = 0;
        $alertes = 0;
        $aiParameters = [];

        foreach ($parameters as $row) {
            $parametre = (string) $row['parametre'];
            $valeur = (float) $row['valeur'];
            $threshold = $this->thresholdFor($sample, $parametre);
            $defaults = self::DEFAULT_THRESHOLDS[$parametre] ?? ['min' => null, 'max' => null, 'unite' => $row['unite'] ?? ''];

            $seuilMin = $threshold?->valeur_min !== null ? (float) $threshold->valeur_min : $defaults['min'];
            $seuilMax = $threshold?->valeur_max !== null ? (float) $threshold->valeur_max : $defaults['max'];
            $unite = $threshold?->unite ?: ($row['unite'] ?? $defaults['unite']);

            $conforme = ($seuilMin === null || $valeur >= $seuilMin)
                && ($seuilMax === null || $valeur <= $seuilMax);

            WaterQualityParameter::create([
                'sample_id' => $sample->id,
                'parametre' => $parametre,
                'valeur' => $valeur,
                'unite' => $unite,
                'seuil_min' => $seuilMin,
                'seuil_max' => $seuilMax,
                'est_conforme' => $conforme,
            ]);

            $aiParameters[] = [
                'code' => $parametre,
                'parametre' => QualityThreshold::PARAMETRES[$parametre] ?? $parametre,
                'valeur' => $valeur,
                'unite' => $unite,
                'seuil_min' => $seuilMin,
                'seuil_max' => $seuilMax,
                'origine_seuil' => $threshold ? 'base_de_donnees' : 'valeur_par_defaut',
                'conforme' => $conforme,
            ];

            if (! $conforme) {
                $nonConformes++;

                $niveau = $this->alertLevel($parametre, $valeur, $seuilMin, $seuilMax);
                $alertData = [
                    'zone_id' => $sample->zone_id,
                    'niveau' => $niveau,
                    'message' => $this->alertMessage($parametre, $valeur, $seuilMin, $seuilMax, $unite, $sample->zone?->nom),
                    'statut' => 'active',
                    'resolue_at' => null,
                ];

                $alert = $activeAlerts->get($parametre);

                if ($alert) {
                    $alert->update($alertData);
                } else {
                    $alert = WaterQualityAlert::create($alertData + [
                        'sample_id' => $sample->id,
                        'parametre' => $parametre,
                    ]);
                }

                $keptAlertIds[] = $alert->id;
                $alertes++;
            }
        }

        $alertsToResolve = $sample->alerts()->where('statut', 'active');

        if ($keptAlertIds !== []) {
            $alertsToResolve->whereNotIn('id', $keptAlertIds);
        }

        $alertsToResolve->update([
            'statut' => 'resolue',
            'resolue_at' => now(),
        ]);

        $total = count($parameters);
        $score = $total > 0 ? round((($total - $nonConformes) / $total) * 100, 2) : 0.0;
        $statut = match (true) {
            $total === 0 => 'en_attente',
            $nonConformes > 0 => 'non_conforme',
            default => 'conforme',
        };

        $sample->update(['score' => $score, 'statut' => $statut]);

        return [
            'statut' => $statut,
            'score' => (float) $score,
            'non_conformes' => $nonConformes,
            'alertes' => $alertes,
            'ai_payload' => [
                'zone' => $sample->zone?->nom,
                'date_prelevement' => $sample->date_prelevement?->toIso8601String(),
                'statut' => $statut,
                'score' => (float) $score,
                'non_conformes' => $nonConformes,
                'parametres' => $aiParameters,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{resume: string, anomalies: list<string>}|null
     */
    private function aiSummary(WaterQualitySample $sample, array $payload): ?array
    {
        $apiKey = config('services.gemini.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '' || ($payload['parametres'] ?? []) === []) {
            return null;
        }

        $hash = hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $cacheKey = self::AI_SUMMARY_CACHE_PREFIX.$sample->id;
        $cached = Cache::get($cacheKey);

        if (is_array($cached) && ($cached['hash'] ?? null) === $hash) {
            return $this->cachedSummary($sample);
        }

        $result = $this->requestAiSummary($payload);

        if ($result === null) {
            Cache::put($cacheKey, [
                'hash' => $hash,
                'resume' => null,
                'anomalies' => [],
                'genere_le' => null,
            ], now()->addMinutes(self::AI_UNAVAILABLE_TTL_MINUTES));

            return null;
        }

        $entry = [
            'hash' => $hash,
            'resume' => $result['resume'],
            'anomalies' => $result['anomalies'],
            'genere_le' => now()->format('d/m/Y H:i'),
        ];

        Cache::put($cacheKey, $entry, now()->addDays(self::AI_SUMMARY_TTL_DAYS));

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{resume: string, anomalies: list<string>}|null
     */
    private function requestAiSummary(array $payload): ?array
    {
        $model = (string) config('services.gemini.model', 'gemini-2.5-flash');
        $timeout = max(5, (int) config('services.gemini.timeout', 12));

        $generationConfig = [
            'temperature' => 0.2,
            'maxOutputTokens' => 1024,
            'responseMimeType' => 'application/json',
            'responseSchema' => [
                'type' => 'OBJECT',
                'properties' => [
                    'resume' => ['type' => 'STRING'],
                    'anomalies' => [
                        'type' => 'ARRAY',
                        'items' => ['type' => 'STRING'],
                    ],
                ],
                'required' => ['resume', 'anomalies'],
            ],
        ];

        if ($model === 'gemini-2.5-flash') {
            $generationConfig['thinkingConfig'] = ['thinkingBudget' => 0];
        }

        $caBundle = config('services.gemini.ca_bundle');

        try {
            $request = Http::withHeaders(['x-goog-api-key' => (string) config('services.gemini.api_key')])
                ->acceptJson()
                ->timeout($timeout)
                ->connectTimeout(min(5, $timeout));

            if (is_string($caBundle) && $caBundle !== '') {
                $request = $request->withOptions(['verify' => $caBundle]);
            }

            $response = $request->post(
                'https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':generateContent',
                [
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [['text' => $this->aiPrompt($payload)]],
                    ]],
                    'generationConfig' => $generationConfig,
                ]
            );
        } catch (ConnectionException $exception) {
            Log::warning('Gemini quality summary connection failed.', [
                'exception' => $exception::class,
                'code' => $exception->getCode(),
                'timeout' => $timeout,
                'message' => mb_substr($exception->getMessage(), 0, 300),
            ]);

            return null;
        } catch (Throwable $exception) {
            Log::warning('Gemini quality summary request failed.', [
                'exception' => $exception::class,
                'code' => $exception->getCode(),
                'message' => mb_substr($exception->getMessage(), 0, 300),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Gemini quality summary request was rejected.', [
                'status' => $response->status(),
                'error_status' => $response->json('error.status'),
            ]);

            return null;
        }

        $text = $this->extractResponseText($response->json('candidates.0.content.parts', []));
        $decoded = $text !== null ? $this->decodeAiJson($text) : null;

        if ($decoded === null) {
            Log::warning('Gemini quality summary returned an invalid payload.');

            return null;
        }

        $resume = is_string($decoded['resume'] ?? null) ? trim($decoded['resume']) : '';

        if ($resume === '') {
            return null;
        }

        $anomalies = collect($decoded['anomalies'] ?? [])
            ->filter(fn ($anomaly) => is_string($anomaly) && trim($anomaly) !== '')
            ->map(fn (string $anomaly) => trim($anomaly))
            ->take(10)
            ->values()
            ->all();

        return [
            'resume' => $resume,
            'anomalies' => $anomalies,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $parts
     */
    private function extractResponseText(array $parts): ?string
    {
        foreach ($parts as $part) {
            if (($part['thought'] ?? false) === true) {
                continue;
            }

            if (is_string($part['text'] ?? null) && trim($part['text']) !== '') {
                return $part['text'];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeAiJson(string $text): ?array
    {
        $text = trim($text);

        if (str_starts_with($text, '```')) {
            $text = (string) preg_replace('/^```(?:json)?\s*|\s*```$/', '', $text);
        }

        $decoded = json_decode(trim($text), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function aiPrompt(array $payload): string
    {
        $data = (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
Tu es un assistant qui aide des citoyens non techniques à comprendre la qualité de l'eau potable.
Analyse uniquement les données du prélèvement fournies ci-dessous. N'invente aucune valeur, aucun seuil et aucune réglementation.
Les seuils indiqués sont ceux réellement appliqués par le système ; les dépassements ont déjà été calculés côté serveur : utilise-les comme des faits.

Données du prélèvement (JSON) :
{$data}

Ta mission :
1. Identifier les valeurs inhabituelles, les dépassements de seuil et les combinaisons de mesures potentiellement préoccupantes.
2. Rédiger un résumé en français simple, compréhensible par un citoyen, en 120 mots maximum.
3. Présenter les paramètres préoccupants et leurs valeurs mesurées.
4. Expliquer simplement les anomalies et leurs risques potentiels.
5. Si aucun dépassement ni anomalie n'est détecté, l'indiquer clairement.
6. Si des résultats sont préoccupants, recommander une vérification par les responsables de la qualité de l'eau.
7. Ne jamais affirmer que l'eau est potable au seul motif qu'aucun dépassement n'a été détecté.
8. Ne mentionner aucune donnée personnelle.

Réponds uniquement avec un objet JSON valide contenant :
- "resume" : le résumé citoyen en français ;
- "anomalies" : un tableau de phrases courtes décrivant chaque anomalie détectée (tableau vide si aucune).
PROMPT;
    }

    private function alertLevel(string $parametre, float $valeur, ?float $min, ?float $max): string
    {
        if (in_array($parametre, ['bacteries', 'plomb'], true)) {
            return 'critique';
        }

        $reference = $max !== null && $max > 0 ? $max : ($min ?? 1);
        $depassement = $reference > 0 ? abs($valeur - $reference) / $reference : 0;

        return $depassement >= 0.5 ? 'critique' : 'avertissement';
    }

    private function alertMessage(string $parametre, float $valeur, ?float $min, ?float $max, string $unite, ?string $zone): string
    {
        $label = QualityThreshold::PARAMETRES[$parametre] ?? $parametre;
        $bornes = match (true) {
            $min !== null && $max !== null => "plage attendue {$min} – {$max} {$unite}",
            $min !== null => "minimum attendu {$min} {$unite}",
            $max !== null => "maximum attendu {$max} {$unite}",
            default => 'aucun seuil configuré',
        };

        return sprintf(
            '%s : valeur mesurée %s %s (%s)%s.',
            $label,
            rtrim(rtrim(number_format($valeur, 4, ',', ''), '0'), ','),
            $unite,
            $bornes,
            $zone ? " — zone {$zone}" : '',
        );
    }
}
