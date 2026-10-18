<?php

namespace App\Services\Drought;

use App\Models\Drought\ConsumptionReading;
use App\Models\Drought\Restriction;
use App\Models\Drought\ScheduledCut;
use App\Models\Drought\WaterLevel;
use Illuminate\Support\Collection;

class DroughtExportService
{
    /**
     * Get water levels for export with applied filters.
     */
    public static function getWaterLevelsForExport(?int $zoneId = null, ?string $type = null): Collection
    {
        return WaterLevel::query()
            ->with('zone')
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId))
            ->when($type && array_key_exists($type, WaterLevel::TYPES), fn ($query) => $query->where('type_source', $type))
            ->orderByDesc('date_mesure')
            ->get();
    }

    /**
     * Get consumption readings for export with applied filters.
     */
    public static function getConsumptionReadingsForExport(?int $zoneId = null): Collection
    {
        return ConsumptionReading::query()
            ->with('zone')
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId))
            ->orderByDesc('periode')
            ->get();
    }

    /**
     * Get restrictions for export with applied filters.
     */
    public static function getRestrictionsForExport(?int $zoneId = null, ?string $statut = null, ?string $type = null): Collection
    {
        return Restriction::query()
            ->with('zone')
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId))
            ->when($statut && array_key_exists($statut, Restriction::STATUTS), fn ($query) => $query->where('statut', $statut))
            ->when($type && array_key_exists($type, Restriction::TYPES), fn ($query) => $query->where('type', $type))
            ->orderByDesc('date_debut')
            ->get();
    }

    /**
     * Get scheduled cuts for export with applied filters.
     */
    public static function getScheduledCutsForExport(?int $zoneId = null, ?string $statut = null): Collection
    {
        return ScheduledCut::query()
            ->with(['zone', 'infrastructure'])
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId))
            ->when($statut && array_key_exists($statut, ScheduledCut::STATUTS), fn ($query) => $query->where('statut', $statut))
            ->orderByDesc('date_debut')
            ->get();
    }
}
