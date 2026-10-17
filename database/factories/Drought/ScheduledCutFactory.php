<?php

namespace Database\Factories\Drought;

use App\Models\Drought\ScheduledCut;
use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledCut>
 */
class ScheduledCutFactory extends Factory
{
    protected $model = ScheduledCut::class;

    public function definition(): array
    {
        return [
            'zone_id' => Zone::factory(),
            'infrastructure_id' => null,
            'motif' => fake()->randomElement([
                'Travaux de réparation sur la conduite principale',
                'Remplacement d\'une vanne de sectionnement',
                'Intervention sur la station de pompage',
                'Raccordement d\'un nouveau quartier',
            ]),
            'date_debut' => fake()->dateTimeBetween('+1 day', '+3 weeks'),
            'date_fin' => fake()->dateTimeBetween('+3 weeks', '+4 weeks'),
            'statut' => 'planifiee',
        ];
    }

    public function enCours(): static
    {
        return $this->state(fn () => [
            'statut' => 'en_cours',
            'date_debut' => now()->subHour(),
            'date_fin' => now()->addHours(3),
        ]);
    }
}
