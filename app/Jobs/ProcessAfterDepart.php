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

    private function getCard($card_id) {
        return Card::with('user')->where('id', $card_id)->first();
    }

    private function getAdmins() {
        return User::whereIn('role', ['admin', 'cashier'])->get();
    }

    private function departedNotification($queue, $card) {
        foreach($this->getAdmins() as $user) {
            $notification = Notification::create([
                'type' => 'Departed',
                'title' => 'Vehicle has been departed',
                'message' => "[". ucfirst($queue->vehicle_type) ."] vehicle with the plate number of {$queue->plate_number} has been marked as departed",
                'metadata' => json_encode([
                    'card_id' => $queue->card_id,
                ]),
            ]);

            UserNotification::create([
                'notification_id' => $notification->id,
                'user_id' => $user->id,
            ]);
        }

        $notification = Notification::create([
            'type' => 'departed',
            'title' => 'Vehicle marked departed',
            'message' => "Your {$queue->vehicle_type} vehicle in the queue with the plate number of {$queue->plate_number} has been marked as departed.",
            'metadata' => json_encode([
                'card_id' => $queue->card_id,
            ]),
        ]);

        UserNotification::create([
            'notification_id' => $notification->id,
            'user_id' => $card->user->id,
        ]);

        broadcast(new NotificationEvent());
    }

    private function promoteToLoadingNotification($next_queue, $card) {
        foreach($this->getAdmins() as $user) {
            $notification = Notification::create([
                'type' => 'Loading',
                'title' => 'Vehicle has been promoted to loading',
                'message' => "[". ucfirst($next_queue->vehicle_type) ."] vehicle type with the plate number of {$next_queue->plate_number} has been promoted to loading",
                'metadata' => json_encode([
                    'card_id' => $next_queue->card_id,
                ]),
            ]);

            UserNotification::create([
                'notification_id' => $notification->id,
                'user_id' => $user->id,
            ]);
        }

        $notification = Notification::create([
            'type' => 'loading',
            'title' => 'Vehicle promoted to loading',
            'message' => "Your {$next_queue->vehicle_type} vehicle in the queue with the plate number of {$next_queue->plate_number} has been promoted to loading.",
            'metadata' => json_encode([
                'card_id' => $next_queue->card_id,
            ]),
        ]);

        UserNotification::create([
            'notification_id' => $notification->id,
            'user_id' => $card->user->id,
        ]);

        broadcast(new NotificationEvent());
    }

    public function handle(): void
    {
        DB::transaction(function  ()  {

            $queue = Queue::where('id', $this->model_id)
                ->lockForUpdate()
                ->first();

            if (!$queue || $queue->status !== 'loading') {
                Log::warning("Queue [{$this->model_id}] not found or not in loading state.");
                return;
            }

            $card = $this->getCard($queue->card_id);

            $queue->update([
                'time_departed' => Carbon::now(),
                'status'        => 'departed',
            ]);

            broadcast(new QueuedVehicleEvent());

            Log::info("Queue [{$queue->id}] marked as departed.");

            $this->departedNotification($queue, $card);

            $next_queue = Queue::where('status', 'staging')
                ->orderBy('time_queued', 'asc')
                ->lockForUpdate()
                ->first();

            if (!$next_queue) {
                Log::info("No staging vehicles found. Queue chain ended.");
                return;
            }

            $next_queue->update([
                'status'     => 'loading',
                'departs_at' => Carbon::now()->addMinute(),
            ]);

            $next_card = $this->getCard($next_queue->card_id);

            $this->promoteToLoadingNotification($next_queue, $next_card);
            
            broadcast(new QueuedVehicleEvent());

            Log::info("Queue [{$next_queue->id}] promoted to loading.");

            ProcessAfterDepart::dispatch($next_queue->id)
                ->delay($next_queue->departs_at);
                
        });
    }
}