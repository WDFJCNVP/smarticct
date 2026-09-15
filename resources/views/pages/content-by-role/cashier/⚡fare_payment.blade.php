<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use App\Models\Card;
use App\Models\Queue;
use App\Models\RouteList;
use App\Http\Controllers\Api\CardController;
use App\Services\AuditLogsService;

new class extends Component
{
    public ?int $selectedQueueId = null;

    public bool $show_card_payment_modal = false;

    public string $card_uid   = '';
    public string $card_state = 'ready'; 

    public function mount(): void
    {
        $this->selectedQueueId = null;
    }

    #[Computed]
    public function loadingQueues()
    {
        return Queue::with('user')
            ->where('status', 'loading')
            ->orderBy('destination')
            ->orderBy('vehicle_type')
            ->get();
    }

    #[Computed]
    public function selectedQueue(): ?Queue
    {
        if (!$this->selectedQueueId) return null;
        return Queue::with('user')->find($this->selectedQueueId);
    }

    // Commuter fare for the selected queue's destination + vehicle type,
    // sourced from the route list (separate from the operator's queueing fee).
    #[Computed]
    public function fare(): float
    {
        $queue = $this->selectedQueue;
        if (!$queue) return 0;

        $routeList = RouteList::where('terminal', $queue->destination)
            ->whereHas('operatorTicketRate', fn ($q) => $q->where('vehicle_type', $queue->vehicle_type))
            ->first();

        return (float) ($routeList->fare ?? 0);
    }

    public function selectQueue(int $queueId): void
    {
        $this->selectedQueueId = $queueId;
        $this->resetPaymentFields();
    }

    public function clearQueue(): void
    {
        $this->selectedQueueId = null;
        $this->resetPaymentFields();
    }

    public function resetPaymentFields(): void
    {
        $this->card_uid   = '';
        $this->card_state = 'ready';
    }

    // ─── Card ────────────────────────────────────────────────────────────
    #[Computed]
    public function cardRecord(): ?Card
    {
        if (empty($this->card_uid)) return null;
        return Card::with('user')->where('uid', $this->card_uid)->first();
    }

    public function updatedCardUid(): void
    {
        $this->card_uid = strtoupper(trim($this->card_uid));

        if (empty($this->card_uid)) {
            $this->card_state = 'ready';
            return;
        }

        // A real tap always produces the full 8-character UID in one burst —
        // don't attempt a lookup (or show "not recognised") on a partial scan.
        if (!preg_match('/^[0-9A-F]{8}$/', $this->card_uid)) {
            $this->card_state = 'ready';
            return;
        }

        $card = $this->cardRecord;

        if (!$card || $card->status !== 'active') {
            $this->card_state = 'warn';
            return;
        }

        $this->card_state = 'success';
    }

    public function clearCard(): void
    {
        $this->card_uid   = '';
        $this->card_state = 'ready';
    }

    public function processCardPayment(): void
    {
        $this->show_card_payment_modal = false;

        $queue = $this->selectedQueue;

        if (!$queue) {
            Flux::toast(variant: 'warning', duration: 4000, heading: 'No vehicle selected.', text: 'Select a boarding vehicle first.');
            return;
        }

        $card = $this->cardRecord;

        if (!$card || $card->status !== 'active') {
            Flux::toast(variant: 'danger', duration: 4000, heading: 'Card not recognised.', text: 'Tap a valid, active card.');
            return;
        }

        $request = new Request();
        $request->merge([
            'uid'              => $this->card_uid,
            'transaction_type' => 'fare_payment',
            'amount'           => $this->fare,
            'destination'      => $queue->destination,
            'vehicle_type'     => $queue->vehicle_type,
        ]);

        try {
            $response     = (new CardController())->tap($request);
            $responseData = $response->getData(true);
        } catch (\Exception $e) {
            Log::error('Cashier fare payment (card) failed', ['error' => $e->getMessage(), 'queue_id' => $queue->id]);
            Flux::toast(variant: 'warning', duration: 4000, heading: 'Payment failed.', text: 'Something went wrong while processing this fare. Please try again.');
            return;
        }

        if ($responseData['success'] === true) {
            app(AuditLogsService::class)->create([
                'user_id'  => auth()->id(),
                'action'   => 'Fare Payment',
                'subject'  => 'Commuter fare paid via card at cashier terminal',
                'channel'  => 'Web',
                'metadata' => [
                    'ip_address' => request()->ip(),
                    'message'    => "Fare of ₱{$this->fare} collected for {$queue->destination} trip, plate {$queue->plate_number}.",
                ],
            ]);

            Flux::toast(variant: 'success', duration: 4000, heading: 'Fare paid.', text: $responseData['message']);

            $this->clearCard();
        } else {
            Flux::toast(variant: 'warning', duration: 4000, heading: 'Payment failed.', text: $responseData['message'] ?? 'An error occurred while processing this fare.');
        }
    }

    #[On('echo:vehicle-queue,.QueuedVehicleEvent')]
    public function refreshQueues(): void
    {
        if ($this->selectedQueueId && !$this->selectedQueue) {
            $this->clearQueue();
        }
    }

    public function render(): mixed
    {
        return $this->view()->layout('layouts.cashier-layout');
    }
};
?>

<div>
    <x-page-header
        description="Fare Payment using Card"
        class="mb-6"
    >
    </x-page-header>

    <div class="sm:hidden mb-4 pb-4 border-b border-light-bd-default dark:border-dark-bd-default">
        <x-heading
            size="xl"
            class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
            style="font-size: var(--text-page-title)"
        >
            Fare Payment
        </x-heading>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

        <div class="lg:col-span-1">
            <x-card class="!p-0 overflow-hidden">
                <div class="px-4 sm:px-5 py-3 border-b border-light-bd-default dark:border-dark-bd-default bg-light-secondary/50 dark:bg-dark-secondary/50">
                    <h3 class="font-primary text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                        Now Boarding
                    </h3>
                    <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted mt-0.5">
                        The first vehicle in line for each destination and vehicle type.
                    </p>
                </div>

                <div class="max-h-[65vh] overflow-y-auto divide-y divide-light-bd-default dark:divide-dark-bd-default">
                    @forelse ($this->loadingQueues as $queue)
                        <button
                            type="button"
                            wire:click="selectQueue({{ $queue->id }})"
                            @class([
                                'w-full text-left px-4 sm:px-5 py-3 transition',
                                'bg-primary/10 dark:bg-primary/15' => $selectedQueueId === $queue->id,
                                'hover:bg-light-subtle dark:hover:bg-dark-subtle' => $selectedQueueId !== $queue->id,
                            ])
                        >
                            <div class="flex items-center justify-between gap-2">
                                <p class="font-secondary font-semibold text-sm text-light-txt-primary dark:text-dark-txt-primary">
                                    {{ $queue->destination }}
                                </p>
                                <flux:badge size="sm" color="blue" class="font-secondary text-badge text-xs shrink-0">
                                    {{ $queue->vehicle_type }}
                                </flux:badge>
                            </div>
                            <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted mt-0.5">
                                {{ $queue->plate_number }} · {{ $queue->user?->name ?? 'Unassigned operator' }}
                            </p>
                            <div class="flex items-center justify-between mt-2">
                                <span class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">
                                    Seats
                                </span>
                                <span class="font-secondary text-xs font-semibold tabular-nums {{ $queue->seat_count >= $queue->seat_capacity ? 'text-danger dark:text-dark-danger' : 'text-light-txt-primary dark:text-dark-txt-primary' }}">
                                    {{ $queue->seat_count }} / {{ $queue->seat_capacity }}
                                </span>
                            </div>
                            <div class="w-full h-1.5 rounded-full bg-light-subtle dark:bg-dark-subtle mt-1 overflow-hidden">
                                <div
                                    class="h-full rounded-full {{ $queue->seat_count >= $queue->seat_capacity ? 'bg-danger dark:bg-dark-danger' : 'bg-primary' }}"
                                    style="width: {{ $queue->seat_capacity > 0 ? min(100, round(($queue->seat_count / $queue->seat_capacity) * 100)) : 0 }}%"
                                ></div>
                            </div>
                        </button>
                    @empty
                        <div class="flex flex-col items-center justify-center py-10 gap-2 px-4">
                            <flux:icon name="truck" class="w-6 h-6 text-light-txt-muted dark:text-dark-txt-muted" />
                            <x-text variant="subtle" class="!font-secondary text-center block" style="font-size: var(--text-table-row)">
                                No vehicles are currently boarding.
                            </x-text>
                        </div>
                    @endforelse
                </div>
            </x-card>
        </div>

        {{-- ─── Right: payment panel ───────────────────────────────────────── --}}
        <div class="lg:col-span-2">
            @if (!$this->selectedQueue)
                <x-card class="!rounded-xl !border !border-dashed !border-light-bd-strong dark:!border-dark-bd-strong !bg-light-secondary dark:!bg-dark-secondary !text-center !p-10">
                    <flux:icon name="ticket" class="w-8 h-8 mx-auto text-light-txt-muted dark:text-dark-txt-muted mb-2" />
                    <x-text variant="subtle" class="!font-secondary block" style="font-size: var(--text-table-row)">
                        No vehicle selected yet.
                    </x-text>
                    <x-text variant="subtle" class="!font-secondary block mt-1" style="font-size: var(--text-timestamp)">
                        Pick a boarding vehicle on the left to start collecting a fare.
                    </x-text>
                </x-card>
            @else
                @php $queue = $this->selectedQueue; @endphp

                {{-- Trip summary --}}
                <x-card class="!p-0 overflow-hidden mb-6">
                    <div class="px-4 sm:px-5 py-3 border-b border-light-bd-default dark:border-dark-bd-default bg-light-secondary/50 dark:bg-dark-secondary/50 flex items-center justify-between">
                        <h3 class="font-primary text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                            Trip
                        </h3>
                        <button wire:click="clearQueue" class="text-light-txt-muted hover:text-light-txt-primary dark:text-dark-txt-muted dark:hover:text-dark-txt-primary transition">
                            <flux:icon name="x-mark" class="w-4 h-4" />
                        </button>
                    </div>
                    <div class="p-4 sm:p-5 grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div class="min-w-0">
                            <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">Destination</p>
                            <p class="font-secondary font-semibold text-light-txt-primary dark:text-dark-txt-primary truncate">{{ $queue->destination }}</p>
                        </div>
                        <div class="min-w-0">
                            <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">Vehicle</p>
                            <p class="font-secondary font-semibold text-light-txt-primary dark:text-dark-txt-primary truncate">{{ $queue->vehicle_type }} · {{ $queue->plate_number }}</p>
                        </div>
                        <div class="min-w-0">
                            <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">Seats</p>
                            <p class="font-secondary font-semibold text-light-txt-primary dark:text-dark-txt-primary">{{ $queue->seat_count }} / {{ $queue->seat_capacity }}</p>
                        </div>
                        <div class="min-w-0">
                            <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">Fare</p>
                            <p class="font-secondary font-semibold text-primary dark:text-dark-txt-primary">₱{{ number_format($this->fare, 2) }}</p>
                        </div>
                    </div>
                </x-card>

                @if ($this->fare <= 0)
                    <flux:callout variant="warning" icon="exclamation-triangle" class="mb-4">
                        <flux:callout.heading>No fare configured</flux:callout.heading>
                        <flux:callout.text>
                            There's no route/fare entry for {{ $queue->destination }} ({{ $queue->vehicle_type }}). Set one up under Routes before collecting payment.
                        </flux:callout.text>
                    </flux:callout>
                @endif

                <x-card class="!p-0 overflow-hidden">
                    <div @class([
                        'flex items-center gap-3 p-4 rounded-t-xl border-b',
                        'bg-primary/5 dark:bg-primary/10 border-primary/10 dark:border-primary/20'           => $card_state === 'ready',
                        'bg-success/10 dark:bg-dark-success/10 border-success/20 dark:border-dark-success/20' => $card_state === 'success',
                        'bg-danger/10 dark:bg-dark-danger/10 border-danger/20 dark:border-dark-danger/20'     => $card_state === 'warn',
                    ])>
                        <flux:icon
                            :name="$card_state === 'success' ? 'check-circle' : ($card_state === 'warn' ? 'exclamation-triangle' : 'credit-card')"
                            @class([
                                'w-5 h-5 shrink-0',
                                'text-primary dark:text-dark-txt-primary' => $card_state === 'ready',
                                'text-success dark:text-dark-success'     => $card_state === 'success',
                                'text-danger dark:text-dark-danger'       => $card_state === 'warn',
                            ])
                        />
                        <div class="flex-1 min-w-0">
                            <p @class([
                                'font-secondary text-sm font-medium',
                                'text-light-txt-primary dark:text-dark-txt-primary' => $card_state === 'ready',
                                'text-success dark:text-dark-success'                => $card_state === 'success',
                                'text-danger dark:text-dark-danger'                  => $card_state === 'warn',
                            ])>
                                @if ($card_state === 'ready') Waiting for commuter card tap
                                @elseif ($card_state === 'success') Card recognised
                                @else Card not recognised or inactive
                                @endif
                            </p>
                            <p @class([
                                'font-secondary text-xs',
                                'text-light-txt-muted dark:text-dark-txt-muted' => $card_state === 'ready',
                                'text-success/80 dark:text-dark-success/80'     => $card_state === 'success',
                                'text-danger/80 dark:text-dark-danger/80'       => $card_state === 'warn',
                            ])>
                                @if ($card_state === 'ready') Hold the commuter's card near the reader
                                @elseif ($card_state === 'success') UID {{ $card_uid }} · {{ $this->cardRecord?->user?->name }} · Balance ₱{{ number_format($this->cardRecord?->balance ?? 0, 2) }}
                                @else Unregistered card or card is suspended/terminated
                                @endif
                            </p>
                        </div>
                        @if ($card_state === 'success')
                            <button wire:click="clearCard" class="text-light-txt-muted hover:text-light-txt-primary dark:text-dark-txt-muted dark:hover:text-dark-txt-primary transition shrink-0">
                                <flux:icon name="x-mark" class="w-5 h-5" />
                            </button>
                        @endif
                    </div>

                    <div class="p-5 space-y-4">
                        <flux:field>
                            <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary flex items-center gap-1.5">
                                <flux:icon name="credit-card" class="w-3.5 h-3.5" />
                                Card UID
                            </flux:label>
                            <div class="relative mt-1">
                                <input
                                    type="text"
                                    wire:model.live.debounce.200ms="card_uid"
                                    autocomplete="off"
                                    autofocus
                                    @keydown.enter.prevent
                                    class="absolute inset-0 w-full h-full opacity-0 cursor-default"
                                />
                                <div
                                    class="pointer-events-none rounded-lg border-2 border-dashed p-2.5 flex items-center justify-center gap-2 transition-colors
                                        {{ $card_uid ? 'border-success dark:border-dark-success bg-success/5 dark:bg-dark-success/10' : 'border-light-bd-default dark:border-dark-bd-default' }}"
                                >
                                    @if ($card_uid)
                                        <span class="font-mono text-sm tracking-[0.2em] font-semibold text-light-txt-primary dark:text-dark-txt-primary">{{ $card_uid }}</span>
                                    @else
                                        <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Tap commuter's card on reader…</span>
                                    @endif
                                </div>
                            </div>
                        </flux:field>

                        @if ($card_state === 'success' && $this->cardRecord?->user?->role !== 'commuter')
                            <flux:callout variant="warning" icon="exclamation-triangle">
                                <flux:callout.text>
                                    This card belongs to {{ $this->cardRecord?->user?->role === 'operator' ? 'an operator' : 'a non-commuter account' }}. Fare payments should be made with a commuter's card.
                                </flux:callout.text>
                            </flux:callout>
                        @endif

                        <x-button
                            variant="primary"
                            size="sm"
                            class="w-full !font-secondary"
                            :disabled="$card_state !== 'success' || $this->fare <= 0"
                            wire:click="$set('show_card_payment_modal', true)"
                        >
                            Charge ₱{{ number_format($this->fare, 2) }}
                        </x-button>
                    </div>
                </x-card>
            @endif
        </div>
    </div>

    {{-- Card payment confirmation --}}
    <flux:modal wire:model="show_card_payment_modal" :closable="false" class="w-[calc(100%-2rem)] max-w-xs sm:max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Confirm charge?</flux:heading>
                <flux:text class="mt-2">
                    Charge ₱{{ number_format($this->fare, 2) }} to this card for the {{ $this->selectedQueue?->destination }} trip?
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button wire:click="processCardPayment" wire:loading.attr="disabled" wire:target="processCardPayment" variant="primary">
                    <span wire:loading.remove wire:target="processCardPayment">Charge ₱{{ number_format($this->fare, 2) }}</span>
                    <span wire:loading wire:target="processCardPayment">Processing…</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>