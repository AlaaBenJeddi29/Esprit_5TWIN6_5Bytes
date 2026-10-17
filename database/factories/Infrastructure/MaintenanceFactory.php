<?php

namespace Database\Factories\Infrastructure;

use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Maintenance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Maintenance>
 */
class MaintenanceFactory extends Factory
{
    protected $model = Maintenance::class;

    public function definition(): array
    {
        return [
            'infrastructure_id' => Infrastructure::factory(),
            'technicien_id' => null,
            'type' => fake()->word(),
            'description' => fake()->sentence(),
            'date_intervention' => fake()->dateTimeBetween('-1 year', '+6 months'),
            'cout' => fake()->randomFloat(2, 0, 20000),
            'statut' => 'planifiee',
        ];
    }
}
