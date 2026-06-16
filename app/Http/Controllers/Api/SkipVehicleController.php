<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

use App\Models\Queue;
use App\Jobs\ProcessAfterDepart;
use App\Events\QueuedVehicleEvent;

class SkipVehicleController extends Controller
{
    /**
     * Dispatcher marks a vehicle as skipped.
     *
     * Flow:
     *  1. Mark target as 'skipped', demote slot_position to N+1
     *  2. Find the next vehicle in line by slot_position
     *  3a. Next is 'paid'    → auto-start timer immediately
     *  3b. Next is 'staging' → promote to 'loading', wait for their tap to start timer
     */
    public function skip(Request $request)
    {
        $validated = $request->validate([
            'queue_id' => 'required|integer|exists:queues,id',
        ]);

        try {
            $result = DB::transaction(function () use ($validated) {
                $target = Queue::where('id', $validated['queue_id'])
                    ->whereIn('status', ['staging', 'loading', 'paid'])
                    ->whereNotNull('daily_schedule_slot_id')
                    ->lockForUpdate()
                    ->first();

                if (!$target) {
                    return [
                        'success' => false,
                        'message' => 'Queue entry not found or cannot be skipped.',
                    ];
                }

                $skippedPosition = $target->slot_position;

                // 1. Mark as skipped, demote to N+1 for re-entry later
                $target->update([
                    'status'        => 'skipped',
                    'slot_position' => $skippedPosition + 1,
                ]);
                $target->dailyScheduleSlot?->update(['status' => 'skipped']);

                // 2. Find the next vehicle in line
                $next = Queue::where('vehicle_type', $target->vehicle_type)
                    ->whereIn('status', ['staging', 'paid'])
                    ->whereNotNull('daily_schedule_slot_id')
                    ->whereHas('dailyScheduleSlot', fn($q) => $q->where('schedule_date', today()))
                    ->orderBy('slot_position', 'asc')
                    ->lockForUpdate()
                    ->first();

                if (!$next) {
                    return [
                        'success'  => true,
                        'message'  => 'Vehicle skipped. No more vehicles in queue for today.',
                        'promoted' => null,
                    ];
                }

                // 3a. Already paid — auto-start timer, no tap needed
                if ($next->status === 'paid') {
                    $departsAt = ProcessAfterDepart::startTimer($next);

                    return [
                        'success'  => true,
                        'message'  => "Vehicle skipped. Next vehicle ({$next->plate_number}) was pre-paid — timer started automatically.",
                        'promoted' => [
                            'queue_id'     => $next->id,
                            'plate_number' => $next->plate_number,
                            'driver_name'  => $next->driver_name,
                            'status'       => 'loading',
                            'departs_at'   => $departsAt?->toIso8601String(),
                        ],
                    ];
                }

                // 3b. Not yet paid — promote to loading, wait for their tap
                $next->update([
                    'status'      => 'loading',
                    'time_queued' => now(),
                ]);
                $next->dailyScheduleSlot?->update(['status' => 'queued']);

                return [
                    'success'  => true,
                    'message'  => "Vehicle skipped. Next vehicle ({$next->plate_number}) is now loading — waiting for tap to start timer.",
                    'promoted' => [
                        'queue_id'     => $next->id,
                        'plate_number' => $next->plate_number,
                        'driver_name'  => $next->driver_name,
                        'status'       => 'loading',
                        'departs_at'   => null,
                    ],
                ];
            });

            broadcast(new QueuedVehicleEvent());

            return response()->json($result, $result['success'] ? 200 : 422);

        } catch (\Exception $e) {
            Log::error('Skip vehicle error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}