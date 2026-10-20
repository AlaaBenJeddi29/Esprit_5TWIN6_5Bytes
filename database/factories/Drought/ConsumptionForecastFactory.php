<?php

namespace Database\Factories\Drought;

use App\Models\Drought\ConsumptionForecast;
use App\Models\Infrastructure\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsumptionForecast>
 */
class ConsumptionForecastFactory extends Factory
{
    protected $model = ConsumptionForecast::class;

    public function definition(): array
    {
        return [
            'zone_id' => Zone::factory(),
            'forecast_month' => now()->startOfMonth()->addMonth(),
            'prediction_m3' => fake()->randomFloat(2, 5000, 250000),
            'lower_bound' => fn (array $attributes) => round((float) $attributes['prediction_m3'] * 0.85, 2),
            'upper_bound' => fn (array $attributes) => round((float) $attributes['prediction_m3'] * 1.15, 2),
            'model_used' => 'holt_winters',
            'mae' => fake()->randomFloat(2, 100, 5000),
        ];
    }
}
