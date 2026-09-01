<?php

namespace Database\Seeders;

use App\Models\OperatorTicketRate;
use Illuminate\Database\Seeder;

class OperatorTicketRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * "Queueing fee" = the amount an operator pays per vehicle type
     * every time it queues at a terminal. One row per vehicle type,
     * matching the exact values used in the vehicle-type dropdown
     * (resources/views/pages/settings/⚡vehicle-type-page.blade.php).
     */
    // public function run(): void
    // {
    //     $rates = [
    //         ['vehicle_type' => 'Bus',        'queueing_fee' => 50.00],
    //         ['vehicle_type' => 'UV-express', 'queueing_fee' => 30.00],
    //         ['vehicle_type' => 'Multi-cab',  'queueing_fee' => 15.00],
    //         ['vehicle_type' => 'Jeep',       'queueing_fee' => 10.00],
    //     ];

    //     foreach ($rates as $rate) {
    //         OperatorTicketRate::updateOrCreate(
    //             ['vehicle_type' => $rate['vehicle_type']],
    //             ['queueing_fee' => $rate['queueing_fee']]
    //         );
    //     }
    // }
}
