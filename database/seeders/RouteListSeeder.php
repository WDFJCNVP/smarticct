<?php

namespace Database\Seeders;

use App\Models\OperatorTicketRate;
use App\Models\RouteList;
use Illuminate\Database\Seeder;

class RouteListSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * This is what shows up on the admin "Routes" page (App\Models\RouteList)
     * — NOT the `routes` table (App\Models\Route), which stores a single
     * vehicle's own trip schedule.
     *
     * Source: official fare table, Office of the Iriga City Central
     * Terminal. Fare is fixed PER ROUTE (vehicle type + destination), not
     * a flat rate per vehicle type — a Jeep to Baao (₱20) and a Jeep to
     * Pili (₱50) are both "Jeep" but different fares because they're
     * different distances. The "Mountain Unit" row in the source document
     * (Iriga–Sagrada, ₱70) is folded into Jeep here, since that's the
     * vehicle type actually running it — "Mountain Unit" isn't an option
     * in the vehicle-type dropdown.
     *
     * The document's "NO. of Operators" column isn't seeded — there's no
     * column for it on route_lists, it's just headcount info from the
     * source document.
     */
    // public function run(): void
    // {
    //     $routes = [
    //         'Bus'        => [
    //             'Naga' => 70,
    //         ],
    //         'UV-express' => [
    //             'Naga'    => 100,
    //             'Legazpi' => 170,
    //         ],
    //         'Jeep'       => [
    //             'Baao'    => 20,
    //             'Buhi'    => 34,
    //             'Bato'    => 35,
    //             'Pili'    => 50,
    //             'Mountain Unit' => 70, // "Mountain Unit" route in the source document
    //         ],
    //         'Multi-cab'  => [
    //             'Nabua' => 15,
    //             'Baao'  => 20,
    //         ],
    //     ];

    //     foreach ($routes as $vehicleType => $destinations) {
    //         $rate = OperatorTicketRate::where('vehicle_type', $vehicleType)->first();

    //         if (! $rate) {
    //             $this->command?->warn("Skipping {$vehicleType} routes: no matching OperatorTicketRate row. Run OperatorTicketRateSeeder first.");
    //             continue;
    //         }

    //         foreach ($destinations as $terminal => $fare) {
    //             RouteList::updateOrCreate(
    //                 [
    //                     'operator_ticket_rate_id' => $rate->id,
    //                     'terminal'                => $terminal,
    //                 ],
    //                 [
    //                     'fare'     => $fare,
    //                     'metadata' => [
    //                         'first_trip' => '05:00',
    //                         'last_trip'  => '21:00',
    //                     ],
    //                 ]
    //             );
    //         }
    //     }
    // }
}
