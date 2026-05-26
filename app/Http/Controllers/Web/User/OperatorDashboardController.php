<?php

namespace App\Http\Controllers\Web\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vehicle;
use App\Models\Queue;

class OperatorDashboardController extends Controller
{
    public function index() {
        return view('user.operator.index');
    }

    public function vehicles() {

        $vehicle = Vehicle::with('user','route.terminal')->where('user_id', auth()->user()->id)->get();

        return view('pages.content-by-role.operator.vehicles', ['vehicles' => $vehicle]);
    }

    public function travelRecord(Vehicle $vehicle) {

        $queues = Queue::where('vehicle_type', $vehicle->vehicle_type)
                        ->where('plate_number', $vehicle->plate_number)
                        ->latest()
                        ->paginate(10);

        return view('pages.content-by-role.operator.travel_records', [
            'vehicle' => $vehicle,
            'queues'  => $queues
        ]);
    }
}
