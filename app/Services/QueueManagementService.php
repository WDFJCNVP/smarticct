<?php

namespace App\Services;

use App\Models\DailyScheduleSlot;
use App\Models\VehicleGroup;
use App\Models\Queue;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class QueueManagementService
{
    public function generateSchedule($targetDate = null)
    {
        $targetDate = $targetDate ? Carbon::parse($targetDate)->toDateString() : today()->toDateString();

        // 1. Idempotency Guard: Prevent generating duplicate schedules if the service is triggered twice
        $exists = DailyScheduleSlot::where('schedule_date', $targetDate)->exists();
        if ($exists) {
            return [
                'success' => false,
                'message' => "Schedule for {$targetDate} has already been generated.",
            ];
        }

        // 2. Determine which group's turn it is
        $lastScheduledDay = DailyScheduleSlot::with('vehicleGroup')
            ->orderBy('schedule_date', 'desc')
            ->first();

        $activeGroup = 1;
        if ($lastScheduledDay && $lastScheduledDay->vehicleGroup) {
            $activeGroup = ($lastScheduledDay->vehicleGroup->group_number == 1) ? 2 : 1;
        }

        // 3. Determine direction (forward or reverse) for fair rotation
        $lastGroupTurn = DailyScheduleSlot::whereHas('vehicleGroup', function ($query) use ($activeGroup) {
                $query->where('group_number', $activeGroup);
            })
            ->orderBy('schedule_date', 'desc')
            ->first();

        $direction = 'forward';
        if ($lastGroupTurn && isset($lastGroupTurn->metadata['direction'])) {
            $direction = ($lastGroupTurn->metadata['direction'] === 'forward') ? 'reverse' : 'forward';
        }

        $sortOrder = ($direction === 'forward') ? 'asc' : 'desc';

        // 4. Fetch the prioritized vehicle assignments
        $scheduledAssignments = VehicleGroup::where('group_number', $activeGroup)
            ->orderBy('order_number', $sortOrder)
            ->with(['vehicle.user', 'vehicle.route_list'])
            ->get();

        $busPosition = 0;
        $vanPosition = 0;

        // 5. Wrap database inserts in a transaction to ensure an "all-or-nothing" execution
        DB::transaction(function () use ($scheduledAssignments, $targetDate, $direction, &$busPosition, &$vanPosition) {
            foreach ($scheduledAssignments as $assignment) {
                $vehicle = $assignment->vehicle; 
                
                // Safety check: skip if the assignment has a missing or deleted vehicle record
                if (!$vehicle) {
                    continue;
                }

                $vehicleType = $vehicle->vehicle_type;

                if ($vehicleType === 'Bus') {
                    $busPosition++;
                    $currentPosition = $busPosition;
                } elseif ($vehicleType === 'UV-express') {
                    $vanPosition++;
                    $currentPosition = $vanPosition;
                } else {
                    continue; // Skip unrecognized vehicle types safely
                }

                $slot = DailyScheduleSlot::create([
                    'schedule_date'    => $targetDate,
                    'vehicle_group_id' => $assignment->id,
                    'slot_position'    => $currentPosition,
                    'status'           => 'waiting',
                    'metadata'         => [
                        'direction'           => $direction,
                        'assigned_vehicle_id' => $vehicle->id,
                    ],
                ]);

                Queue::create([
                    'user_id'                => $vehicle->user?->id,
                    'vehicle_type'           => $vehicleType,
                    'plate_number'           => $vehicle->plate_number,
                    'driver_name'            => $vehicle->user?->name ?? 'Unknown Driver',
                    'seat_capacity'          => $vehicle->total_seats,
                    'seat_count'             => 0,
                    'time_queued'            => now(),
                    'time_departed'          => null,
                    'destination'            => $vehicle->route_list?->terminal ?? 'Main Route',
                    'status'                 => 'staging',
                    'departs_at'             => null,
                    'slot_position'          => $currentPosition,
                    'daily_schedule_slot_id' => $slot->id,
                ]);
            }
        });

        return [
            'success' => true,
            'message' => "Successfully generated schedule for Group {$activeGroup} ({$direction}) on {$targetDate}.",
        ];
    }
}