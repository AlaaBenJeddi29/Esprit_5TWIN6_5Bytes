<?php

namespace Database\Factories\Infrastructure;

use App\Models\Infrastructure\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
{
    protected $model = Zone::class;

    public function definition(): array
    {
        return [
            'nom' => 'Zone '.fake()->unique()->citySuffix(),
            'commune' => fake()->city(),
            'code_postal' => fake()->postcode(),
            'population' => fake()->numberBetween(1000, 100000),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
        ];
    }
}
