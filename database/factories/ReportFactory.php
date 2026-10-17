<?php

namespace Database\Factories;

use App\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Report>
     */
    protected $model = Report::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'type' => fake()->randomElement(array_keys(Report::TYPES)),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'address' => fake()->streetAddress(),
            'status' => fake()->randomElement(array_keys(Report::STATUSES)),
            'reported_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
