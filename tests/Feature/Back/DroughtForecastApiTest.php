<?php

namespace Tests\Feature\Back;

use App\Models\Drought\ConsumptionForecast;
use App\Models\Drought\ConsumptionReading;
use App\Models\Infrastructure\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DroughtForecastApiTest extends TestCase
{
    use RefreshDatabase;

    private function readingPeriod(int $monthsAgo): string
    {
        return now()->startOfMonth()->subMonths($monthsAgo)->toDateString();
    }

    public function test_forecast_endpoint_returns_history_and_six_future_forecasts(): void
    {
        $zone = Zone::factory()->create([
            'nom' => 'Zone API',
            'commune' => 'Tunis',
            'population' => 120000,
        ]);

        for ($i = 11; $i >= 0; $i--) {
            ConsumptionReading::factory()->create([
                'zone_id' => $zone->id,
                'periode' => $this->readingPeriod($i),
                'consommation_m3' => 10000 + ($i * 100),
            ]);
        }

        // Forecast in the current month is not after the latest historical reading.
        ConsumptionForecast::factory()->create([
            'zone_id' => $zone->id,
            'forecast_month' => now()->startOfMonth()->toDateString(),
            'prediction_m3' => 999999,
            'mae' => 999,
        ]);

        for ($i = 1; $i <= 6; $i++) {
            ConsumptionForecast::factory()->create([
                'zone_id' => $zone->id,
                'forecast_month' => now()->startOfMonth()->addMonths($i)->toDateString(),
                'prediction_m3' => 11000 + ($i * 100),
                'lower_bound' => 10000 + ($i * 100),
                'upper_bound' => 12000 + ($i * 100),
                'mae' => 150,
            ]);
        }

        $response = $this->getJson(route('api.v1.drought.forecast', $zone));

        $response
            ->assertOk()
            ->assertJsonPath('zone.id', $zone->id)
            ->assertJsonPath('zone.nom', 'Zone API')
            ->assertJsonPath('zone.commune', 'Tunis')
            ->assertJsonPath('zone.population', 120000)
            ->assertJsonCount(12, 'historical_data')
            ->assertJsonCount(6, 'forecast')
            ->assertJsonPath('forecast.0.date', now()->startOfMonth()->addMonth()->toDateString())
            ->assertJsonPath('forecast.0.value', 11100)
            ->assertJsonPath('forecast.0.lower_bound', 10100)
            ->assertJsonPath('forecast.0.upper_bound', 12100)
            ->assertJsonPath('quality_metrics.model', 'holt_winters')
            ->assertJsonPath('quality_metrics.mae', 150)
            ->assertJsonPath('quality_metrics.confidence_interval', '±15%')
            ->assertJsonPath('quality_metrics.forecast_horizon', '6 months')
            ->assertJsonPath('confidence_intervals.lower_percentage', 0.85)
            ->assertJsonPath('confidence_intervals.upper_percentage', 1.15);

        $this->assertNotNull($response->json('quality_metrics.last_updated'));
    }

    public function test_forecast_endpoint_ignores_forecasts_older_than_the_current_month(): void
    {
        $zone = Zone::factory()->create();

        ConsumptionForecast::factory()->create([
            'zone_id' => $zone->id,
            'forecast_month' => now()->startOfMonth()->subMonth()->toDateString(),
        ]);
        ConsumptionForecast::factory()->create([
            'zone_id' => $zone->id,
            'forecast_month' => now()->startOfMonth()->addMonth()->toDateString(),
        ]);

        $this->getJson(route('api.v1.drought.forecast', $zone))
            ->assertOk()
            ->assertJsonCount(1, 'forecast')
            ->assertJsonPath('forecast.0.date', now()->startOfMonth()->addMonth()->toDateString());
    }

    public function test_forecast_endpoint_returns_an_empty_history_for_a_zone_without_readings(): void
    {
        $zone = Zone::factory()->create();

        ConsumptionForecast::factory()->create([
            'zone_id' => $zone->id,
            'forecast_month' => now()->startOfMonth()->addMonth()->toDateString(),
        ]);

        $this->getJson(route('api.v1.drought.forecast', $zone))
            ->assertOk()
            ->assertJsonCount(0, 'historical_data')
            ->assertJsonCount(1, 'forecast')
            ->assertJsonPath('quality_metrics.forecast_horizon', '1 months');
    }

    public function test_forecast_endpoint_returns_not_found_for_an_unknown_zone(): void
    {
        $this->getJson('/api/v1/drought/zones/999999/consumption/forecast')
            ->assertNotFound();
    }
}
