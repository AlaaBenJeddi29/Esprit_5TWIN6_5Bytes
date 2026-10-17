<?php

namespace Database\Factories;

use App\Models\AlertSubscription;
use App\Models\Infrastructure\Zone;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlertSubscription>
 */
class AlertSubscriptionFactory extends Factory
{
    protected $model = AlertSubscription::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->citoyen(),
            'zone_id' => Zone::factory(),
            'type_alerte' => fake()->randomElement(array_keys(AlertSubscription::TYPES)),
            'actif' => true,
        ];
    }
}
