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
        ];
    }
}
