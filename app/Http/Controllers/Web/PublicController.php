<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Carbon;

use App\Models\Vehicle;
use App\Models\Queue;

class PublicController extends Controller
{
    public function index() {
        return view('welcome');
    }

    public function routes() {
        return view('routes');
    }

    public function queue() {
        
        return view('queue');
    }

    public function queuePartial() {
        $group_vehicles = $this->groupVehicles();

        return view('partials.queue-list', compact('group_vehicles'));
    }

    public function fare() {
        return view('fare');
    }

    private function groupVehicles() {
        return Queue::whereIn('status', ['staging', 'loading'])
            ->orderBy('time_queued')
            ->get()
            ->groupBy(['vehicle_type', 'destination']);
    }
}
