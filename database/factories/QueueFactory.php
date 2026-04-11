<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

use App\Models\Queue;
use App\Models\Terminal;
use App\Models\Vehicle;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Queue>
 */
class QueueFactory extends Factory
{
    protected $model = Queue::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_type' => fake()->randomElement(['Bus', 'Modern Jeepney', 'Van']),
            'destination' => fn () => Terminal::query()->inRandomOrder()->value('municipality') ?? fake()->city(),
            'plate_number' => strtoupper(fake()->bothify('???-####')),
            'driver_name' => fake()->name(),
            'status' => fake()->randomElement(['loading', 'staging', 'departed']),
            'seat_capacity' => fake()->numberBetween(10, 60),
            'seat_count' => 0,
            'time_queued' => now(),
            'departs_at' => now()->addMinutes(fake()->numberBetween(5, 90)),
            'time_departed' => null,
        ];
    }

    public function fromVehicle(Vehicle $vehicle): static
    {
        $vehicle->loadMissing(['user', 'route.terminal']);

        $driverName = trim(($vehicle->user->first_name ?? '') . ' ' . ($vehicle->user->last_name ?? ''));

        return $this->state(fn () => [
            'vehicle_type' => $vehicle->vehicle_type,
            'destination' => $vehicle->route?->terminal?->municipality
                ?? Terminal::query()->inRandomOrder()->value('municipality')
                ?? fake()->city(),
            'plate_number' => $vehicle->plate_number,
            'driver_name' => $driverName !== '' ? $driverName : fake()->name(),
            'seat_capacity' => $vehicle->total_seats,
            'seat_count' => 0,
            'status' => fake()->randomElement(['loading', 'staging', 'departed']),
            'time_queued' => now(),
            'departs_at' => now()->addMinutes(fake()->numberBetween(5, 90)),
            'time_departed' => null,
        ]);
    }
}
