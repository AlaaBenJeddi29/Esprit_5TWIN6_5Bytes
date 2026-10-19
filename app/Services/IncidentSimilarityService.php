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
        private readonly GrokAIService $ai,
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
     *     ai: array{candidate_id: int, is_same_issue: bool, confidence: int, justification: string}|null,
     *     ai_attempted: bool
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
                ];
            }
        }

        usort($scored, fn (array $left, array $right) => $right['score'] <=> $left['score']);

        $scored = array_slice($scored, 0, $maximumSuggestions);

        if ($allowAi && $this->ai->enabled()) {
            $aiLimit = min(count($scored), max(1, (int) config('incident_similarity.ai_max_candidates', 5)));

            for ($index = 0; $index < $aiLimit; $index++) {
                $scored[$index]['ai_attempted'] = true;
                $scored[$index]['ai'] = $this->ai->confirm($incident, $scored[$index]['incident']);
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
     *     ai: array{candidate_id: int, is_same_issue: bool, confidence: int, justification: string}|null,
     *     ai_attempted: bool
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
                        $aiAttempted => 'Analyse IA indisponible ; la suggestion locale est conservée.',
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
     * @param  array{candidate_id: int, is_same_issue: bool, confidence: int, justification: string}  $ai
     */
    private function formatAiJustification(array $ai): string
    {
        return sprintf(
            '%s. Indice déclaré par le modèle : %d/100 (non calibré, avis indicatif). %s',
            $ai['is_same_issue'] ? 'IA : même problème probable' : 'IA : problème probablement différent',
            $ai['confidence'],
            $ai['justification'],
        );
    }
}
