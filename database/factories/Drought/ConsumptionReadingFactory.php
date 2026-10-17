<?php

namespace Database\Factories\Drought;

use App\Models\Drought\ConsumptionReading;
use App\Models\Infrastructure\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsumptionReading>
 */
class ConsumptionReadingFactory extends Factory
{
    protected $model = ConsumptionReading::class;

    public function definition(): array
    {
        return [
            'zone_id' => Zone::factory(),
            'periode' => fake()->unique()->dateTimeBetween('-18 months', '-1 month')->format('Y-m-01'),
            'consommation_m3' => fake()->randomFloat(2, 5000, 250000),
            'nombre_abonnes' => fake()->numberBetween(500, 50000),
        ];
    }
}
