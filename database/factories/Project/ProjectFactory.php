<?php

namespace Database\Factories\Project;

use App\Models\Infrastructure\Zone;
use App\Models\Project\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        $dateDebut = fake()->dateTimeBetween('-1 year', '+2 months');
        $dateFin = (clone $dateDebut)->modify('+'.fake()->numberBetween(60, 730).' days');

        return [
            'titre' => fake()->randomElement([
                'Remplacement canalisation principale',
                'Décontamination réservoir Est',
                'Rénovation station de pompage Nord',
                'Extension réseau quartier des Fleurs',
                'Modernisation compteurs zone industrielle',
                'Réhabilitation réseau sans tranchée',
            ]).' — '.fake()->city(),
            'type' => fake()->randomElement(Project::TYPES),
            'description' => fake()->paragraph(),
            'budget_prevu' => fake()->randomFloat(2, 20000, 600000),
            'date_debut' => $dateDebut,
            'date_fin_prevue' => $dateFin,
            'statut' => fake()->randomElement(Project::STATUTS),
            'avancement_pourcentage' => fake()->numberBetween(0, 100),
            'zone_id' => Zone::factory(),
            'infrastructure_id' => null,
            'responsable_id' => User::factory()->gestionnaire(),
        ];
    }

    public function planifie(): static
    {
        return $this->state(fn () => [
            'statut' => 'planifié',
            'avancement_pourcentage' => fake()->numberBetween(0, 10),
            'date_debut' => fake()->dateTimeBetween('+1 week', '+3 months'),
        ]);
    }

    public function enCours(): static
    {
        return $this->state(fn () => [
            'statut' => 'en_cours',
            'avancement_pourcentage' => fake()->numberBetween(20, 80),
            'date_debut' => fake()->dateTimeBetween('-6 months', '-1 week'),
        ]);
    }

    public function termine(): static
    {
        return $this->state(fn () => [
            'statut' => 'terminé',
            'avancement_pourcentage' => 100,
            'date_debut' => fake()->dateTimeBetween('-2 years', '-3 months'),
        ]);
    }
}
