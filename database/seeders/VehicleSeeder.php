<?php

namespace Database\Seeders;

use App\Models\OperatorTicketRate;
use App\Models\Route;
use App\Models\RouteList;
use App\Models\Terminal;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Gives each seeded operator one vehicle. Not every vehicle type
     * serves every terminal (e.g. Bus only runs to Naga, Jeep never runs
     * to Legazpi) — so instead of picking a random terminal and hoping a
     * matching route exists, we pick a random *route* for the vehicle's
     * type first, then derive its terminal from that. This guarantees
     * route_list_id and terminal_id are always a real, valid pairing.
     */
    public function run(): void
    {
        $vehicleTypes = OperatorTicketRate::pluck('vehicle_type')->values();

        if ($vehicleTypes->isEmpty()) {
            $this->command?->warn('Skipping VehicleSeeder: run OperatorTicketRateSeeder first.');
            return;
        }

        User::where('role', 'operator')->get()->each(function (User $operator, int $index) use ($vehicleTypes) {
            $vehicleType = $vehicleTypes[$index % $vehicleTypes->count()];

            // Pick a random real route for this vehicle type (e.g. a Jeep
            // might land on Baao, Buhi, Bato, Pili, or Sagrada).
            $routeList = RouteList::whereHas(
                'operatorTicketRate',
                fn ($q) => $q->where('vehicle_type', $vehicleType)
            )->inRandomOrder()->first();

            if (! $routeList) {
                $this->command?->warn("Skipping vehicle for {$operator->email_address}: no RouteList found for {$vehicleType}. Run RouteListSeeder first.");
                return;
            }

            $terminal = Terminal::where('municipality', $routeList->terminal)->first();

            $vehicle = Vehicle::updateOrCreate(
                ['user_id' => $operator->id],
                [
                    'route_list_id'         => $routeList->id,
                    'vehicle_type'           => $vehicleType,
                    'plate_number'           => strtoupper(fake()->unique()->bothify('???-####')),
                    'total_seats'            => match ($vehicleType) {
                        'Bus'        => fake()->numberBetween(40, 60),
                        'UV-express' => fake()->numberBetween(10, 18),
                        default      => fake()->numberBetween(14, 24),
                    },
                    'has_or_cr'              => true,
                    'or_cr_expiry_date'      => fake()->dateTimeBetween('+1 month', '+2 years'),
                    'has_franchise'          => true,
                    'franchise_expiry_date'  => fake()->dateTimeBetween('+1 month', '+2 years'),
                    'driver_name'            => fake()->name(),
                ]
            );

            Route::updateOrCreate(
                ['vehicle_id' => $vehicle->id],
                [
                    'terminal_id' => $terminal?->id,
                    'first_trip'  => '05:00',
                    'last_trip'   => '21:00',
                    'base_fare'   => $routeList->fare,
                ]
            );
        });
    }
}
