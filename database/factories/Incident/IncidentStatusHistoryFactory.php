<?php

namespace Database\Factories\Incident;

use App\Models\Incident\Incident;
use App\Models\Incident\IncidentStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncidentStatusHistory>
 */
class IncidentStatusHistoryFactory extends Factory
{
    protected $model = IncidentStatusHistory::class;

    public function definition(): array
    {
        return [
            'incident_id' => Incident::factory(),
            'user_id' => User::factory()->gestionnaire(),
            'ancien_statut' => 'nouveau',
            'nouveau_statut' => 'en_cours',
            'comment' => fake()->sentence(),
        ];
    }
}
