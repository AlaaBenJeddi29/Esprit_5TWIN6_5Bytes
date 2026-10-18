<?php

namespace App\Services;

use App\Models\Incident\Incident;
use Illuminate\Support\Collection;

/**
 * Détection des concentrations d'incidents critiques proches.
 *
 * Méthode retenue : regroupement glouton par ancre, dans un ordre
 * déterministe (identifiants croissants, donc ordre de création).
 *
 * 1. Les incidents sont triés par identifiant.
 * 2. Le premier incident non affecté devient l'ancre d'un groupe.
 * 3. Tous les incidents encore libres situés à une distance de Haversine
 *    inférieure ou égale au rayon de l'ancre rejoignent ce groupe.
 * 4. Les groupes de moins de deux incidents ne sont pas des concentrations.
 *
 * Ce choix garantit qu'un incident n'appartient qu'à un seul groupe et que
 * deux groupes ne se chevauchent jamais. Conséquence assumée : lorsqu'un
 * enchaînement de points est plus long que le rayon (A proche de B, B proche
 * de C, A éloigné de C), l'ancre A ne regroupe que B ; C reste hors du groupe
 * au lieu de fusionner des incidents réellement éloignés. Le résultat ne
 * dépend donc pas de l'ordre d'itération et reste reproductible.
 *
 * Limites : la distance est calculée sur une sphère (rayon terrestre moyen),
 * ce qui est suffisant à l'échelle d'une commune mais ignore le relief et le
 * tracé réel du réseau. Le regroupement ne tient pas compte du type
 * d'incident, uniquement de la gravité « critique » filtrée en amont.
 */
class IncidentClusterService
{
    public const DEFAULT_RADIUS = 500;

    public const MIN_RADIUS = 50;

    public const MAX_RADIUS = 5000;

    private const EARTH_RADIUS_METERS = 6371000.0;

    private const MAX_LABELS = 3;

    /**
     * @param  Collection<int, Incident>  $incidents  Incidents critiques déjà filtrés
     * @return list<array<string, mixed>>
     */
    public function detect(Collection $incidents, int $radius = self::DEFAULT_RADIUS): array
    {
        $radius = max(self::MIN_RADIUS, min(self::MAX_RADIUS, $radius));

        $ordered = $incidents
            ->filter(fn (Incident $incident) => $this->coordinates($incident) !== null)
            ->sortBy('id')
            ->values();

        $assigned = [];
        $clusters = [];

        foreach ($ordered as $seed) {
            if (isset($assigned[$seed->id])) {
                continue;
            }

            $assigned[$seed->id] = true;
            $members = [$seed];
            [$seedLatitude, $seedLongitude] = $this->coordinates($seed);

            foreach ($ordered as $candidate) {
                if (isset($assigned[$candidate->id])) {
                    continue;
                }

                [$latitude, $longitude] = $this->coordinates($candidate);

                if ($this->distanceInMeters($seedLatitude, $seedLongitude, $latitude, $longitude) <= $radius) {
                    $members[] = $candidate;
                    $assigned[$candidate->id] = true;
                }
            }

            if (count($members) >= 2) {
                $clusters[] = $this->presentCluster($members, $radius);
            }
        }

        return $clusters;
    }

    /**
     * Distance géographique réelle entre deux points, en mètres (Haversine).
     */
    public function distanceInMeters(
        float $latitudeFrom,
        float $longitudeFrom,
        float $latitudeTo,
        float $longitudeTo,
    ): float {
        $latitudeFrom = deg2rad($latitudeFrom);
        $latitudeTo = deg2rad($latitudeTo);
        $deltaLatitude = $latitudeTo - $latitudeFrom;
        $deltaLongitude = deg2rad($longitudeTo - $longitudeFrom);

        $a = sin($deltaLatitude / 2) ** 2
            + cos($latitudeFrom) * cos($latitudeTo) * sin($deltaLongitude / 2) ** 2;

        return 2 * self::EARTH_RADIUS_METERS * asin(min(1.0, sqrt(max(0.0, $a))));
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function coordinates(Incident $incident): ?array
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

        return [$latitude, $longitude];
    }

    /**
     * @param  list<Incident>  $members
     * @return array<string, mixed>
     */
    private function presentCluster(array $members, int $radius): array
    {
        $collection = collect($members);
        $seed = $members[0];

        $coordinates = $collection->map(fn (Incident $incident) => $this->coordinates($incident));
        $latitudes = $coordinates->map(fn (array $coordinate) => $coordinate[0]);
        $longitudes = $coordinates->map(fn (array $coordinate) => $coordinate[1]);

        return [
            'id' => 'cluster-'.$seed->id,
            'count' => $collection->count(),
            'radius' => $radius,
            'center' => [
                'latitude' => round((float) $latitudes->avg(), 7),
                'longitude' => round((float) $longitudes->avg(), 7),
            ],
            'bounds' => [
                [round((float) $latitudes->min(), 7), round((float) $longitudes->min(), 7)],
                [round((float) $latitudes->max(), 7), round((float) $longitudes->max(), 7)],
            ],
            'zone' => $this->labelList($collection->map(fn (Incident $incident) => $incident->zone?->nom)),
            'infrastructure' => $this->labelList(
                $collection->map(fn (Incident $incident) => $incident->infrastructure?->nom)
            ),
            'incidents' => $collection->map(fn (Incident $incident) => [
                'id' => $incident->id,
                'reference' => $incident->reference,
                'title' => $incident->title,
                'status' => $incident->status,
                'status_label' => $incident->statusLabel(),
                'url' => route('back.incidents.show', $incident),
            ])->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, string|null>  $names
     */
    private function labelList(Collection $names): ?string
    {
        $unique = $names
            ->filter(fn ($name) => is_string($name) && $name !== '')
            ->unique()
            ->values();

        if ($unique->isEmpty()) {
            return null;
        }

        $visible = $unique->take(self::MAX_LABELS)->implode(', ');

        return $unique->count() > self::MAX_LABELS ? $visible.'…' : $visible;
    }
}
