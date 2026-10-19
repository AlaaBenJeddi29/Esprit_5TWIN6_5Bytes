<?php

namespace App\Services;

use App\Models\Incident\Incident;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Indicateurs du module Incidents.
 *
 * Tous les calculs portent sur les incidents dont `reported_at` tombe dans
 * la période demandée (bornes incluses, jour entier). Une borne nulle
 * signifie « pas de limite » de ce côté.
 */
class IncidentStatsService
{
    /**
     * @return array{
     *     critical_open: int,
     *     unassigned_open: int,
     *     avg_resolution_h: float|null,
     *     resolution_rate: float|null,
     *     by_zone: list<array{zone_id: int|null, zone: string, total: int, open: int, critical: int}>
     * }
     */
    public function summary(?string $from = null, ?string $to = null): array
    {
        [$start, $end] = $this->periodBounds($from, $to);

        return [
            'critical_open' => $this->criticalOpenWithin($start, $end),
            'unassigned_open' => $this->unassignedOpenWithin($start, $end),
            'avg_resolution_h' => $this->averageResolutionHours($start, $end),
            'resolution_rate' => $this->resolutionRate($start, $end),
            'by_zone' => $this->byZone($start, $end),
        ];
    }

    /**
     * Incidents critiques encore ouverts (hors `traite` et `rejete`).
     */
    public function criticalOpen(?string $from = null, ?string $to = null): int
    {
        [$start, $end] = $this->periodBounds($from, $to);

        return $this->criticalOpenWithin($start, $end);
    }

    /**
     * Incidents ouverts sans responsable, hors doublons signalés.
     */
    public function unassignedOpen(?string $from = null, ?string $to = null): int
    {
        [$start, $end] = $this->periodBounds($from, $to);

        return $this->unassignedOpenWithin($start, $end);
    }

    private function criticalOpenWithin(?Carbon $start, ?Carbon $end): int
    {
        return $this->periodQuery($start, $end)
            ->where('severity', 'critique')
            ->open()
            ->count();
    }

    private function unassignedOpenWithin(?Carbon $start, ?Carbon $end): int
    {
        return $this->periodQuery($start, $end)
            ->open()
            ->whereNull('assigned_to')
            ->where('is_duplicate', false)
            ->count();
    }

    /**
     * Délai moyen entre signalement et résolution, en heures.
     * Null tant qu'aucun incident traité ne possède les deux dates.
     */
    private function averageResolutionHours(?Carbon $start, ?Carbon $end): ?float
    {
        $row = $this->periodQuery($start, $end)
            ->where('status', 'traite')
            ->whereNotNull('reported_at')
            ->whereNotNull('resolved_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, reported_at, resolved_at)) as avg_seconds')
            ->first();

        if ($row === null || $row->avg_seconds === null) {
            return null;
        }

        return round(((float) $row->avg_seconds) / 3600, 1);
    }

    /**
     * Part des incidents traités sur les incidents recevables :
     * traite / (total - rejete - doublons) × 100.
     * Null si le dénominateur est nul ou négatif.
     */
    private function resolutionRate(?Carbon $start, ?Carbon $end): ?float
    {
        $row = $this->periodQuery($start, $end)
            ->selectRaw(
                'COUNT(*) as total, '.
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as treated, '.
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as rejected, '.
                'SUM(CASE WHEN is_duplicate = 1 THEN 1 ELSE 0 END) as duplicates',
                ['traite', 'rejete'],
            )
            ->first();

        $total = (int) ($row->total ?? 0);
        $treated = (int) ($row->treated ?? 0);
        $rejected = (int) ($row->rejected ?? 0);
        $duplicates = (int) ($row->duplicates ?? 0);

        $denominator = $total - $rejected - $duplicates;

        if ($denominator <= 0) {
            return null;
        }

        return round(($treated / $denominator) * 100, 1);
    }

    /**
     * Total, ouverts et critiques par zone. Toutes les zones sont listées,
     * même sans incident ; les signalements sans zone sont ajoutés dans une
     * ligne « Sans zone » lorsqu'il en existe sur la période.
     *
     * @return list<array{zone_id: int|null, zone: string, total: int, open: int, critical: int}>
     */
    private function byZone(?Carbon $start, ?Carbon $end): array
    {
        $rows = DB::table('zones')
            ->leftJoin('incidents', function ($join) use ($start, $end) {
                $join->on('incidents.zone_id', '=', 'zones.id');

                if ($start !== null) {
                    $join->where('incidents.reported_at', '>=', $start);
                }

                if ($end !== null) {
                    $join->where('incidents.reported_at', '<=', $end);
                }
            })
            ->selectRaw(
                'zones.id as zone_id, zones.nom as zone, COUNT(incidents.id) as total, '.
                'SUM(CASE WHEN incidents.status NOT IN (?, ?) THEN 1 ELSE 0 END) as open_count, '.
                'SUM(CASE WHEN incidents.severity = ? THEN 1 ELSE 0 END) as critical_count',
                ['traite', 'rejete', 'critique'],
            )
            ->groupBy('zones.id', 'zones.nom')
            ->orderBy('zones.nom')
            ->get()
            ->map(fn ($row) => [
                'zone_id' => (int) $row->zone_id,
                'zone' => (string) $row->zone,
                'total' => (int) $row->total,
                'open' => (int) $row->open_count,
                'critical' => (int) $row->critical_count,
            ])
            ->all();

        $unzoned = $this->periodQuery($start, $end)
            ->whereNull('zone_id')
            ->selectRaw(
                'COUNT(*) as total, '.
                'SUM(CASE WHEN status NOT IN (?, ?) THEN 1 ELSE 0 END) as open_count, '.
                'SUM(CASE WHEN severity = ? THEN 1 ELSE 0 END) as critical_count',
                ['traite', 'rejete', 'critique'],
            )
            ->first();

        if ($unzoned !== null && (int) $unzoned->total > 0) {
            $rows[] = [
                'zone_id' => null,
                'zone' => 'Sans zone',
                'total' => (int) $unzoned->total,
                'open' => (int) $unzoned->open_count,
                'critical' => (int) $unzoned->critical_count,
            ];
        }

        return $rows;
    }

    private function periodQuery(?Carbon $start, ?Carbon $end): Builder
    {
        return Incident::query()
            ->when($start !== null, fn (Builder $query) => $query->where('reported_at', '>=', $start))
            ->when($end !== null, fn (Builder $query) => $query->where('reported_at', '<=', $end));
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
