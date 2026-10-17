<?php

namespace Database\Factories\Project;

use App\Models\Project\Project;
use App\Models\Project\ProjectDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectDocument>
 */
class ProjectDocumentFactory extends Factory
{
    protected $model = ProjectDocument::class;

    public function definition(): array
    {
        $type = fake()->randomElement(ProjectDocument::TYPES);
        $titre = ucfirst($type).' — '.fake()->words(3, true);
        $extension = match ($type) {
            'photo' => 'jpg',
            'facture' => 'xlsx',
            'plan' => 'dwg',
            default => 'pdf',
        };

        return [
            'project_id' => Project::factory(),
            'titre' => $titre,
            'chemin_fichier' => 'projects/'.fake()->numberBetween(1, 999).'/documents/'.
                strtolower(str_replace(' ', '_', $titre)).'_'.fake()->numberBetween(1000, 9999).'.'.$extension,
            'type' => $type,
        ];
    }
}
