<?php

namespace Database\Factories\Drought;

use App\Models\Drought\WaterLevel;
use App\Models\Infrastructure\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaterLevel>
 */
class WaterLevelFactory extends Factory
{
    protected $model = WaterLevel::class;

    public function definition(): array
    {
        return [
            'nom_source' => fake()->randomElement(['Barrage du Nord', 'Nappe de la Plaine', 'Réservoir Est', 'Rivière Vivante']),
            'zone_id' => Zone::factory(),
            'type_source' => fake()->randomElement(array_keys(WaterLevel::TYPES)),
            'niveau' => fake()->randomFloat(2, 10, 95),
            'unite' => '%',
            'seuil_alerte' => 30,
            'seuil_critique' => 15,
            'date_mesure' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }

    public function critique(): static
    {
        return $this->state(fn () => ['niveau' => fake()->randomFloat(2, 2, 14)]);
    }
}
