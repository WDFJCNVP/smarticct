<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever a CardTransaction is created (fare payment, queueing fee,
 * fare earning, top-up, withdrawal, etc.) so that any open dashboard can
 * refresh its balance / recent-activity widgets without a manual reload.
 *
 * The operator, commuter, and cashier dashboards already listen for this
 * event on the public 'card-transaction-created' channel
 * (echo:card-transaction-created,.CardTransactionCreated) — this class was
 * previously missing, so those listeners were wired to nothing.
 */
class CardTransactionCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ?int $cardId = null,
        public readonly ?string $transactionType = null,
    ) {
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('card-transaction-created'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CardTransactionCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'card_id'          => $this->cardId,
            'transaction_type' => $this->transactionType,
        ];
    }
}
