<?php

namespace Database\Factories\Incident;

use App\Models\Incident\Incident;
use App\Models\Incident\IncidentSimilarity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncidentSimilarity>
 */
class IncidentSimilarityFactory extends Factory
{
    protected $model = IncidentSimilarity::class;

    public function definition(): array
    {
        return [
            'incident_id' => Incident::factory(),
            'similar_incident_id' => Incident::factory(),
            'score' => fake()->numberBetween(60, 100),
            'method' => IncidentSimilarity::METHOD_LOCAL,
            'ai_justification' => null,
            'status' => IncidentSimilarity::STATUS_SUGGESTED,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'decision_note' => null,
        ];
    }
}
