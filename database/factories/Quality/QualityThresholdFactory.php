<?php

namespace Database\Factories\Quality;

use App\Models\Quality\QualityThreshold;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QualityThreshold>
 */
class QualityThresholdFactory extends Factory
{
    protected $model = QualityThreshold::class;

    public function definition(): array
    {
        $parametre = fake()->randomElement(array_keys(QualityThreshold::PARAMETRES));
        $unites = [
            'ph' => 'pH',
            'turbidite' => 'NTU',
            'chlore' => 'mg/L',
            'nitrates' => 'mg/L',
            'plomb' => 'µg/L',
            'bacteries' => 'UFC/100 mL',
            'conductivite' => 'µS/cm',
        ];

        return [
            'zone_id' => null,
            'parametre' => $parametre,
            'unite' => $unites[$parametre] ?? 'unité',
            'valeur_min' => 0,
            'valeur_max' => $parametre === 'ph' ? 8.5 : fake()->randomFloat(2, 1, 50),
            'actif' => true,
        ];
    }
}
