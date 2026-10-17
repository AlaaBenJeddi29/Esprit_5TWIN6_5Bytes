<?php

namespace Database\Factories\Project;

use App\Models\Project\Contractor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contractor>
 */
class ContractorFactory extends Factory
{
    protected $model = Contractor::class;

    public function definition(): array
    {
        return [
            'nom' => fake()->company(),
            'specialite' => fake()->randomElement([
                'Rénovation canalisations',
                'Décontamination',
                'Pose de compteurs',
                'Travaux de forage',
                'Électromécanique',
                'Installation de pompes',
                'Étanchéité réservoirs',
                'Réhabilitation sans tranchée',
            ]),
            'email' => fake()->unique()->companyEmail(),
            'telephone' => '+33 '.fake()->numberBetween(1, 9).' '.fake()->numerify('## ## ## ##'),
            'adresse' => fake()->numberBetween(1, 150).' '.fake()->streetName().', '.fake()->postcode().' '.fake()->city(),
        ];
    }
}
