<?php

namespace App\Services\Drought;

use App\Models\Drought\ConsumptionReading;
use App\Models\Drought\Restriction;
use App\Models\Drought\ScheduledCut;
use App\Models\Drought\WaterLevel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DroughtExportService
{
    /**
     * Get water levels for export with applied filters.
     */
    public static function getWaterLevelsForExport(?int $zoneId = null, ?string $type = null): Collection
    {
        $query = WaterLevel::query()
            ->with('zone')
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId));

        if ($type && !array_key_exists($type, WaterLevel::TYPES)) {
            Log::warning('Invalid filter value for water level type export', [
                'type' => $type,
                'valid_types' => array_keys(WaterLevel::TYPES),
            ]);
        } else {
            $query->when($type && array_key_exists($type, WaterLevel::TYPES), fn ($query) => $query->where('type_source', $type));
        }

        $result = $query->orderByDesc('date_mesure')->get();

        if ($result->isEmpty()) {
            Log::info('Water level export returned empty result', [
                'filters' => ['zone_id' => $zoneId, 'type' => $type],
            ]);
        }

        return $result;
    }

    /**
     * Get consumption readings for export with applied filters.
     */
    public static function getConsumptionReadingsForExport(?int $zoneId = null): Collection
    {
        $result = ConsumptionReading::query()
            ->with('zone')
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId))
            ->orderByDesc('periode')
            ->get();

        if ($result->isEmpty()) {
            Log::info('Consumption reading export returned empty result', [
                'filters' => ['zone_id' => $zoneId],
            ]);
        }

        return $result;
    }

    /**
     * Get restrictions for export with applied filters.
     */
    public static function getRestrictionsForExport(?int $zoneId = null, ?string $statut = null, ?string $type = null): Collection
    {
        $query = Restriction::query()
            ->with('zone')
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId));

        if ($statut && !array_key_exists($statut, Restriction::STATUTS)) {
            Log::warning('Invalid filter value for restriction statut export', [
                'statut' => $statut,
                'valid_statuts' => array_keys(Restriction::STATUTS),
            ]);
        } else {
            $query->when($statut && array_key_exists($statut, Restriction::STATUTS), fn ($query) => $query->where('statut', $statut));
        }

        if ($type && !array_key_exists($type, Restriction::TYPES)) {
            Log::warning('Invalid filter value for restriction type export', [
                'type' => $type,
                'valid_types' => array_keys(Restriction::TYPES),
            ]);
        } else {
            $query->when($type && array_key_exists($type, Restriction::TYPES), fn ($query) => $query->where('type', $type));
        }

        $result = $query->orderByDesc('date_debut')->get();

        if ($result->isEmpty()) {
            Log::info('Restriction export returned empty result', [
                'filters' => ['zone_id' => $zoneId, 'statut' => $statut, 'type' => $type],
            ]);
        }

        return $result;
    }

    /**
     * Get scheduled cuts for export with applied filters.
     */
    public static function getScheduledCutsForExport(?int $zoneId = null, ?string $statut = null): Collection
    {
        $query = ScheduledCut::query()
            ->with(['zone', 'infrastructure'])
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId));

        if ($statut && !array_key_exists($statut, ScheduledCut::STATUTS)) {
            Log::warning('Invalid filter value for scheduled cut statut export', [
                'statut' => $statut,
                'valid_statuts' => array_keys(ScheduledCut::STATUTS),
            ]);
        } else {
            $query->when($statut && array_key_exists($statut, ScheduledCut::STATUTS), fn ($query) => $query->where('statut', $statut));
        }

        $result = $query->orderByDesc('date_debut')->get();

        if ($result->isEmpty()) {
            Log::info('Scheduled cut export returned empty result', [
                'filters' => ['zone_id' => $zoneId, 'statut' => $statut],
            ]);
        }

        return $result;
    }
}
