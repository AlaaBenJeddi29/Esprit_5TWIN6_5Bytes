<?php

namespace App\Jobs;

use App\Models\Drought\ConsumptionForecast;
use App\Models\Infrastructure\Zone;
use App\Services\ConsumptionForecastService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class GenerateConsumptionForecasts implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Zone $zone, private readonly int $forecastMonths = 6)
    {
    }

    public function handle(ConsumptionForecastService $service): void
    {
        $forecast = $service->predictNextMonths($this->zone, $this->forecastMonths);

        if (empty($forecast['predictions'])) {
            return;
        }

        $lastReadingDate = $this->zone
            ->consumptionReadings()
            ->orderByDesc('periode')
            ->first()
            ?->periode;

        if (!$lastReadingDate) {
            return;
        }

        $startDate = Carbon::parse($lastReadingDate)->addMonth()->startOfMonth();

        foreach ($forecast['predictions'] as $idx => $prediction) {
            $forecastMonth = $startDate->copy()->addMonths($idx);

            ConsumptionForecast::upsert(
                [
                    [
                        'zone_id' => $this->zone->id,
                        'forecast_month' => $forecastMonth->toDateString(),
                        'prediction_m3' => $prediction,
                        'lower_bound' => $forecast['lower_bounds'][$idx],
                        'upper_bound' => $forecast['upper_bounds'][$idx],
                        'model_used' => $forecast['model'],
                        'mae' => $forecast['mae'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ],
                ['zone_id', 'forecast_month'],
                ['prediction_m3', 'lower_bound', 'upper_bound', 'model_used', 'mae', 'updated_at']
            );
        }
    }
}
