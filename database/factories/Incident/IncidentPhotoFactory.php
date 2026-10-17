<?php

namespace Database\Factories\Incident;

use App\Models\Incident\Incident;
use App\Models\Incident\IncidentPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncidentPhoto>
 */
class IncidentPhotoFactory extends Factory
{
    protected $model = IncidentPhoto::class;

    public function definition(): array
    {
        $name = fake()->word().'.jpg';

        return [
            'incident_id' => Incident::factory(),
            'chemin_fichier' => 'incidents/demo/'.$name,
            'nom_original' => $name,
            'mime_type' => 'image/jpeg',
            'taille' => fake()->numberBetween(50_000, 2_000_000),
        ];
    }
}
