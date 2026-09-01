<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Order matters: reference data first (rates, terminals, route
     * listings), then the accounts, then the vehicles that depend on
     * both the accounts and the route listings.
     */
    public function run(): void
    {
        // --- Reference / lookup data -----------------------------------
        $this->call([
            OperatorTicketRateSeeder::class, // vehicle types + queueing fees
            TerminalSeeder::class,           // terminals/municipalities
            RouteListSeeder::class,          // "Routes" admin page entries
        ]);

        // --- Fixed admin/cashier test accounts --------------------------
        User::updateOrCreate(
            ['email_address' => 'admin@example.test'],
            [
                'name'              => 'Admin',
                'role'              => 'admin',
                'commuter_type'     => 'regular',
                'password'          => '12345678',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email_address' => 'cashier@example.test'],
            [
                'name'              => 'Cashier',
                'role'              => 'cashier',
                'commuter_type'     => 'regular',
                'password'          => '12345678',
                'email_verified_at' => now(),
            ]
        );

        // --- 5 operator + 5 commuter test accounts ----------------------
        $this->call([
            UserSeeder::class,
        ]);

        // --- Vehicles for the seeded operators ---------------------------
        $this->call([
            VehicleSeeder::class,
        ]);
    }
}
