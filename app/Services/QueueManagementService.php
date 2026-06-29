<?php

namespace App\Services;

use App\Models\DailyScheduleSlot;
use App\Models\VehicleGroup;
use App\Models\Queue;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class QueueManagementService
{
    private const GROUP_COUNTS = [
        'Bus'        => 2,
        'UV-express' => 3,
    ];

    private function resolveNextGroup(string $vehicleType): array
    {
        $maxGroups = self::GROUP_COUNTS[$vehicleType] ?? 2;

        $lastSlot = DailyScheduleSlot::whereHas('vehicleGroup.vehicle',
                fn($q) => $q->where('vehicle_type', $vehicleType)
            )
            ->with('vehicleGroup')
            ->orderBy('schedule_date', 'desc')
            ->first();

        if (!$lastSlot?->vehicleGroup) {
            return ['group' => 1, 'direction' => 'forward'];
        }

        $lastGroup = $lastSlot->vehicleGroup->group_number;

        $nextGroup = ($lastGroup % $maxGroups) + 1;

        $lastTurnForNextGroup = DailyScheduleSlot::whereHas('vehicleGroup', fn($q) =>
                $q->where('group_number', $nextGroup)
                  ->whereHas('vehicle', fn($q2) => $q2->where('vehicle_type', $vehicleType))
            )
            ->orderBy('schedule_date', 'desc')
            ->first();

        $direction = 'forward';

        if ($lastTurnForNextGroup && isset($lastTurnForNextGroup->metadata['direction'])) {
            $direction = $lastTurnForNextGroup->metadata['direction'] === 'forward'
                ? 'reverse'
                : 'forward';
        }

        return ['group' => $nextGroup, 'direction' => $direction];
    }

    public function generateSchedule($targetDate = null)
    {
        $targetDate = $targetDate
            ? Carbon::parse($targetDate)->toDateString()
            : today()->toDateString();

        $exists = DailyScheduleSlot::where('schedule_date', $targetDate)->exists();
        if ($exists) {
            return [
                'success' => false,
                'message' => "Schedule for {$targetDate} has already been generated.",
            ];
        }

        $scheduleData = [];

        foreach (array_keys(self::GROUP_COUNTS) as $vehicleType) {

            ['group' => $activeGroup, 'direction' => $direction] = $this->resolveNextGroup($vehicleType);

            $sortOrder = $direction === 'forward' ? 'asc' : 'desc';

            $assignments = VehicleGroup::where('group_number', $activeGroup)
                ->whereHas('vehicle', fn($q) => $q->where('vehicle_type', $vehicleType))
                ->orderBy('order_number', $sortOrder)
                ->with(['vehicle.user', 'vehicle.route_list'])
                ->get();

            $scheduleData[$vehicleType] = compact('activeGroup', 'direction', 'assignments');
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

                    $slot = DailyScheduleSlot::create([
                        'schedule_date'    => $targetDate,
                        'vehicle_group_id' => $assignment->id,
                        'slot_position'    => $position,
                        'status'           => 'waiting',
                        'metadata'         => [
                            'direction'           => $data['direction'],
                            'assigned_vehicle_id' => $vehicle->id,
                            'vehicle_type'        => $vehicleType, 
                        ],
                    ]);

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
            ->map(fn($data, $type) => "{$type}: Group {$data['activeGroup']} ({$data['direction']})")
            ->implode(' | ');

        return [
            'success' => true,
            'message' => "Schedule for {$targetDate} generated — {$summaries}.",
        ];
    }


}