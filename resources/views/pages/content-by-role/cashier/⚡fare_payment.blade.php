<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use App\Models\Card;
use App\Models\Queue;
use App\Models\User;
use App\Models\RouteList;
use App\Http\Controllers\Api\CardController;
use App\Services\AuditLogsService;

new class extends Component
{
    // Step 1 — which boarding vehicle the commuter is paying for
    public ?int $selectedQueueId = null;

    // ===================== PAYMENT CONFIRMATIONS =====================
    public bool $show_card_payment_modal = false;
    public bool $show_cash_payment_modal = false;

    // Payment mode
    public string $paymentMode = 'card'; // card | cash

    // --- Card mode state ---
    public string $card_uid   = '';
    public string $card_state = 'ready'; // ready | success | warn

    // --- Cash mode state ---
    public string $riderSearch   = '';
    public ?int   $riderUserId   = null;
    public ?float $amount_received = null;
    public bool   $showInsufficientAlert = false;

    public function mount(): void
    {
        $this->selectedQueueId = null;
    }

    // ─── Boarding vehicles (front of line for each destination/type) ───────
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
        $this->card_uid        = '';
        $this->card_state      = 'ready';
        $this->riderSearch     = '';
        $this->riderUserId     = null;
        $this->amount_received = null;
    }

    public function setPaymentMode(string $mode): void
    {
        $this->paymentMode = $mode;
        $this->resetPaymentFields();
    }

    // ─── Card mode ───────────────────────────────────────────────────────
    #[Computed]
    public function cardRecord(): ?Card
    {
        if (empty($this->card_uid)) return null;
        return Card::with('user')->where('uid', $this->card_uid)->first();
    }

    public function updatedCardUid(): void
    {
        if (empty($this->card_uid)) {
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

    // ─── Cash mode ───────────────────────────────────────────────────────
    #[Computed]
    public function riderCandidates()
    {
        if (strlen($this->riderSearch) < 2 || $this->riderUserId) {
            return collect();
        }

        return User::where('role', 'commuter')
            ->where(function ($q) {
                $q->where('name', 'like', '%' . $this->riderSearch . '%')
                  ->orWhere('user_code', 'like', '%' . $this->riderSearch . '%');
            })
            ->limit(8)
            ->get();
    }

    public function selectRider(int $userId): void
    {
        $this->riderUserId = $userId;
        $this->riderSearch = User::find($userId)?->name ?? '';
    }

    public function clearRider(): void
    {
        $this->riderUserId = null;
        $this->riderSearch = '';
    }

    #[Computed]
    public function change(): float
    {
        if ($this->amount_received && $this->fare > 0) {
            return max(0, (float) $this->amount_received - $this->fare);
        }
        return 0;
    }

    public function processCashPayment(): void
    {
        $this->show_cash_payment_modal = false;

        $queue = $this->selectedQueue;

        if (!$queue) {
            Flux::toast(variant: 'warning', duration: 4000, heading: 'No vehicle selected.', text: 'Select a boarding vehicle first.');
            return;
        }

        if (empty($this->amount_received) || (float) $this->amount_received < $this->fare) {
            $this->showInsufficientAlert = true;
            return;
        }

        $request = new Request();
        $request->merge([
            'destination'      => $queue->destination,
            'vehicle_type'     => $queue->vehicle_type,
            'amount'           => $this->fare,
            'amount_received'  => $this->amount_received,
            'user_id'          => $this->riderUserId,
        ]);

        try {
            $response     = (new CardController())->cashFarePayment($request);
            $responseData = $response->getData(true);
        } catch (\Exception $e) {
            Log::error('Cashier fare payment (cash) failed', ['error' => $e->getMessage(), 'queue_id' => $queue->id]);
            Flux::toast(variant: 'warning', duration: 4000, heading: 'Payment failed.', text: 'Something went wrong while processing this fare. Please try again.');
            return;
        }

        if ($responseData['success'] === true) {
            app(AuditLogsService::class)->create([
                'user_id'  => auth()->id(),
                'action'   => 'Fare Payment',
                'subject'  => 'Commuter fare paid in cash at cashier terminal',
                'channel'  => 'Web',
                'metadata' => [
                    'ip_address' => request()->ip(),
                    'message'    => "Cash fare of ₱{$this->fare} collected for {$queue->destination} trip, plate {$queue->plate_number}.",
                ],
            ]);

            $change = $responseData['change'] ?? 0;

            Flux::toast(
                variant: 'success',
                duration: 4000,
                heading: 'Fare paid.',
                text: 'Cash fare recorded. Change: ₱' . number_format($change, 2),
            );

            $this->resetPaymentFields();
        } else {
            Flux::toast(variant: 'warning', duration: 4000, heading: 'Payment failed.', text: $responseData['message'] ?? 'An error occurred while processing this fare.');
        }
    }

    #[On('echo:vehicle-queue,.QueuedVehicleEvent')]
    public function refreshQueues(): void
    {
        // #[Computed] properties are recalculated fresh on this render, so
        // loadingQueues() and selectedQueue() already reflect the change
        // (live seat counts, new vehicles loading, vehicles departing).
        // We just need to drop the selection if that vehicle is no longer
        // boarding (departed, or someone else filled/cleared its queue).
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
    {{-- ====== PAGE HEADER (mini-navbar: heading left, notifications right) ====== --}}
    <x-page-header
        heading="Fare Payment"
        description="Collect a commuter's fare for a currently boarding vehicle — by card tap or cash."
        class="mb-6"
    >
        {{-- No extra controls – keep it minimal --}}
    </x-page-header>

    {{-- Mobile-visible heading, since the page header above is desktop-only --}}
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

        {{-- ─── Left: boarding vehicles list ──────────────────────────────── --}}
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

                {{-- Payment mode toggle --}}
                <div class="flex items-center gap-2 mb-4">
                    <flux:button
                        wire:click="setPaymentMode('card')"
                        variant="{{ $paymentMode === 'card' ? 'primary' : 'ghost' }}"
                        size="sm"
                        icon="credit-card"
                        class="font-secondary"
                    >
                        Pay by Card
                    </flux:button>
                    <flux:button
                        wire:click="setPaymentMode('cash')"
                        variant="{{ $paymentMode === 'cash' ? 'primary' : 'ghost' }}"
                        size="sm"
                        icon="banknotes"
                        class="font-secondary"
                    >
                        Pay by Cash
                    </flux:button>
                </div>

                @if ($this->fare <= 0)
                    <flux:callout variant="warning" icon="exclamation-triangle" class="mb-4">
                        <flux:callout.heading>No fare configured</flux:callout.heading>
                        <flux:callout.text>
                            There's no route/fare entry for {{ $queue->destination }} ({{ $queue->vehicle_type }}). Set one up under Routes before collecting payment.
                        </flux:callout.text>
                    </flux:callout>
                @endif

                {{-- ─── Card mode ─────────────────────────────────────────────── --}}
                @if ($paymentMode === 'card')
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
                                <x-input
                                    wire:model.live.debounce.300ms="card_uid"
                                    placeholder="Tap commuter's card on reader…"
                                    autocomplete="off"
                                    class="font-mono tracking-widest mt-1"
                                    autofocus
                                />
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

                {{-- ─── Cash mode ─────────────────────────────────────────────── --}}
                @if ($paymentMode === 'cash')
                    <x-card class="!p-0 overflow-hidden">
                        <div class="p-5 space-y-4">

                            {{-- Optional: link to a registered commuter --}}
                            <flux:field>
                                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary flex items-center gap-1.5">
                                    <flux:icon name="magnifying-glass" class="w-3.5 h-3.5" />
                                    Commuter (optional)
                                </flux:label>
                                <div class="relative mt-1">
                                    <x-input
                                        wire:model.live.debounce.300ms="riderSearch"
                                        placeholder="Search name or user code — leave blank for a walk-in rider"
                                        autocomplete="off"
                                        :disabled="(bool) $riderUserId"
                                    />
                                    @if ($riderUserId)
                                        <button
                                            type="button"
                                            wire:click="clearRider"
                                            class="absolute right-2 top-1/2 -translate-y-1/2 text-light-txt-muted hover:text-danger dark:hover:text-dark-danger transition"
                                        >
                                            <flux:icon name="x-mark" class="w-4 h-4" />
                                        </button>
                                    @elseif (strlen($riderSearch) >= 2)
                                        <div class="absolute z-20 w-full mt-1 bg-light-primary dark:bg-dark-surface border border-light-bd-default dark:border-dark-bd-default rounded-lg shadow-xl max-h-52 overflow-y-auto">
                                            @forelse ($this->riderCandidates as $u)
                                                <div
                                                    wire:click="selectRider({{ $u->id }})"
                                                    class="flex items-center gap-3 px-3 py-2.5 hover:bg-light-subtle dark:hover:bg-dark-subtle cursor-pointer"
                                                >
                                                    <flux:avatar size="xs" src="{{ $u->avatar_url }}" name="{{ $u->name }}" />
                                                    <div class="min-w-0">
                                                        <p class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary truncate">{{ $u->name }}</p>
                                                        <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">{{ $u->user_code }}</p>
                                                    </div>
                                                </div>
                                            @empty
                                                <div class="px-3 py-2 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">No commuters found.</div>
                                            @endforelse
                                        </div>
                                    @endif
                                </div>
                                <flux:description class="font-secondary text-helper text-light-txt-muted dark:text-dark-txt-muted">
                                    Linking an account records this trip on their travel history. Not required for cash fares.
                                </flux:description>
                            </flux:field>

                            {{-- Amount received --}}
                            <div>
                                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary mb-1 block">
                                    Amount Received
                                </flux:label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-light-txt-muted dark:text-dark-txt-muted">₱</span>
                                    <x-input
                                        wire:model.live.debounce.300ms="amount_received"
                                        type="number"
                                        step="0.01"
                                        min="{{ $this->fare }}"
                                        placeholder="{{ number_format($this->fare, 2) }}"
                                        class="pl-7 w-full"
                                    />
                                </div>
                            </div>

                            @if ($amount_received)
                                <div class="flex justify-between py-1.5 text-sm font-secondary">
                                    <span class="text-light-txt-muted dark:text-dark-txt-muted">Change</span>
                                    <span class="font-semibold text-success dark:text-dark-success">₱{{ number_format($this->change, 2) }}</span>
                                </div>
                            @endif

                            <x-button
                                variant="primary"
                                size="sm"
                                class="w-full !font-secondary"
                                :disabled="$this->fare <= 0"
                                wire:click="$set('show_cash_payment_modal', true)"
                            >
                                Confirm Cash Fare
                            </x-button>
                        </div>
                    </x-card>
                @endif
            @endif
        </div>
    </div>

    {{-- Insufficient amount modal --}}
    <flux:modal wire:model.live="showInsufficientAlert" class="max-w-sm">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg" class="!text-danger dark:!text-dark-danger">Insufficient Amount</flux:heading>
                <flux:subheading>Amount received is less than the fare.</flux:subheading>
            </div>
            <x-text variant="subtle" class="!font-secondary block" style="font-size: var(--text-table-row)">
                The fare is <strong class="text-light-txt-primary dark:text-dark-txt-primary">₱{{ number_format($this->fare, 2) }}</strong>.
                Please enter an amount equal to or greater than this.
            </x-text>
            <div class="flex justify-end">
                <flux:button wire:click="$set('showInsufficientAlert', false)" variant="primary">Got it</flux:button>
            </div>
        </div>
    </flux:modal>

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

    {{-- Cash payment confirmation --}}
    <flux:modal wire:model="show_cash_payment_modal" :closable="false" class="w-[calc(100%-2rem)] max-w-xs sm:max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Confirm cash fare?</flux:heading>
                <flux:text class="mt-2">
                    Record a ₱{{ number_format($this->fare, 2) }} cash fare for the {{ $this->selectedQueue?->destination }} trip?
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button wire:click="processCashPayment" wire:loading.attr="disabled" wire:target="processCashPayment" variant="primary">
                    <span wire:loading.remove wire:target="processCashPayment">Confirm Cash Fare</span>
                    <span wire:loading wire:target="processCashPayment">Processing…</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
