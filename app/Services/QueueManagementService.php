<?php

namespace App\Services;

use App\Models\DailyScheduleSlot;
use App\Models\VehicleGroup;
use App\Models\Queue;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Vehicle;

class QueueManagementService
{
    private const GROUP_COUNTS = [
        'Bus'        => 2,
        'UV-express' => 3,
    ];

    public function scheduleBlockReason(Vehicle $vehicle): ?string
    {
        $today = today()->toDateString();

        $slot = DailyScheduleSlot::where('schedule_date', $today)
            ->where('metadata->assigned_vehicle_id', $vehicle->id)
            ->first();

        // The vehicle IS in today's active group
        if ($slot) {
            return match ($slot->status) {
                'waiting', 'queued' => null, // allowed to try
                'skipped'           => 'Your vehicle was skipped for this round. Please wait for the next round.',
                default             => 'Your turn has passed for this round. Please wait for the rest of your group to depart before the next round begins.',
            };
        }

        // The vehicle has no slot today: its group is not the active one
        $activeSlot = DailyScheduleSlot::with('vehicleGroup')
            ->where('schedule_date', $today)
            ->where('metadata->vehicle_type', $vehicle->vehicle_type)
            ->first();

        if (! $activeSlot) {
            return "No {$vehicle->vehicle_type} schedule has been generated for today. Please contact the terminal admin.";
        }

        $myGroup     = $vehicle->vehicle_group()->value('group_number');
        $activeGroup = $activeSlot->vehicleGroup?->group_number;

        if (! $myGroup) {
            return "This vehicle is not assigned to any {$vehicle->vehicle_type} group. Please contact the terminal admin.";
        }

        return "Not scheduled today. This vehicle is in Group {$myGroup}, but today's active {$vehicle->vehicle_type} group is Group {$activeGroup}. Please queue on your group's scheduled day.";
    }
    
    public function expireStaleEntries(): int
    {
        return DB::transaction(function () {
            $staleSlotIds = DailyScheduleSlot::where('schedule_date', '<', today()->toDateString())->pluck('id');

            $count = Queue::where('status', 'staging')
                ->whereIn('daily_schedule_slot_id', $staleSlotIds)
                ->update(['status' => 'skipped']);

            DailyScheduleSlot::whereIn('id', $staleSlotIds)
                ->where('status', 'waiting')
                ->update(['status' => 'skipped']);

            return $count;
        });
    }

    private function resolveNextGroup(string $vehicleType, string $targetDate): array
    {
        $maxGroups = self::GROUP_COUNTS[$vehicleType] ?? 2;

        // 1. INTRADAY ROUND CHECK: Check if a group is already running today
        $todaySlot = DailyScheduleSlot::where('schedule_date', $targetDate)
            ->where('metadata->vehicle_type', $vehicleType)
            ->with('vehicleGroup')
            ->first();

        if ($todaySlot && $todaySlot->vehicleGroup) {
            // It is the same day. Keep the EXACT same group, just advance the round number
            $currentGroup = $todaySlot->vehicleGroup->group_number;
            $currentRound = $todaySlot->metadata['round_number'] ?? 1;
            $direction    = $todaySlot->metadata['direction'] ?? 'forward';

            return ['group' => $currentGroup, 'direction' => $direction, 'round' => $currentRound + 1];
        }

        // 2. NEW DAY GROUP ROTATION: Find the absolute latest slot to calculate the next day's group
        $lastSlot = DailyScheduleSlot::where('metadata->vehicle_type', $vehicleType)
            ->with('vehicleGroup')
            ->orderBy('schedule_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if (!$lastSlot || !$lastSlot->vehicleGroup) {
            return ['group' => 1, 'direction' => 'forward', 'round' => 1];
        }

        $lastGroup = $lastSlot->vehicleGroup->group_number;
        $nextGroup = ($lastGroup % $maxGroups) + 1;

        $lastTurnForNextGroup = DailyScheduleSlot::where('metadata->vehicle_type', $vehicleType)
            ->whereHas('vehicleGroup', fn($q) => $q->where('group_number', $nextGroup))
            ->orderBy('schedule_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        $direction = 'forward';
        if ($lastTurnForNextGroup && isset($lastTurnForNextGroup->metadata['direction'])) {
            $direction = $lastTurnForNextGroup->metadata['direction'] === 'forward'
                ? 'reverse'
                : 'forward';
        }

        return ['group' => $nextGroup, 'direction' => $direction, 'round' => 1];
    }

    public function generateSchedule($targetDate = null)
    {
        $targetDate = $targetDate
            ? Carbon::parse($targetDate)->toDateString()
            : today()->toDateString();

        $scheduleData = [];

        $this->expireStaleEntries();

        foreach (array_keys(self::GROUP_COUNTS) as $vehicleType) {
            
            // 1. ROUND EXISTENCE CHECK: Get all slots for this vehicle type today
            $existingSlots = DailyScheduleSlot::where('schedule_date', $targetDate)
                ->where('metadata->vehicle_type', $vehicleType)
                ->get();

            if ($existingSlots->isNotEmpty()) {
                // Are ALL of today's slots departed? 
                
                $allDeparted = $existingSlots->every(fn($slot) => in_array($slot->status, ['departed', 'skipped']));

                if (!$allDeparted) {
                    continue; // Group is still queueing/loading. Skip this vehicle type.
                }
            }

            ['group' => $activeGroup, 'direction' => $direction, 'round' => $round] = $this->resolveNextGroup($vehicleType, $targetDate);

            $sortOrder = $direction === 'forward' ? 'asc' : 'desc';

            $assignments = VehicleGroup::where('group_number', $activeGroup)
                ->whereHas('vehicle', fn($q) => $q->where('vehicle_type', $vehicleType))
                ->orderBy('order_number', $sortOrder)
                ->with(['vehicle.user', 'vehicle.route_list'])
                ->get();

            if ($assignments->isNotEmpty()) {
                $scheduleData[$vehicleType] = compact('activeGroup', 'direction', 'round', 'assignments');
            }
        }

        if (empty($scheduleData)) {
            return [
                'success' => false,
                'message' => "Schedule for {$targetDate} is currently active and waiting for rounds to finish.",
            ];
        }

        DB::transaction(function () use ($scheduleData, $targetDate) {

            foreach ($scheduleData as $vehicleType => $data) {

                $position = 0;
                
                foreach ($data['assignments'] as $assignment) {
                    $vehicle = $assignment->vehicle;

                    if (!$vehicle) {
                        continue;
                    }

                    $position++;

                    // Clever fix: use updateOrCreate to recycle the slot instead of crashing the unique index constraint!
                    $slot = DailyScheduleSlot::updateOrCreate(
                        [
                            'schedule_date'    => $targetDate,
                            'vehicle_group_id' => $assignment->id,
                        ],
                        [
                            'slot_position'    => $position,
                            'status'           => 'waiting',
                            'metadata'         => [
                                'direction'           => $data['direction'],
                                'assigned_vehicle_id' => $vehicle->id,
                                'vehicle_type'        => $vehicleType, 
                                'round_number'        => $data['round'], 
                            ],
                        ]
                    );

                    // Create the fresh Staging Queue for this new round
                    Queue::create([
                        'user_id'                => $vehicle->user?->id,
                        'vehicle_id'             => $vehicle->id,
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
                        'slot_position'          => $position,
                        'daily_schedule_slot_id' => $slot->id,
                    ]);
                }
            }
        });

        $summaries = collect($scheduleData)
            ->map(fn($data, $type) => "{$type}: Group {$data['activeGroup']} - Round {$data['round']} ({$data['direction']})")
            ->implode(' | ');

        return [
            'success' => true,
            'message' => "Schedule generated — {$summaries}.",
        ];
    }
}