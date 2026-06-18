<?php

namespace App\Services;

use App\Models\Queue;
use App\Events\QueuedVehicleEvent;
use Illuminate\Support\Facades\DB;
use Exception;

class QueueOrderService
{
    /**
     * @param int $queueId
     * @return array
     * @throws Exception
     */

    public function swapWithNext(int $queueId): array
    {
        return DB::transaction(function () use ($queueId) {
    
            $target = Queue::where('id', $queueId)
                ->where('status', 'staging')
                ->whereNotNull('daily_schedule_slot_id')
                ->lockForUpdate()
                ->first();

            if (!$target) {
                throw new Exception('Queue entry not found or is not in a valid state to be modified.');
            }

            $next = Queue::where('vehicle_type', $target->vehicle_type)
                ->where('status', $target->status)
                ->whereNotNull('daily_schedule_slot_id')
                ->whereHas('dailyScheduleSlot', fn($q) => $q->where('schedule_date', today()->toDateString()))
                ->where('slot_position', '>', $target->slot_position)
                ->orderBy('slot_position', 'asc')
                ->lockForUpdate()
                ->first();

            if (!$next) {
                return [
                    'success' => false,
                    'message' => 'Cannot swap. No vehicles currently in line behind this one.'
                ];
            }

            // 3. Swap the slot positions explicitly
            $targetPosition = $target->slot_position;
            $nextPosition = $next->slot_position;

            $target->update(['slot_position' => $nextPosition]);
            $next->update(['slot_position' => $targetPosition]);

            broadcast(new QueuedVehicleEvent());

            return [
                'success' => true,
                'promoted' => [
                    'queue_id'      => $next->id,
                    'plate_number'  => $next->plate_number,
                    'slot_position' => $next->slot_position,
                ],
                'demoted' => [
                    'queue_id'      => $target->id,
                    'plate_number'  => $target->plate_number,
                    'slot_position' => $target->slot_position,
                ]
            ];
        });
    }
}