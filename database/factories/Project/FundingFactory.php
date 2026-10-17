<?php

namespace Database\Factories\Project;

use App\Models\Project\Funding;
use App\Models\Project\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Funding>
 */
class FundingFactory extends Factory
{
    protected $model = Funding::class;

    public function definition(): array
    {
        $source = fake()->randomElement(Funding::SOURCES);

        return [
            'project_id' => Project::factory(),
            'source' => $source,
            'donateur_id' => null,
            'montant' => $source === 'don'
                ? fake()->randomFloat(2, 50, 5000)
                : fake()->randomFloat(2, 5000, 200000),
            'date_versement' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }

    public function municipal(): static
    {
        return $this->state(fn () => [
            'source' => 'municipal',
            'donateur_id' => null,
        ]);
    }

    public function don(): static
    {
        return $this->state(fn () => [
            'source' => 'don',
            'donateur_id' => \App\Models\User::factory()->citoyen(),
            'montant' => fake()->randomFloat(2, 50, 2000),
        ]);
    }
}
