<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever a CashTransaction is created (cash fare paid at the
 * cashier counter, or self-logged by the operator). The cashier dashboard
 * and transactions list already listen for this on the public
 * 'cash-transaction-created' channel (echo:cash-transaction-created,
 * .CashTransactionCreated) — this class was previously missing.
 */
class CashTransactionCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ?int $queueId = null,
    ) {
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('cash-transaction-created'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CashTransactionCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'queue_id' => $this->queueId,
        ];
    }
}
