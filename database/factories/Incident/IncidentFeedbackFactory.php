<?php

namespace Database\Factories\Incident;

use App\Models\Incident\Incident;
use App\Models\Incident\IncidentFeedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncidentFeedback>
 */
class IncidentFeedbackFactory extends Factory
{
    protected $model = IncidentFeedback::class;

    public function definition(): array
    {
        return [
            'incident_id' => Incident::factory()->traite(),
            'user_id' => User::factory()->citoyen(),
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->optional()->sentence(12),
            'resolution_confirmed' => true,
            'complaint_description' => null,
            'reopen_requested' => false,
            'complaint_status' => null,
            'complaint_decision' => null,
            'manager_response' => null,
            'decision_reason' => null,
            'reopen_decision_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'reopened_at' => null,
        ];
    }

    public function complaint(bool $reopenRequested = false): static
    {
        return $this->state(fn () => [
            'rating' => fake()->numberBetween(1, 3),
            'resolution_confirmed' => false,
            'complaint_description' => 'Le problème persiste malgré l\'intervention annoncée par les services techniques.',
            'reopen_requested' => $reopenRequested,
            'complaint_status' => 'en_attente',
        ]);
    }

    public function enExamen(?User $manager = null): static
    {
        return $this->state(fn () => [
            'complaint_status' => 'en_examen',
            'reviewed_by' => $manager?->id ?? User::factory()->gestionnaire(),
            'reviewed_at' => now(),
        ]);
    }

    public function acceptee(?User $manager = null): static
    {
        return $this->state(fn () => [
            'complaint_status' => 'acceptee',
            'complaint_decision' => 'acceptee',
            'decision_reason' => 'La réclamation est fondée au vu des éléments fournis.',
            'reviewed_by' => $manager?->id ?? User::factory()->gestionnaire(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejetee(?User $manager = null): static
    {
        return $this->state(fn () => [
            'complaint_status' => 'rejetee',
            'complaint_decision' => 'rejetee',
            'decision_reason' => 'Les vérifications terrain ne confirment pas la persistance du problème.',
            'reviewed_by' => $manager?->id ?? User::factory()->gestionnaire(),
            'reviewed_at' => now(),
        ]);
    }

    public function cloturee(?User $manager = null): static
    {
        return $this->state(fn () => [
            'complaint_status' => 'cloturee',
            'complaint_decision' => 'acceptee',
            'decision_reason' => 'La réclamation est fondée au vu des éléments fournis.',
            'reviewed_by' => $manager?->id ?? User::factory()->gestionnaire(),
            'reviewed_at' => now(),
        ]);
    }
}
