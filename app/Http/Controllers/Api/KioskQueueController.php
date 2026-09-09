<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Queue;
use App\Models\RouteList;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

class KioskQueueController extends Controller
{
    /**
     * Return active queued vehicles grouped by destination for the kiosk UI.
     */
    public function getAvailableRides(): JsonResponse
    {
        // 1. Fetch only active loading vehicles
        $activeQueues = Queue::where('status', 'loading')
            ->where('admin_deleted', false)
            ->where('user_deleted', false)
            ->orderBy('slot_position', 'asc')
            ->orderBy('time_queued', 'asc')
            ->get();

        // 2. Pre-load all routes and ticket rates into memory to prevent N+1 queries
        $activeRouteFares = RouteList::with('operatorTicketRate')->get();

        $destinations = ['Naga', 'Legaspi', 'Baao', 'Buhi', 'Mountain Unit'];
        $groupedRoutes = [];

        foreach ($destinations as $destination) {
            $routesForDest = $activeQueues->where('destination', $destination);
            $groupedRoutes[$destination] = [];

            // Group by vehicle type (Bus, Jeep, UV-express, Multi-cab)
            $vehicleGroups = $routesForDest->groupBy('vehicle_type');

            foreach ($vehicleGroups as $vehicleType => $queueItems) {
                /** @var \App\Models\Queue $primaryQueue */
                $primaryQueue = $queueItems->first();

                // Convert departs_at to epoch milliseconds for the kiosk Alpine.js countdown
                $departsAtTimestamp = $primaryQueue->departs_at
                    ? Carbon::parse($primaryQueue->departs_at)->timestamp * 1000
                    : null;

                $isFull = $primaryQueue->seat_count >= $primaryQueue->seat_capacity;

                $groupedRoutes[$destination][] = [
                    'queue_id'             => $primaryQueue->id,
                    'type'                 => $primaryQueue->vehicle_type,
                    'icon'                 => $this->resolveVehicleIcon($primaryQueue->vehicle_type),
                    'plate_number'         => $primaryQueue->plate_number,
                    'capacity_current'     => (int) $primaryQueue->seat_count,
                    'capacity_max'         => (int) $primaryQueue->seat_capacity,
                    'fare'                 => $this->resolveFare($destination, $primaryQueue->vehicle_type, $activeRouteFares),
                    'departs_at'           => $primaryQueue->departs_at ? Carbon::parse($primaryQueue->departs_at)->toIso8601String() : null,
                    'departs_at_timestamp' => $departsAtTimestamp,
                    'status'               => $primaryQueue->status,
                    'is_full'              => $isFull,
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'data'   => $groupedRoutes,
        ]);
    }

    /**
     * Map vehicle types to Flux icon names.
     */
    private function resolveVehicleIcon(string $type): string
    {
        return match (strtolower(trim($type))) {
            'uv express', 'uv-express' => 'car',
            default                    => 'truck',
        };
    }

    /**
     * Dynamically resolve fare using the database RouteList and OperatorTicketRate models.
     */
    private function resolveFare(string $destination, string $type, ?Collection $routeFares = null): float
    {
        $normalizedType = strtolower(str_replace('-', ' ', trim($type)));

        // If a preloaded collection is passed, search in memory
        if ($routeFares) {
            $matchedRoute = $routeFares->first(function ($route) use ($destination, $normalizedType) {
                $dbTerminal = strtolower(trim($route->terminal));
                $dbVehicleType = strtolower(str_replace('-', ' ', trim($route->operatorTicketRate->vehicle_type ?? '')));

                return $dbTerminal === strtolower(trim($destination)) && $dbVehicleType === $normalizedType;
            });

            return $matchedRoute ? (float) $matchedRoute->fare : 0.00;
        }

        // Fallback: Direct DB query (mirrors CardController logic)
        $route = RouteList::where('terminal', $destination)
            ->whereHas('operatorTicketRate', function ($query) use ($type) {
                $query->where('vehicle_type', $type)
                    ->orWhere('vehicle_type', str_replace('-', ' ', $type))
                    ->orWhere('vehicle_type', str_replace(' ', '-', $type));
            })
            ->first();

        return $route ? (float) $route->fare : 0.00;
    }
}