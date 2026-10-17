<?php

namespace Database\Factories\Project;

use App\Models\Project\Contractor;
use App\Models\Project\Project;
use App\Models\Project\ProjectPhase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectPhase>
 */
class ProjectPhaseFactory extends Factory
{
    protected $model = ProjectPhase::class;

    public function definition(): array
    {
        $dateDebut = fake()->dateTimeBetween('-1 year', 'now');
        $dateFin = (clone $dateDebut)->modify('+'.fake()->numberBetween(15, 180).' days');

        return [
            'project_id' => Project::factory(),
            'contractor_id' => Contractor::factory(),
            'nom' => fake()->randomElement([
                'Phase 1 - Préparation et études',
                'Phase 2 - Travaux préparatoires',
                'Phase 3 - Travaux principaux',
                'Phase 4 - Finitions',
                'Phase 5 - Tests et mise en service',
            ]),
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'cout' => fake()->randomFloat(2, 5000, 150000),
            'avancement' => fake()->numberBetween(0, 100),
        ];
    }
}
