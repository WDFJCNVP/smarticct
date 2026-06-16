<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

use App\Models\User;
use App\Models\Card;
use App\Models\Queue;
use App\Models\Notification;
use App\Models\UserNotification;
use App\Events\QueuedVehicleEvent;
use App\Events\NotificationEvent;

class ProcessAfterDepart implements ShouldQueue
{
    use Queueable;

    private $model_id;

    public function __construct($model_id)
    {
        $this->model_id = $model_id;
    }

    private function getCard($user_id)
    {
        return Card::with('user')->where('user_id', $user_id)->first();
    }

    private function getAdmins()
    {
        return User::whereIn('role', ['admin', 'cashier'])->get();
    }

    private function departedNotification($queue, $card): void
    {
        $ownerUserId = $card?->user?->id ?? $queue->user_id;

        foreach ($this->getAdmins() as $user) {
            $notification = Notification::create([
                'type'     => 'Departed',
                'title'    => 'Vehicle has been departed',
                'message'  => "[" . ucfirst($queue->vehicle_type) . "] vehicle with plate number {$queue->plate_number} has been marked as departed",
                'metadata' => json_encode(['user_id' => $queue->user_id]),
            ]);
            UserNotification::create(['notification_id' => $notification->id, 'user_id' => $user->id]);
        }

        $notification = Notification::create([
            'type'     => 'departed',
            'title'    => 'Vehicle marked departed',
            'message'  => "Your {$queue->vehicle_type} vehicle with plate number {$queue->plate_number} has been marked as departed.",
            'metadata' => json_encode(['user_id' => $queue->user_id]),
        ]);
        UserNotification::create(['notification_id' => $notification->id, 'user_id' => $ownerUserId]);

        broadcast(new NotificationEvent());
    }

    private function promotedNotification($next_queue, $card): void
    {
        $ownerUserId = $card?->user?->id ?? $next_queue->user_id;

        foreach ($this->getAdmins() as $user) {
            $notification = Notification::create([
                'type'     => 'Loading',
                'title'    => 'Vehicle promoted to loading',
                'message'  => "[" . ucfirst($next_queue->vehicle_type) . "] vehicle with plate number {$next_queue->plate_number} is now next in line",
                'metadata' => json_encode(['user_id' => $next_queue->user_id]),
            ]);
            UserNotification::create(['notification_id' => $notification->id, 'user_id' => $user->id]);
        }

        $notification = Notification::create([
            'type'     => 'loading',
            'title'    => 'Your vehicle is next',
            'message'  => "Your {$next_queue->vehicle_type} vehicle with plate number {$next_queue->plate_number} is now first in line. Please tap your card to start loading.",
            'metadata' => json_encode(['user_id' => $next_queue->user_id]),
        ]);
        UserNotification::create(['notification_id' => $notification->id, 'user_id' => $ownerUserId]);

        broadcast(new NotificationEvent());
    }

    public function handle(): void
    {
        DB::transaction(function () {
            $queue = Queue::where('id', $this->model_id)
                ->lockForUpdate()
                ->first();

            if (!$queue || $queue->status !== 'loading') {
                Log::warning("Queue [{$this->model_id}] not found or not in loading state.");
                return;
            }

            $card = $this->getCard($queue->user_id);

            // Mark current vehicle as departed
            $queue->update([
                'time_departed' => Carbon::now(),
                'status'        => 'departed',
            ]);

            $queue->dailyScheduleSlot?->update(['status' => 'departed']);

            broadcast(new QueuedVehicleEvent());
            Log::info("Queue [{$queue->id}] marked as departed.");
            $this->departedNotification($queue, $card);

            // Find next vehicle in line
            $isScheduled = in_array($queue->vehicle_type, ['Bus', 'UV-express']);

            if ($isScheduled) {
                // Scheduled: next staging vehicle by slot_position
                // Status stays 'staging' — they must tap to start their timer
                $next_queue = Queue::where('vehicle_type', $queue->vehicle_type)
                    ->where('destination', $queue->destination)
                    ->where('status', 'staging')
                    ->whereNotNull('daily_schedule_slot_id')
                    ->whereHas('dailyScheduleSlot', fn($q) => $q->where('schedule_date', today()))
                    ->orderBy('slot_position', 'asc')
                    ->lockForUpdate()
                    ->first();

                if (!$next_queue) {
                    Log::info("No more staging vehicles for today. Queue chain ended.");
                    return;
                }

                // Do NOT change status — vehicle stays 'staging' until they tap
                // Just notify them that they're now first in line
                Log::info("Queue [{$next_queue->id}] is now first in line. Waiting for tap.");
                $next_card = $this->getCard($next_queue->user_id);
                $this->promotedNotification($next_queue, $next_card);

            } else {
                // Non-scheduled: auto-promote next staging to loading
                $next_queue = Queue::where('vehicle_type', $queue->vehicle_type)
                    ->where('destination', $queue->destination)
                    ->where('status', 'staging')
                    ->orderBy('time_queued', 'asc')
                    ->lockForUpdate()
                    ->first();

                if (!$next_queue) {
                    Log::info("No staging vehicles found. Queue chain ended.");
                    return;
                }

                $departsAt = match ($next_queue->vehicle_type) {
                    'Bus'       => Carbon::now()->addMinutes(1),
                    'Multi-cab' => Carbon::now()->addMinutes(2),
                    default     => null,
                };

                $next_queue->update([
                    'status'      => 'loading',
                    'departs_at'  => $departsAt,
                    'time_queued' => Carbon::now(),
                ]);

                if ($departsAt !== null) {
                    self::dispatch($next_queue->id)->delay($departsAt);
                }

                Log::info("Queue [{$next_queue->id}] promoted to loading.");
                $next_card = $this->getCard($next_queue->user_id);
                $this->promotedNotification($next_queue, $next_card);
            }

            broadcast(new QueuedVehicleEvent());
        });
    }
}