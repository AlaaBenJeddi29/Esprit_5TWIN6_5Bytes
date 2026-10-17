<?php

namespace Database\Factories\Quality;

use App\Models\Quality\WaterQualityParameter;
use App\Models\Quality\WaterQualitySample;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaterQualityParameter>
 */
class WaterQualityParameterFactory extends Factory
{
    protected $model = WaterQualityParameter::class;

    public function definition(): array
    {
        return [
            'sample_id' => WaterQualitySample::factory(),
            'parametre' => 'ph',
            'valeur' => fake()->randomFloat(2, 6.5, 8.5),
            'unite' => 'pH',
            'seuil_min' => 6.5,
            'seuil_max' => 8.5,
            'est_conforme' => true,
        ];
    }

    public function nonConforme(): static
    {
        return $this->state(fn () => [
            'parametre' => 'turbidite',
            'valeur' => fake()->randomFloat(2, 6, 20),
            'unite' => 'NTU',
            'seuil_min' => 0,
            'seuil_max' => 5,
            'est_conforme' => false,
        ]);
    }
}
