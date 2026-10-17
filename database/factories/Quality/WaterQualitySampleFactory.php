<?php

namespace Database\Factories\Quality;

use App\Models\Infrastructure\Zone;
use App\Models\Quality\WaterQualitySample;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaterQualitySample>
 */
class WaterQualitySampleFactory extends Factory
{
    protected $model = WaterQualitySample::class;

    public function definition(): array
    {
        return [
            'zone_id' => Zone::factory(),
            'infrastructure_id' => null,
            'user_id' => User::factory()->gestionnaire(),
            'date_prelevement' => fake()->dateTimeBetween('-6 months', 'now'),
            'source' => fake()->randomElement(['Réseau', 'Réservoir', 'Station de pompage']),
            'statut' => 'en_attente',
            'score' => null,
            'notes' => null,
        ];
    }

    public function conforme(): static
    {
        return $this->state(fn () => [
            'statut' => 'conforme',
            'score' => 100,
        ]);
    }

    public function nonConforme(): static
    {
        return $this->state(fn () => [
            'statut' => 'non_conforme',
            'score' => fake()->randomFloat(2, 40, 80),
        ]);
    }
}
