<?php

namespace App\Services;

use App\Models\Incident\Incident;
use App\Models\Incident\IncidentSimilarity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Détection des incidents similaires.
 *
 * Étape A : présélection SQL bornée (même zone, fenêtre temporelle, candidats
 * non rejetés, limite configurable).
 * Étape B : score local via SimilarityScorer, seuil et nombre maximal de
 * suggestions configurables.
 * Étape C : confirmation IA optionnelle, jamais bloquante.
 *
 * Le service ne modifie jamais le statut d'un incident. Les suggestions sont
 * uniquement persistées avec le statut « suggeree » ; la décision appartient
 * au gestionnaire.
 */
class IncidentSimilarityService
{
    public function __construct(
        private readonly SimilarityScorer $scorer,
        private readonly GroqAIService $ai,
    ) {}

    /**
     * Relance l'analyse et remplace les suggestions encore en attente.
     * Les suggestions déjà décidées sont conservées.
     *
     * @return Collection<int, IncidentSimilarity>
     */
    public function detect(Incident $incident): Collection
    {
        return $this->persist($incident, $this->analyze($incident));
    }

    /**
     * Analyse sans écrire en base (utilisée par le mode --dry-run).
     *
     * @return list<array{
     *     incident: Incident,
     *     score: int,
     *     ai: array{
     *         candidate_id: int,
     *         is_same_issue: bool,
     *         confidence: int,
     *         justification: string,
     *         suggested_type: string|null,
     *         suggested_severity: string|null
     *     }|null,
     *     ai_attempted: bool,
     *     ai_failure: string|null
     * }>
     */
    public function analyze(Incident $incident, bool $allowAi = true): array
    {
        $minimumScore = max(0, min(100, (int) config('incident_similarity.min_score', 60)));
        $maximumSuggestions = max(1, (int) config('incident_similarity.max_suggestions', 5));

        $scored = [];

        foreach ($this->candidates($incident) as $candidate) {
            $score = $this->scorer->score($incident, $candidate);

            if ($score >= $minimumScore) {
                $scored[] = [
                    'incident' => $candidate,
                    'score' => $score,
                    'ai' => null,
                    'ai_attempted' => false,
                    'ai_failure' => null,
                ];
            }
        }

        usort($scored, fn (array $left, array $right) => $right['score'] <=> $left['score']);

        $scored = array_slice($scored, 0, $maximumSuggestions);

        if ($allowAi && $this->ai->enabled()) {
            $aiLimit = min(count($scored), max(1, (int) config('incident_similarity.ai_max_candidates', 5)));
            $aiCandidates = array_slice(array_column($scored, 'incident'), 0, $aiLimit);

            $batch = $this->ai->confirmBatch($incident, $aiCandidates);

            for ($index = 0; $index < $aiLimit; $index++) {
                $candidateId = (int) $scored[$index]['incident']->getKey();

                $scored[$index]['ai_attempted'] = $batch['attempted'];
                $scored[$index]['ai'] = $batch['results'][$candidateId] ?? null;
                $scored[$index]['ai_failure'] = $batch['failures'][$candidateId] ?? null;
            }
        }

        return $scored;
    }

    /**
     * @return Collection<int, Incident>
     */
    public function candidates(Incident $incident): Collection
    {
        $windowDays = max(1, (int) config('incident_similarity.window_days', 30));
        $limit = max(1, (int) config('incident_similarity.max_candidates', 50));
        $reference = $incident->reported_at ?? now();

        return Incident::query()
            ->whereKeyNot($incident->id)
            ->where('status', '!=', 'rejete')
            ->whereBetween('reported_at', [
                $reference->copy()->subDays($windowDays),
                $reference->copy()->addDays($windowDays),
            ])
            ->when(
                $incident->zone_id !== null,
                fn ($query) => $query->where('zone_id', $incident->zone_id),
            )
            ->when(
                $incident->zone_id === null && $incident->infrastructure_id !== null,
                fn ($query) => $query->where('infrastructure_id', $incident->infrastructure_id),
            )
            ->latest('reported_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  list<array{
     *     incident: Incident,
     *     score: int,
     *     ai: array{
     *         candidate_id: int,
     *         is_same_issue: bool,
     *         confidence: int,
     *         justification: string,
     *         suggested_type: string|null,
     *         suggested_severity: string|null
     *     }|null,
     *     ai_attempted: bool,
     *     ai_failure: string|null
     * }>  $suggestions
     * @return Collection<int, IncidentSimilarity>
     */
    public function persist(Incident $incident, array $suggestions): Collection
    {
        return DB::transaction(function () use ($incident, $suggestions): Collection {
            $locked = Incident::query()->whereKey($incident->getKey())->lockForUpdate()->first();

            if ($locked === null) {
                return new Collection;
            }

            IncidentSimilarity::query()
                ->where('incident_id', $incident->getKey())
                ->where('status', IncidentSimilarity::STATUS_SUGGESTED)
                ->delete();

            $decided = IncidentSimilarity::query()
                ->where('incident_id', $incident->getKey())
                ->pluck('similar_incident_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $created = new Collection;

            foreach ($suggestions as $suggestion) {
                $candidateId = (int) $suggestion['incident']->getKey();

                if ($candidateId === (int) $incident->getKey() || in_array($candidateId, $decided, true)) {
                    continue;
                }

                $ai = $suggestion['ai'] ?? null;
                $aiAttempted = (bool) ($suggestion['ai_attempted'] ?? false);
                $aiFailure = $suggestion['ai_failure'] ?? null;

                $method = match (true) {
                    is_array($ai) && $ai['is_same_issue'] => IncidentSimilarity::METHOD_LOCAL_AI,
                    is_array($ai) => IncidentSimilarity::METHOD_LOCAL_AI_NEGATIVE,
                    $aiAttempted => IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE,
                    default => IncidentSimilarity::METHOD_LOCAL,
                };

                $created->push(IncidentSimilarity::create([
                    'incident_id' => $incident->getKey(),
                    'similar_incident_id' => $candidateId,
                    'score' => $suggestion['score'],
                    'method' => $method,
                    'ai_justification' => match (true) {
                        is_array($ai) => $this->formatAiJustification($ai),
                        $aiAttempted => GroqAIService::failureJustification(
                            is_string($aiFailure) ? $aiFailure : GroqAIService::FAILURE_UNAVAILABLE,
                        ),
                        default => null,
                    },
                    'status' => IncidentSimilarity::STATUS_SUGGESTED,
                ]));
            }

            return $created;
        }, 3);
    }

    /**
     * Signal « problème récurrent » : au moins N incidents similaires dans la
     * même zone sur la période configurée.
     *
     * @return array{count: int, threshold: int, window_days: int, is_recurring: bool}
     */
    public function recurringProblem(Incident $incident): array
    {
        $threshold = max(2, (int) config('incident_similarity.recurring_threshold', 3));
        $windowDays = max(1, (int) config('incident_similarity.window_days', 30));

        $count = 0;

        if ($incident->zone_id !== null) {
            $count = IncidentSimilarity::query()
                ->where('incident_id', $incident->getKey())
                ->whereIn('status', IncidentSimilarity::COUNTED_STATUSES)
                ->whereHas('similarIncident', fn ($query) => $query
                    ->where('zone_id', $incident->zone_id)
                    ->where('reported_at', '>=', now()->subDays($windowDays)))
                ->pluck('similar_incident_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->count();
        }

        return [
            'count' => $count,
            'threshold' => $threshold,
            'window_days' => $windowDays,
            'is_recurring' => $count >= $threshold,
        ];
    }

    /**
     * Rejoue uniquement la confirmation IA des suggestions encore en attente
     * dont la méthode est local_ai_unavailable. Les suggestions déjà décidées
     * par un gestionnaire ou déjà confirmées par l'IA ne sont jamais touchées.
     *
     * @return array{
     *     enabled: bool,
     *     processed: int,
     *     confirmed: int,
     *     negative: int,
     *     fallback: int,
     *     reasons: array<string, int>
     * }
     */
    public function retryFailedAi(): array
    {
        $summary = [
            'enabled' => $this->ai->enabled(),
            'processed' => 0,
            'confirmed' => 0,
            'negative' => 0,
            'fallback' => 0,
            'reasons' => [],
        ];

        if (! $summary['enabled']) {
            return $summary;
        }

        $groups = IncidentSimilarity::query()
            ->where('status', IncidentSimilarity::STATUS_SUGGESTED)
            ->where('method', IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE)
            ->with(['incident', 'similarIncident'])
            ->orderBy('incident_id')
            ->orderByDesc('score')
            ->get()
            ->groupBy('incident_id');

        foreach ($groups as $group) {
            $incident = $group->first()?->incident;

            if ($incident === null) {
                continue;
            }

            foreach ($group->chunk(5) as $chunk) {
                $candidates = $chunk
                    ->map(fn (IncidentSimilarity $row) => $row->similarIncident)
                    ->filter()
                    ->values()
                    ->all();

                if ($candidates === []) {
                    continue;
                }

                $batch = $this->ai->confirmBatch($incident, $candidates);

                if (! $batch['attempted']) {
                    break;
                }

                foreach ($chunk as $row) {
                    $candidateId = (int) $row->similar_incident_id;
                    $ai = $batch['results'][$candidateId] ?? null;

                    if (is_array($ai)) {
                        $updated = IncidentSimilarity::query()
                            ->whereKey($row->getKey())
                            ->where('status', IncidentSimilarity::STATUS_SUGGESTED)
                            ->where('method', IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE)
                            ->update([
                                'method' => $ai['is_same_issue']
                                    ? IncidentSimilarity::METHOD_LOCAL_AI
                                    : IncidentSimilarity::METHOD_LOCAL_AI_NEGATIVE,
                                'ai_justification' => $this->formatAiJustification($ai),
                                'updated_at' => now(),
                            ]);

                        if ($updated === 0) {
                            continue;
                        }

                        $summary['processed']++;

                        if ($ai['is_same_issue']) {
                            $summary['confirmed']++;
                        } else {
                            $summary['negative']++;
                        }

                        continue;
                    }

                    $reason = $batch['failures'][$candidateId] ?? GroqAIService::FAILURE_UNAVAILABLE;

                    $updated = IncidentSimilarity::query()
                        ->whereKey($row->getKey())
                        ->where('status', IncidentSimilarity::STATUS_SUGGESTED)
                        ->where('method', IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE)
                        ->update([
                            'ai_justification' => GroqAIService::failureJustification($reason),
                            'updated_at' => now(),
                        ]);

                    if ($updated === 0) {
                        continue;
                    }

                    $summary['processed']++;
                    $summary['fallback']++;
                    $summary['reasons'][$reason] = ($summary['reasons'][$reason] ?? 0) + 1;
                }
            }
        }

        return $summary;
    }

    /**
     * @param  array{
     *     candidate_id: int,
     *     is_same_issue: bool,
     *     confidence: int,
     *     justification: string,
     *     suggested_type: string|null,
     *     suggested_severity: string|null
     * }  $ai
     */
    private function formatAiJustification(array $ai): string
    {
        $justification = sprintf(
            '%s. Indice déclaré par le modèle : %d/100 (non calibré, avis indicatif). %s',
            $ai['is_same_issue'] ? 'IA : même problème probable' : 'IA : problème probablement différent',
            $ai['confidence'],
            $ai['justification'],
        );

        $proposals = [];

        if ($ai['suggested_type'] !== null) {
            $proposals[] = 'type '.Incident::TYPES[$ai['suggested_type']];
        }

        if ($ai['suggested_severity'] !== null) {
            $proposals[] = 'gravité '.Incident::SEVERITIES[$ai['suggested_severity']];
        }

        if ($proposals !== []) {
            $justification .= ' Proposition indicative (non appliquée) : '.implode(', ', $proposals).'.';
        }

        return $justification;
    }
}
