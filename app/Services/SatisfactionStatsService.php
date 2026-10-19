<?php

namespace App\Services;

use App\Models\Incident\IncidentFeedback;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Indicateurs de satisfaction citoyenne.
 *
 * Formules :
 * - note moyenne = somme des notes / nombre d'évaluations (null si aucune) ;
 * - taux d'acceptation = réclamations acceptées / (acceptées + refusées)
 *   × 100. Les dossiers en attente, en examen ou clôturés sans décision
 *   n'entrent pas au dénominateur ; null si aucune décision rendue ;
 * - réclamations en attente = statut « en_attente » uniquement ;
 * - réouvertures acceptées = réclamations ayant effectivement déclenché une
 *   réouverture (reopened_at non nul).
 *
 * La période porte sur la date de dépôt de l'évaluation (created_at).
 */
class SatisfactionStatsService
{
    /**
     * @return array{
     *     total_feedback: int,
     *     average_rating: float|null,
     *     rating_distribution: array<int, int>,
     *     resolution_confirmed: int,
     *     resolution_confirmation_rate: float|null,
     *     complaints_total: int,
     *     complaints_pending: int,
     *     complaints_in_review: int,
     *     complaints_accepted: int,
     *     complaints_rejected: int,
     *     acceptance_rate: float|null,
     *     reopen_requested: int,
     *     reopen_accepted: int,
     *     evolution: list<array{period: string, label: string, total: int, average_rating: float|null}>,
     *     by_zone: list<array{zone_id: int|null, zone: string, total: int, average_rating: float|null, complaints: int}>
     * }
     */
    public function summary(?string $from = null, ?string $to = null): array
    {
        [$start, $end] = $this->periodBounds($from, $to);

        $total = $this->periodQuery($start, $end)->count();
        $average = $this->periodQuery($start, $end)->avg('rating');
        $accepted = $this->periodQuery($start, $end)->where('complaint_decision', 'acceptee')->count();
        $rejected = $this->periodQuery($start, $end)->where('complaint_decision', 'rejetee')->count();
        $decisions = $accepted + $rejected;

        $distribution = array_fill(1, 5, 0);

        $this->periodQuery($start, $end)
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating')
            ->each(function ($count, $rating) use (&$distribution): void {
                $rating = (int) $rating;

                if ($rating >= 1 && $rating <= 5) {
                    $distribution[$rating] = (int) $count;
                }
            });

        $confirmed = $this->periodQuery($start, $end)->where('resolution_confirmed', true)->count();

        return [
            'total_feedback' => $total,
            'average_rating' => $total > 0 && $average !== null ? round((float) $average, 2) : null,
            'rating_distribution' => $distribution,
            'resolution_confirmed' => $confirmed,
            'resolution_confirmation_rate' => $total > 0 ? round(($confirmed / $total) * 100, 1) : null,
            'complaints_total' => $this->periodQuery($start, $end)->whereNotNull('complaint_status')->count(),
            'complaints_pending' => $this->periodQuery($start, $end)->where('complaint_status', 'en_attente')->count(),
            'complaints_in_review' => $this->periodQuery($start, $end)->where('complaint_status', 'en_examen')->count(),
            'complaints_accepted' => $accepted,
            'complaints_rejected' => $rejected,
            'acceptance_rate' => $decisions > 0 ? round(($accepted / $decisions) * 100, 1) : null,
            'reopen_requested' => $this->periodQuery($start, $end)->where('reopen_requested', true)->count(),
            'reopen_accepted' => $this->periodQuery($start, $end)->whereNotNull('reopened_at')->count(),
            'evolution' => $this->evolution($start, $end),
            'by_zone' => $this->byZone($start, $end),
        ];
    }

    /**
     * @return list<array{period: string, label: string, total: int, average_rating: float|null}>
     */
    private function evolution(?Carbon $start, ?Carbon $end): array
    {
        return $this->periodQuery($start, $end)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period, COUNT(*) as total, AVG(rating) as average_rating")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => [
                'period' => (string) $row->period,
                'label' => Carbon::createFromFormat('Y-m', (string) $row->period)->format('m/Y'),
                'total' => (int) $row->total,
                'average_rating' => $row->average_rating !== null ? round((float) $row->average_rating, 2) : null,
            ])
            ->all();
    }

    /**
     * Satisfaction par zone, toutes les zones étant listées même sans
     * évaluation sur la période. Les évaluations sans zone sont regroupées
     * dans une ligne « Sans zone » lorsqu'il en existe.
     *
     * @return list<array{zone_id: int|null, zone: string, total: int, average_rating: float|null, complaints: int}>
     */
    private function byZone(?Carbon $start, ?Carbon $end): array
    {
        $rows = DB::table('zones')
            ->leftJoin('incidents', 'incidents.zone_id', '=', 'zones.id')
            ->leftJoin('incident_feedbacks', function ($join) use ($start, $end) {
                $join->on('incident_feedbacks.incident_id', '=', 'incidents.id');

                if ($start !== null) {
                    $join->where('incident_feedbacks.created_at', '>=', $start);
                }

                if ($end !== null) {
                    $join->where('incident_feedbacks.created_at', '<=', $end);
                }
            })
            ->selectRaw(
                'zones.id as zone_id, zones.nom as zone, COUNT(incident_feedbacks.id) as total, '.
                'AVG(incident_feedbacks.rating) as average_rating, '.
                'SUM(CASE WHEN incident_feedbacks.complaint_status IS NOT NULL THEN 1 ELSE 0 END) as complaints',
            )
            ->groupBy('zones.id', 'zones.nom')
            ->orderBy('zones.nom')
            ->get()
            ->map(fn ($row) => [
                'zone_id' => (int) $row->zone_id,
                'zone' => (string) $row->zone,
                'total' => (int) $row->total,
                'average_rating' => $row->average_rating !== null ? round((float) $row->average_rating, 2) : null,
                'complaints' => (int) $row->complaints,
            ])
            ->all();

        $unzoned = $this->periodQuery($start, $end)
            ->whereHas('incident', fn (Builder $query) => $query->whereNull('zone_id'))
            ->selectRaw(
                'COUNT(*) as total, AVG(rating) as average_rating, '.
                'SUM(CASE WHEN complaint_status IS NOT NULL THEN 1 ELSE 0 END) as complaints',
            )
            ->first();

        if ($unzoned !== null && (int) $unzoned->total > 0) {
            $rows[] = [
                'zone_id' => null,
                'zone' => 'Sans zone',
                'total' => (int) $unzoned->total,
                'average_rating' => $unzoned->average_rating !== null ? round((float) $unzoned->average_rating, 2) : null,
                'complaints' => (int) $unzoned->complaints,
            ];
        }

        return $rows;
    }

    private function periodQuery(?Carbon $start, ?Carbon $end): Builder
    {
        return IncidentFeedback::query()
            ->when($start !== null, fn (Builder $query) => $query->where('created_at', '>=', $start))
            ->when($end !== null, fn (Builder $query) => $query->where('created_at', '<=', $end));
    }

    /**
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function periodBounds(?string $from, ?string $to): array
    {
        return [
            $from !== null && $from !== '' ? Carbon::parse($from)->startOfDay() : null,
            $to !== null && $to !== '' ? Carbon::parse($to)->endOfDay() : null,
        ];
    }
}
