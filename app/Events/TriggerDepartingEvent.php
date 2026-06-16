<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

use App\Models\Queue;

class TriggerDepartingEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $queuedVehicle;

    /**
     * Create a new event instance.
     */
    public function __construct(int $queuedId)
    {

        $this->queuedVehicle = Queue::where('id', $queuedId)->first();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('trigger-depart-event'),
        ];
    }

        public function broadcastAs(): string 
    {
        return 'TriggerDepartingEvent';
    }

    public function broadcastWith(): array
    {
        return [
            'vehicle' => [
                'id'    => $this->queuedVehicle->id,
            ],
        ];
    }
}
