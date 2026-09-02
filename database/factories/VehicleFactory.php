<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'vehicle_type' => fake()->randomElement(['Bus', 'Modern Jeepney', 'Van']),
            'plate_number' => strtoupper(fake()->bothify('???-####')),
            'total_seats' => fake()->numberBetween(10, 60),
            'engine_number' => strtoupper(fake()->bothify('EN-#####')),
            'body_number' => strtoupper(fake()->bothify('BD-#####')),
            'chassis_number' => strtoupper(fake()->bothify('CH-#########')),
            'has_franchise' => true,
            'franchise_expiry_date' => fake()->dateTimeBetween('+1 month', '+2 years'),
        ];
    }
}