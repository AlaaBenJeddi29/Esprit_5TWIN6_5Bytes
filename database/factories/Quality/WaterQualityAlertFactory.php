<?php

namespace Database\Factories\Quality;

use App\Models\Infrastructure\Zone;
use App\Models\Quality\WaterQualityAlert;
use App\Models\Quality\WaterQualitySample;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaterQualityAlert>
 */
class WaterQualityAlertFactory extends Factory
{
    protected $model = WaterQualityAlert::class;

    public function definition(): array
    {
        return [
            'zone_id' => Zone::factory(),
            'sample_id' => null,
            'parametre' => 'turbidite',
            'niveau' => fake()->randomElement(array_keys(WaterQualityAlert::NIVEAUX)),
            'message' => 'Turbidité supérieure au seuil réglementaire.',
            'statut' => 'active',
            'resolue_at' => null,
        ];
    }

    public function resolue(): static
    {
        return $this->state(fn () => [
            'statut' => 'resolue',
            'resolue_at' => now(),
        ]);
    }
}
