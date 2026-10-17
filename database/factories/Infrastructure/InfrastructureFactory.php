<?php

namespace Database\Factories\Infrastructure;

use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Infrastructure>
 */
class InfrastructureFactory extends Factory
{
    protected $model = Infrastructure::class;

    public function definition(): array
    {
        return [
            'nom' => ucfirst(fake()->word()).' '.fake()->numberBetween(1, 99),
            'type' => fake()->randomElement(Infrastructure::TYPES),
            'materiau' => fake()->randomElement(['PVC', 'Fonte', 'Acier', 'Béton', 'PEHD']),
            'date_installation' => fake()->dateTimeBetween('-20 years', '-1 year'),
            'capacite' => fake()->randomFloat(2, 100, 10000),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'statut' => fake()->randomElement(Infrastructure::STATUTS),
            'score_risque' => fake()->randomFloat(2, 0, 10),
            'zone_id' => Zone::factory(),
        ];
    }
}
