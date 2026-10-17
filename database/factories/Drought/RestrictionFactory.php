<?php

namespace Database\Factories\Drought;

use App\Models\Drought\Restriction;
use App\Models\Infrastructure\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Restriction>
 */
class RestrictionFactory extends Factory
{
    protected $model = Restriction::class;

    public function definition(): array
    {
        return [
            'zone_id' => Zone::factory(),
            'type' => fake()->randomElement(array_keys(Restriction::TYPES)),
            'titre' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'usages_interdits' => "Arrosage des pelouses\nLavage des véhicules\nRemplissage des piscines",
            'date_debut' => fake()->dateTimeBetween('-2 months', 'now'),
            'date_fin' => fake()->dateTimeBetween('now', '+3 months'),
            'statut' => 'active',
        ];
    }

    public function planifiee(): static
    {
        return $this->state(fn () => [
            'statut' => 'planifiee',
            'date_debut' => fake()->dateTimeBetween('+1 week', '+2 months'),
        ]);
    }
}
