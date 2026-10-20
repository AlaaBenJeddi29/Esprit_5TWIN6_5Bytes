<?php

namespace Tests\Feature\Back;

use App\Jobs\GenerateConsumptionForecasts;
use App\Models\Drought\ConsumptionForecast;
use App\Models\Drought\ConsumptionReading;
use App\Models\Infrastructure\Zone;
use App\Services\ConsumptionForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DroughtForecastRefreshTest extends TestCase
{
    use RefreshDatabase;

    private function seedReadings(Zone $zone, int $months): void
    {
        for ($i = $months - 1; $i >= 0; $i--) {
            ConsumptionReading::factory()->create([
                'zone_id' => $zone->id,
                'periode' => now()->startOfMonth()->subMonths($i)->toDateString(),
                'consommation_m3' => 10000 + ($i * 500),
            ]);
        }
    }

    public function test_command_dispatches_forecasts_only_for_zones_with_enough_history(): void
    {
        Queue::fake();

        $complete = Zone::factory()->create(['nom' => 'Zone complète']);
        $incomplete = Zone::factory()->create(['nom' => 'Zone incomplète']);
        $this->seedReadings($complete, 12);
        $this->seedReadings($incomplete, 11);

        $exitCode = Artisan::call('drought:refresh-forecasts');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        Queue::assertPushed(GenerateConsumptionForecasts::class, 1);
        $this->assertStringContainsString('1 job(s) en attente', $output);
        $this->assertStringContainsString('données insuffisantes', $output);
    }

    public function test_command_handles_an_empty_database(): void
    {
        Queue::fake();

        $exitCode = Artisan::call('drought:refresh-forecasts');

        $this->assertSame(0, $exitCode);
        Queue::assertNothingPushed();
        $this->assertStringContainsString('Aucune zone trouvée', Artisan::output());
    }

    public function test_job_generates_six_forecasts_starting_after_the_last_reading(): void
    {
        $zone = Zone::factory()->create();
        $this->seedReadings($zone, 12);

        (new GenerateConsumptionForecasts($zone, 6))->handle(new ConsumptionForecastService);

        $this->assertDatabaseCount('consumption_forecasts', 6);

        $forecasts = ConsumptionForecast::query()
            ->where('zone_id', $zone->id)
            ->orderBy('forecast_month')
            ->get();

        $this->assertSame(
            now()->startOfMonth()->addMonth()->toDateString(),
            $forecasts->first()->forecast_month->toDateString()
        );
        $this->assertSame(
            now()->startOfMonth()->addMonths(6)->toDateString(),
            $forecasts->last()->forecast_month->toDateString()
        );

        foreach ($forecasts as $forecast) {
            $this->assertSame('holt_winters', $forecast->model_used);
            $this->assertGreaterThan(0, (float) $forecast->prediction_m3);
            $this->assertLessThan((float) $forecast->prediction_m3, (float) $forecast->lower_bound);
            $this->assertGreaterThan((float) $forecast->prediction_m3, (float) $forecast->upper_bound);
            $this->assertGreaterThanOrEqual(0, (float) $forecast->mae);
        }
    }

    public function test_job_updates_existing_forecasts_without_duplicating_them(): void
    {
        $zone = Zone::factory()->create();
        $this->seedReadings($zone, 12);

        $job = new GenerateConsumptionForecasts($zone, 6);
        $job->handle(new ConsumptionForecastService);

        $ids = ConsumptionForecast::query()
            ->where('zone_id', $zone->id)
            ->orderBy('forecast_month')
            ->pluck('id')
            ->all();

        $job->handle(new ConsumptionForecastService);

        $this->assertDatabaseCount('consumption_forecasts', 6);
        $this->assertSame($ids, ConsumptionForecast::query()
            ->where('zone_id', $zone->id)
            ->orderBy('forecast_month')
            ->pluck('id')
            ->all());
    }

    public function test_job_does_nothing_when_history_is_insufficient(): void
    {
        $zone = Zone::factory()->create();
        $this->seedReadings($zone, 5);

        (new GenerateConsumptionForecasts($zone, 6))->handle(new ConsumptionForecastService);

        $this->assertDatabaseCount('consumption_forecasts', 0);
    }
}
