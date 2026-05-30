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

use App\Models\Card;

class RegistrationTapCardEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $card;

    /**
     * Create a new event instance.
     */
    public function __construct(
            Card $card
        )
    {
        $this->card = $card;    
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('registration-tap-card'),
        ];
    }

    public function broadcastAs(): string {
        return 'RegistrationTapCardEvent';
    }

    public function broadcastWith(): array
    {

        \Log::info('broadcastWith called');

        return [
            'uid' => $this->card->uid,
        ];
    }
}
