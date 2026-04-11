<?php

namespace Database\Factories;

use App\Models\Vehicle;
use App\Models\Terminal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Route>
 */
class RouteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstTripAt = fake()->dateTimeBetween('today 04:00', 'today 09:00');
        $lastTripAt = (clone $firstTripAt)->modify('+' . fake()->numberBetween(8, 14) . ' hours');

        return [
            'vehicle_id' => Vehicle::factory(),
            'terminal_id' => fn () => Terminal::query()->inRandomOrder()->first()->id,
            'first_trip' => $firstTripAt->format('H:i'),
            'last_trip' => $lastTripAt->format('H:i'),
            'base_fare' => fake()->randomFloat(2, 10, 100),
        ];
    }
}
