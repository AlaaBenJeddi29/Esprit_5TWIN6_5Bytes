<?php

namespace App\Services;

use App\Models\Infrastructure\Zone;
use App\Models\Quality\QualityPlanification;
use App\Models\Quality\QualityZoneAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Périmètre qualité, autorisations par zone et rappels de planification.
 *
 * Les administrateurs accèdent à toutes les zones. Les gestionnaires ne
 * voient que les zones du module Qualité qui leur sont affectées ; à défaut
 * d'affectation, leur périmètre est vide (refus par défaut).
 */
class QualityPlanningService
{
    /**
     * @return list<int>
     */
    public function assignedZoneIds(User $user): array
    {
        return QualityZoneAssignment::query()
            ->where('user_id', $user->id)
            ->pluck('zone_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return Collection<int, Zone>
     */
    public function zonesFor(User $user): Collection
    {
        return Zone::query()
            ->when(
                ! $user->isAdmin(),
                fn ($query) => $query->whereIn('id', $this->assignedZoneIds($user)),
            )
            ->orderBy('nom')
            ->get();
    }

    public function canAccessZone(User $user, int $zoneId): bool
    {
        return $user->isAdmin()
            || QualityZoneAssignment::query()
                ->where('user_id', $user->id)
                ->where('zone_id', $zoneId)
                ->exists();
    }

    public function authorizeZone(User $user, int $zoneId): void
    {
        abort_unless(
            $this->canAccessZone($user, $zoneId),
            403,
            "Vous n'êtes pas autorisé à accéder à cette zone.",
        );
    }

    public function authorizePlanification(User $user, QualityPlanification $planification): void
    {
        $this->authorizeZone($user, (int) $planification->zone_id);
    }

    /**
     * Rappels affichés dans le back-office.
     *
     * @return array{
     *     a_venir: int,
     *     en_retard: int,
     *     effectues: int,
     *     prochains: Collection<int, QualityPlanification>
     * }
     */
    public function reminders(User $user, int $limit = 5): array
    {
        $upcoming = QualityPlanification::query()
            ->accessibleTo($user)
            ->pending()
            ->whereDate('date_prevue', '>=', today());

        return [
            'a_venir' => (clone $upcoming)->count(),
            'en_retard' => QualityPlanification::query()
                ->accessibleTo($user)
                ->pending()
                ->whereDate('date_prevue', '<', today())
                ->count(),
            'effectues' => QualityPlanification::query()
                ->accessibleTo($user)
                ->whereNotNull('water_quality_sample_id')
                ->count(),
            'prochains' => (clone $upcoming)
                ->with('zone')
                ->orderBy('date_prevue')
                ->limit($limit)
                ->get(),
        ];
    }
}
