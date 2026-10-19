<?php

namespace Database\Factories\Incident;

use App\Models\Incident\Incident;
use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Zone;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    public function definition(): array
    {
        $type = fake()->randomElement(array_keys(Incident::TYPES));
        $severity = fake()->randomElement(array_keys(Incident::SEVERITIES));
        $priority = match ($severity) {
            'critique' => 'urgente',
            'haute' => 'haute',
            'faible' => 'basse',
            default => 'normale',
        };

        return [
            'user_id' => User::factory()->citoyen(),
            'zone_id' => Zone::factory(),
            'infrastructure_id' => null,
            'type' => $type,
            'title' => fake()->sentence(6),
            'description' => fake()->paragraphs(2, true),
            'address' => fake()->streetAddress(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'severity' => $severity,
            'status' => 'nouveau',
            'priority' => $priority,
            'ai_category' => $type,
            'ai_confidence' => fake()->randomFloat(2, 55, 98),
            'ai_summary' => 'Classification locale indicative basée sur les mots-clés du signalement.',
            'is_duplicate' => false,
            'duplicate_of_id' => null,
            'assigned_to' => null,
            'reported_at' => fake()->dateTimeBetween('-3 months', 'now'),
            'resolved_at' => null,
            'resolution_note' => null,
            'rejection_reason' => null,
        ];
    }

    public function traite(): static
    {
        return $this->state(fn () => [
            'status' => 'traite',
            'resolved_at' => now(),
            'resolution_note' => 'Intervention réalisée, alimentation rétablie.',
            'rejection_reason' => null,
        ]);
    }

    public function rejete(): static
    {
        return $this->state(fn () => [
            'status' => 'rejete',
            'resolved_at' => null,
            'resolution_note' => null,
            'rejection_reason' => 'Signalement non recevable après vérification terrain.',
        ]);
    }

    public function enCours(): static
    {
        return $this->state(fn () => [
            'status' => 'en_cours',
            'assigned_to' => User::factory()->gestionnaire(),
        ]);
    }

    public function forInfrastructure(?Infrastructure $infrastructure = null): static
    {
        return $this->state(fn () => [
            'infrastructure_id' => $infrastructure?->id ?? Infrastructure::factory(),
        ]);
    }
}
