<?php

namespace Database\Seeders;

use App\Models\Route;
use App\Models\User;
use App\Models\Card;
use App\Models\Vehicle;
use App\Models\Terminal;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Terminal::factory(10)->create();

        User::factory(100)
            ->has(Card::factory())
            ->has(Vehicle::factory()
                ->has(Route::factory()))
            ->create();

    }
}
