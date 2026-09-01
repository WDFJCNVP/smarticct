<?php

namespace Database\Seeders;

use App\Models\Terminal;
use Illuminate\Database\Seeder;

class TerminalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Destination terminals served out of the Iriga City Central Terminal,
     * per the official fare table (Office of the Iriga City Central
     * Terminal). "Sagrada" is the last stop of the jeep route that the
     * fare table separately labels "Mountain Unit" — it's the same Jeep
     * vehicle type, just a different destination.
     */
    // public function run(): void
    // {
    //     $municipalities = [
    //         'Naga',
    //         'Legazpi',
    //         'Baao',
    //         'Buhi',
    //         'Bato',
    //         'Pili',
    //         'Nabua',
    //         'Mountain Unit',
    //     ];

    //     foreach ($municipalities as $municipality) {
    //         Terminal::firstOrCreate(['municipality' => $municipality]);
    //     }
    // }
}
