<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\Incident\IncidentMapFilterRequest;
use App\Models\Incident\Incident;
use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Zone;
use App\Services\IncidentClusterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class IncidentMapController extends Controller
{
    public function __construct(
        private readonly IncidentClusterService $clusters,
    ) {}

    public function index(): View
    {
        return view('back.incidents.map', [
            'severities' => Incident::SEVERITIES,
            'statuses' => Incident::STATUSES,
            'zones' => Zone::query()->orderBy('nom')->get(['id', 'nom']),
            'infrastructures' => Infrastructure::query()
                ->with('zone:id,nom')
                ->orderBy('nom')
                ->get(['id', 'nom', 'zone_id']),
            'defaultRadius' => IncidentClusterService::DEFAULT_RADIUS,
            'minRadius' => IncidentClusterService::MIN_RADIUS,
            'maxRadius' => IncidentClusterService::MAX_RADIUS,
        ]);
    }

    public function data(IncidentMapFilterRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $radius = (int) ($filters['radius'] ?? IncidentClusterService::DEFAULT_RADIUS);
        $limit = max(100, (int) config('aquasecure.incident_map.max_markers', 2000));

        $base = $this->filteredQuery($filters);

        $total = (clone $base)->count();
        $placed = $this->withValidCoordinates(clone $base)->count();
        $critical = (clone $base)->where('severity', 'critique')->count();
        $unresolved = (clone $base)->whereNotIn('status', ['traite', 'rejete'])->count();

        $incidents = $this->withValidCoordinates(clone $base)
            ->with(['zone:id,nom', 'infrastructure:id,nom'])
            ->orderByDesc('reported_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get($this->incidentColumns());

        $criticalCandidates = $this->withValidCoordinates(
            (clone $base)->where('severity', 'critique')
        )
            ->with(['zone:id,nom', 'infrastructure:id,nom'])
            ->orderBy('id')
            ->limit($limit)
            ->get($this->incidentColumns());

        $payload = $incidents
            ->map(fn (Incident $incident) => $this->presentIncident($incident))
            ->filter()
            ->values();

        $clusters = $this->clusters->detect($criticalCandidates, $radius);

        return response()->json([
            'incidents' => $payload,
            'clusters' => $clusters,
            'counts' => [
                'total' => $total,
                'placed' => $placed,
                'displayed' => $payload->count(),
                'unplaced' => max(0, $total - $placed),
                'critical' => $critical,
                'unresolved' => $unresolved,
                'clusters' => count($clusters),
            ],
            'meta' => [
                'radius' => $radius,
                'limit' => $limit,
                'truncated' => $placed > $limit,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        return Incident::query()
            ->when(! empty($filters['severity']), fn (Builder $query) => $query->where('severity', $filters['severity']))
            ->when(! empty($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['zone_id']), fn (Builder $query) => $query->where('zone_id', $filters['zone_id']))
            ->when(
                ! empty($filters['infrastructure_id']),
                fn (Builder $query) => $query->where('infrastructure_id', $filters['infrastructure_id'])
            )
            ->when(
                ! empty($filters['date_from']),
                fn (Builder $query) => $query->whereDate('reported_at', '>=', $filters['date_from'])
            )
            ->when(
                ! empty($filters['date_to']),
                fn (Builder $query) => $query->whereDate('reported_at', '<=', $filters['date_to'])
            );
    }

    private function withValidCoordinates(Builder $query): Builder
    {
        return $query
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [-90, 90])
            ->whereBetween('longitude', [-180, 180]);
    }

    /**
     * @return list<string>
     */
    private function incidentColumns(): array
    {
        return [
            'id',
            'reference',
            'title',
            'latitude',
            'longitude',
            'severity',
            'status',
            'reported_at',
            'zone_id',
            'infrastructure_id',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function presentIncident(Incident $incident): ?array
    {
        $latitude = is_numeric($incident->latitude) ? (float) $incident->latitude : null;
        $longitude = is_numeric($incident->longitude) ? (float) $incident->longitude : null;

        if ($latitude === null
            || $longitude === null
            || $latitude < -90
            || $latitude > 90
            || $longitude < -180
            || $longitude > 180) {
            return null;
        }

        return [
            'id' => $incident->id,
            'reference' => $incident->reference,
            'title' => $incident->title,
            'severity' => $incident->severity,
            'severity_label' => $incident->severityLabel(),
            'status' => $incident->status,
            'status_label' => $incident->statusLabel(),
            'reported_at' => $incident->reported_at?->toIso8601String(),
            'latitude' => round($latitude, 7),
            'longitude' => round($longitude, 7),
            'zone' => $incident->zone
                ? ['id' => $incident->zone->id, 'nom' => $incident->zone->nom]
                : null,
            'infrastructure' => $incident->infrastructure
                ? ['id' => $incident->infrastructure->id, 'nom' => $incident->infrastructure->nom]
                : null,
            'url' => route('back.incidents.show', $incident),
        ];
    }
}
