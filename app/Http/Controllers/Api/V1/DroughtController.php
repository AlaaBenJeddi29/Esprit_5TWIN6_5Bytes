<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Drought\ConsumptionForecast;
use App\Models\Infrastructure\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class DroughtController extends Controller
{
    public function forecastZone(Zone $zone): JsonResponse
    {
        // Last 12 months of historical data
        $historicalReadings = $zone
            ->consumptionReadings()
            ->orderBy('periode')
            ->get()
            ->take(-12);

        $historicalData = $historicalReadings->map(fn ($reading) => [
            'date' => $reading->periode->format('Y-m-d'),
            'month' => $reading->periode->translatedFormat('M Y'),
            'value' => (float) $reading->consommation_m3,
        ])->values();

        // Latest historical reading
        $latestHistorical = $zone
            ->consumptionReadings()
            ->orderBy('periode', 'desc')
            ->first();

        // Next 6 months of forecasts (validate they are newer than latest history)
        $forecasts = ConsumptionForecast::query()
            ->where('zone_id', $zone->id)
            ->where('forecast_month', '>=', Carbon::now()->startOfMonth())
            ->orderBy('forecast_month')
            ->limit(6)
            ->get()
            ->filter(function ($forecast) use ($latestHistorical) {
                // Ensure forecast month is actually after the latest historical reading
                if ($latestHistorical) {
                    return $forecast->forecast_month > $latestHistorical->periode;
                }
                return true;
            })
            ->values();

        $forecastData = $forecasts->map(fn ($forecast) => [
            'date' => $forecast->forecast_month->format('Y-m-d'),
            'month' => $forecast->forecast_month->translatedFormat('M Y'),
            'value' => (float) $forecast->prediction_m3,
            'lower_bound' => (float) $forecast->lower_bound,
            'upper_bound' => (float) $forecast->upper_bound,
        ])->values();

        $mae = $forecasts->first()?->mae ?? 0;
        $lastUpdated = $forecasts->first()?->updated_at?->format('Y-m-d H:i:s');

        // Quality metrics
        $qualityMetrics = [
            'model' => $forecasts->first()?->model_used ?? 'holt_winters',
            'mae' => (float) $mae,
            'confidence_interval' => '±15%',
            'forecast_horizon' => $forecastData->count() . ' months',
            'last_updated' => $lastUpdated,
            'data_freshness_warning' => $lastUpdated && \Illuminate\Support\Carbon::parse($lastUpdated)->diffInDays() > 1 
                ? "Forecasts were generated {$lastUpdated}. Consider refreshing." 
                : null,
        ];

        return response()->json([
            'zone' => [
                'id' => $zone->id,
                'nom' => $zone->nom,
                'commune' => $zone->commune,
                'population' => $zone->population,
            ],
            'historical_data' => $historicalData,
            'forecast' => $forecastData,
            'confidence_intervals' => [
                'lower_percentage' => 0.85,
                'upper_percentage' => 1.15,
            ],
            'quality_metrics' => $qualityMetrics,
        ]);
    }
}
