<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;

use Flux\Flux;

use App\Enums\RentalRequestStatus;
use App\Enums\RentalStatus;
use App\Models\RentalRequest;
use App\Services\RentalRequestService;
use App\Support\Peso;

new #[Layout('layouts.operator-layout')] class extends Component
{
    public RentalRequest $rentalRequest;

    // quote modal
    public string $quotedTotal = '';
    public string $quoteNote = '';

    // decline modal
    public string $declineReason = '';

    public function mount(RentalRequest $rentalRequest): void
    {
        $rentalRequest->load(['vehicle', 'passenger.userStatus']);

        // Only the operator who owns the vehicle may open this page.
        abort_if($rentalRequest->vehicle->user_id !== auth()->id(), 404);

        $this->rentalRequest = $rentalRequest;
    }

    /** Pending, and the response window has not passed. */
    #[Computed]
    public function canRespond(): bool
    {
        $r = $this->rentalRequest;

        return $r->status === RentalRequestStatus::Pending
            && ! ($r->respond_by && $r->respond_by->isPast())
            && ! $r->trip_start_at->isPast()
            && $r->vehicle->rental_status !== RentalStatus::Suspended;
    }

    #[Computed]
    public function isLate(): bool
    {
        $r = $this->rentalRequest;

        return $r->status === RentalRequestStatus::Pending
            && (($r->respond_by && $r->respond_by->isPast()) || $r->trip_start_at->isPast());
    }

    /**
     * Dates already confirmed, or held while another passenger pays.
     *
     * @return list<string>
     */
    #[Computed]
    public function conflicts(): array
    {
        return RentalRequest::where('vehicle_id', $this->rentalRequest->vehicle_id)
            ->whereKeyNot($this->rentalRequest->id)
            ->blockingDates()
            ->overlapping($this->rentalRequest->trip_start_at, $this->rentalRequest->trip_end_at)
            ->orderBy('trip_start_at')
            ->get(['trip_start_at', 'trip_end_at'])
            ->map(fn ($r) => $r->trip_start_at->isSameDay($r->trip_end_at)
                ? $r->trip_start_at->format('M d, Y')
                : $r->trip_start_at->format('M d') . ' – ' . $r->trip_end_at->format('M d, Y'))
            ->all();
    }

    /** Other requests still waiting on the same dates (information only). */
    #[Computed]
    public function overlappingOpenCount(): int
    {
        return RentalRequest::where('vehicle_id', $this->rentalRequest->vehicle_id)
            ->whereKeyNot($this->rentalRequest->id)
            ->whereIn('status', [RentalRequestStatus::Pending->value, RentalRequestStatus::Quoted->value])
            ->overlapping($this->rentalRequest->trip_start_at, $this->rentalRequest->trip_end_at)
            ->count();
    }

    public function sendQuote(RentalRequestService $service): void
    {
        $firstPayment = (float) $this->rentalRequest->down_payment_amount;

        $this->validate(
            [
                'quotedTotal' => ['required', 'numeric', 'min:' . $firstPayment, 'max:' . RentalRequestService::MAX_TOTAL],
                'quoteNote'   => ['nullable', 'string', 'max:255'],
            ],
            [
                'quotedTotal.min' => 'The full payment cannot be lower than the first payment (' . Peso::format($firstPayment) . ').',
            ],
            [
                'quotedTotal' => 'full payment',
                'quoteNote'   => 'note',
            ],
        );

        try {
            $updated = $service->sendQuote(
                $this->rentalRequest,
                auth()->user(),
                (float) $this->quotedTotal,
                $this->quoteNote,
            );
        } catch (\DomainException $e) {
            $this->rentalRequest->refresh();
            $this->addError('form', $e->getMessage());
            return;
        }

        $this->rentalRequest = $updated->load(['vehicle', 'passenger.userStatus']);
        $this->reset(['quotedTotal', 'quoteNote']);
        Flux::modal('send-quote')->close();

        Flux::toast(
            variant: 'success',
            heading: 'Quote sent',
            duration: 4000,
            text: 'The passenger has ' . RentalRequestService::QUOTE_VALID_HOURS . ' hours to accept your price.',
        );
    }

    public function decline(RentalRequestService $service): void
    {
        $this->validate(
            ['declineReason' => ['nullable', 'string', 'max:255']],
            [],
            ['declineReason' => 'reason'],
        );

        try {
            $updated = $service->decline($this->rentalRequest, auth()->user(), $this->declineReason);
        } catch (\DomainException $e) {
            $this->rentalRequest->refresh();
            $this->addError('form', $e->getMessage());
            return;
        }

        $this->rentalRequest = $updated->load(['vehicle', 'passenger.userStatus']);
        $this->reset('declineReason');
        Flux::modal('decline-request')->close();

        Flux::toast(
            variant: 'success',
            heading: 'Request declined',
            duration: 4000,
            text: 'The passenger will see that you declined.',
        );
    }
};
?>

@php
    $r         = $rentalRequest;
    $downPay   = (float) $r->down_payment_amount;
    $firstPay  = (float) ($r->quoted_first_payment ?? $r->down_payment_amount);
    $badgeColor = match ($r->status) {
        \App\Enums\RentalRequestStatus::Pending         => 'yellow',
        \App\Enums\RentalRequestStatus::Quoted          => 'blue',
        \App\Enums\RentalRequestStatus::AwaitingPayment => 'orange',
        \App\Enums\RentalRequestStatus::Confirmed       => 'green',
        \App\Enums\RentalRequestStatus::Completed       => 'zinc',
        default                              => 'red',
    };
    $passengerName = ($r->passenger && ! $r->passenger->isDeleted()) ? $r->passenger->name : 'Deleted User';
    $showContact   = in_array($r->status, [\App\Enums\RentalRequestStatus::Confirmed, \App\Enums\RentalRequestStatus::Completed], true)
        && $r->passenger && ! $r->passenger->isDeleted();
@endphp

<div>
    <x-page-header heading="Review Rental Request"></x-page-header>

    <div class="flex flex-col-reverse sm:flex-row sm:items-start sm:justify-between gap-3 sm:gap-4 mb-6">
        <div>
            <x-heading
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-section-heading)"
            >
                {{ $r->event_name }} for {{ $r->vehicle->plate_number }}
            </x-heading>
            <div class="mt-1 flex flex-wrap items-center gap-2">
                <flux:badge :color="$badgeColor" size="sm" class="font-secondary text-badge text-xs">{{ $r->status->label() }}</flux:badge>
                <x-text class="text-xs text-light-txt-muted dark:text-dark-txt-muted">
                    Requested {{ $r->created_at->diffForHumans() }}
                </x-text>
            </div>
        </div>

        <flux:breadcrumbs class="shrink-0 sm:pt-1">
            <flux:breadcrumbs.item href="{{ route('rental-details', ['vehicle' => $r->vehicle_id]) }}" wire:navigate>
                Back to rental details
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Review request</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-10 gap-4 items-start">
        {{-- ===================== LEFT: REQUEST DETAILS ===================== --}}
        <div class="lg:col-span-7 space-y-4">
            <flux:card class="!p-4 sm:!p-6">
                <x-heading class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary mb-3" style="font-size: var(--text-section-heading)">
                    Passenger and trip
                </x-heading>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                    <div>
                        <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-0.5">Passenger</span>
                        <span class="text-sm font-medium text-light-txt-body dark:text-dark-txt-body">{{ $passengerName }}</span>
                        @if ($showContact && filled($r->passenger->phone_number))
                            <span class="block text-xs text-light-txt-muted dark:text-dark-txt-muted">{{ $r->passenger->phone_number }}</span>
                        @elseif (! $showContact)
                            <span class="block text-xs text-light-txt-muted dark:text-dark-txt-muted">Contact details are shown once the booking is confirmed.</span>
                        @endif
                    </div>

                    <div>
                        <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-0.5">Event</span>
                        <span class="text-sm font-medium text-light-txt-body dark:text-dark-txt-body">{{ $r->event_name }}</span>
                    </div>

                    <div>
                        <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-0.5">From (Point A)</span>
                        <span class="text-sm font-medium text-light-txt-body dark:text-dark-txt-body">
                            {{ $r->pickup_municipality }}@if ($r->pickup_barangay), {{ $r->pickup_barangay }}@endif
                        </span>
                        <span class="block text-xs text-light-txt-muted dark:text-dark-txt-muted">{{ $r->pickup_details }}</span>
                    </div>

                    <div>
                        <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-0.5">To (Point B)</span>
                        <span class="text-sm font-medium text-light-txt-body dark:text-dark-txt-body">
                            {{ $r->destination_municipality }}@if ($r->destination_barangay), {{ $r->destination_barangay }}@endif
                        </span>
                        <span class="block text-xs text-light-txt-muted dark:text-dark-txt-muted">{{ $r->destination_details }}</span>
                    </div>

                    <div>
                        <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-0.5">Trip start</span>
                        <span class="text-sm font-medium text-light-txt-body dark:text-dark-txt-body">{{ $r->trip_start_at->format('M d, Y · g:i A') }}</span>
                    </div>

                    <div>
                        <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-0.5">Trip end</span>
                        <span class="text-sm font-medium text-light-txt-body dark:text-dark-txt-body">{{ $r->trip_end_at->format('M d, Y · g:i A') }}</span>
                        <span class="block text-xs text-light-txt-muted dark:text-dark-txt-muted">{{ $r->trip_start_at->diffForHumans($r->trip_end_at, true) }} in total</span>
                    </div>

                    <div>
                        <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-0.5">Passengers</span>
                        <span class="text-sm font-medium text-light-txt-body dark:text-dark-txt-body">{{ $r->passenger_count }} of {{ $r->vehicle->total_seats }} seats</span>
                    </div>

                    <div>
                        <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-0.5">Vehicle</span>
                        <span class="text-sm font-medium text-light-txt-body dark:text-dark-txt-body">{{ $r->vehicle->plate_number }} · {{ $r->vehicle->vehicle_type }}</span>
                    </div>
                </div>

                @if (filled($r->notes))
                    <div class="mt-4 rounded-lg bg-light-subtle dark:bg-dark-subtle p-3">
                        <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-1">Passenger's notes</span>
                        <p class="text-sm text-light-txt-body dark:text-dark-txt-body whitespace-pre-line break-words">{{ $r->notes }}</p>
                    </div>
                @endif
            </flux:card>

            <flux:card class="!p-4 sm:!p-6">
                <x-heading class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary mb-3" style="font-size: var(--text-section-heading)">
                    Your prices for this event
                </x-heading>
                <div>
                    <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-0.5">Down payment:</span>
                    <span class="text-base font-bold font-primary text-light-txt-primary dark:text-dark-txt-primary tabular-nums">{{ \App\Support\Peso::format($downPay) }}</span>
                </div>
                <x-text class="mt-2 text-xs text-light-txt-muted dark:text-dark-txt-muted">
                    The passenger saw the Down Payment when sending the request, and it stays the same for this request even if you change it later. You set the full payment in your quote, based on this route and group.
                </x-text>
            </flux:card>
        </div>

        {{-- ===================== RIGHT: DECISION ===================== --}}
        <div class="lg:col-span-3 lg:sticky lg:top-4 space-y-4">
            <flux:card class="!p-4 sm:!p-5">
                {{-- ---------- PENDING ---------- --}}
                @if ($r->status === \App\Enums\RentalRequestStatus::Pending)
                    <x-heading class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary mb-3" style="font-size: var(--text-section-heading)">
                        Your decision
                    </x-heading>

                    @if ($this->isLate)
                        <div class="mb-3 rounded-lg border border-warning/40 bg-warning/10 dark:bg-dark-warning/10 p-3 text-sm text-light-txt-body dark:text-dark-txt-body">
                            The response time for this request has passed. It will be marked as expired when you try to answer it.
                        </div>
                    @elseif ($r->respond_by)
                        <x-text class="mb-3 text-xs text-light-txt-muted dark:text-dark-txt-muted">
                            Please respond by {{ $r->respond_by->format('M d, g:i A') }} ({{ $r->respond_by->diffForHumans() }}).
                        </x-text>
                    @endif

                    {{-- Date check --}}
                    @if (count($this->conflicts) > 0)
                        <div class="mb-3 rounded-lg border border-danger/40 bg-danger/10 dark:bg-dark-danger/10 p-3 text-sm">
                            <p class="font-medium text-danger dark:text-dark-danger mb-1">These dates are not free</p>
                            <ul class="space-y-0.5 text-light-txt-body dark:text-dark-txt-body">
                                @foreach ($this->conflicts as $range)
                                    <li>Booked or on hold: {{ $range }}</li>
                                @endforeach
                            </ul>
                            <p class="mt-1 text-xs text-light-txt-muted dark:text-dark-txt-muted">You can decline this request.</p>
                        </div>
                    @else
                        <div class="mb-3 flex items-center gap-2 text-sm text-success dark:text-dark-success">
                            <flux:icon.check-circle class="w-4 h-4 shrink-0" />
                            These dates are free.
                        </div>
                    @endif

                    @if ($this->overlappingOpenCount > 0)
                        <x-text class="mb-3 text-xs text-light-txt-muted dark:text-dark-txt-muted">
                            {{ $this->overlappingOpenCount }} other {{ \Illuminate\Support\Str::plural('request', $this->overlappingOpenCount) }} for these dates {{ $this->overlappingOpenCount === 1 ? 'is' : 'are' }} still open. The first passenger to accept a quote and pay gets the dates.
                        </x-text>
                    @endif

                    @if ($r->vehicle->rental_status === \App\Enums\RentalStatus::Suspended)
                        <div class="mb-3 rounded-lg border border-danger/40 bg-danger/10 dark:bg-dark-danger/10 p-3 text-sm text-danger dark:text-dark-danger">
                            Rental for this vehicle was suspended by the admin. You can only decline.
                        </div>
                    @endif

                    <div class="flex flex-col gap-2">
                        <flux:modal.trigger name="send-quote">
                            <flux:button
                                variant="primary"
                                class="w-full justify-center font-secondary"
                                :disabled="! $this->canRespond || count($this->conflicts) > 0"
                            >
                                Send quote
                            </flux:button>
                        </flux:modal.trigger>

                        <flux:modal.trigger name="decline-request">
                            <flux:button variant="danger" class="w-full justify-center font-secondary">
                                Decline
                            </flux:button>
                        </flux:modal.trigger>
                    </div>

                {{-- ---------- QUOTED ---------- --}}
                @elseif ($r->status === \App\Enums\RentalRequestStatus::Quoted)
                    <x-heading class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary mb-3" style="font-size: var(--text-section-heading)">
                        Your quote
                    </x-heading>
                    <div class="space-y-3">
                        <div>
                            <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-0.5">Full price</span>
                            <span class="text-xl font-bold font-primary text-light-txt-primary dark:text-dark-txt-primary tabular-nums">₱{{ number_format((float) $r->quoted_total, 2) }}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <span class="block text-[10px] text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-0.5">Online now</span>
                                <span class="font-medium text-light-txt-body dark:text-dark-txt-body tabular-nums">₱{{ number_format($firstPay, 2) }}</span>
                            </div>
                            <div>
                                <span class="block text-[10px] text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-0.5">Cash on the trip</span>
                                <span class="font-medium text-light-txt-body dark:text-dark-txt-body tabular-nums">₱{{ number_format(max(0, (float) $r->quoted_total - $firstPay), 2) }}</span>
                            </div>
                        </div>
                        @if (filled($r->quote_note))
                            <p class="text-sm text-light-txt-body dark:text-dark-txt-body break-words">{{ $r->quote_note }}</p>
                        @endif
                        <x-text class="text-xs text-light-txt-muted dark:text-dark-txt-muted">
                            Waiting for the passenger. The quote expires {{ $r->quote_expires_at?->format('M d, g:i A') }} ({{ $r->quote_expires_at?->diffForHumans() }}).
                        </x-text>
                    </div>

                {{-- ---------- EVERYTHING ELSE ---------- --}}
                @else
                    <x-heading class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary mb-3" style="font-size: var(--text-section-heading)">
                        Status
                    </x-heading>
                    <div class="space-y-2 text-sm text-light-txt-body dark:text-dark-txt-body">
                        <flux:badge :color="$badgeColor" size="sm" class="font-secondary text-badge text-xs">{{ $r->status->label() }}</flux:badge>

                        @if (filled($r->status_reason))
                            <p class="break-words">{{ $r->status_reason }}</p>
                        @endif

                        @if ($r->quoted_total !== null)
                            <p class="tabular-nums">Quoted full price: <strong>₱{{ number_format((float) $r->quoted_total, 2) }}</strong></p>
                            @if ($r->quoted_first_payment !== null)
                                <p class="tabular-nums">Down payment: <strong>₱{{ number_format((float) $r->quoted_first_payment, 2) }}</strong></p>
                            @endif
                        @endif
                    </div>
                @endif
            </flux:card>
        </div>
    </div>

    {{-- ===================== SEND QUOTE MODAL ===================== --}}
    @if ($r->status === \App\Enums\RentalRequestStatus::Pending)
        <flux:modal name="send-quote" class="w-[calc(100%-2rem)] sm:max-w-md mx-auto rounded-xl">
            <form wire:submit="sendQuote" class="flex flex-col gap-4 p-1">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">Send quote</flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        Review the route, dates, and number of passengers, then set the full payment for this trip.
                    </flux:text>
                </div>

                @error('form')
                    <div class="rounded-lg border border-danger/40 bg-danger/10 dark:bg-dark-danger/10 p-3 text-sm text-danger dark:text-dark-danger">{{ $message }}</div>
                @enderror

                <div class="rounded-lg bg-light-subtle dark:bg-dark-subtle p-3 text-sm text-light-txt-body dark:text-dark-txt-body tabular-nums">
                    Down payment, paid online by the passenger: <strong>{{ \App\Support\Peso::format($downPay) }}</strong>
                </div>

                <flux:input
                    wire:model.live.debounce.400ms="quotedTotal"
                    type="number"
                    step="0.01"
                    min="{{ $downPay }}"
                    inputmode="decimal"
                    label="Full payment (₱)"
                    description="The total for the whole trip, based on the route and number of passengers. The Down Payment is part of it."
                    placeholder="e.g. 4500"
                />

                @php $typedTotal = is_numeric($quotedTotal) ? (float) $quotedTotal : null; @endphp
                @if ($typedTotal !== null && $typedTotal >= $downPay)
                    <div class="rounded-lg bg-light-subtle dark:bg-dark-subtle p-3 text-sm text-light-txt-body dark:text-dark-txt-body tabular-nums space-y-0.5">
                        <p>Passenger pays online now: <strong>{{ \App\Support\Peso::format($downPay) }}</strong></p>
                        <p>Passenger pays you in cash on the trip: <strong>₱{{ number_format($typedTotal - $downPay, 2) }}</strong></p>
                    </div>
                @endif

                <flux:textarea wire:model="quoteNote" label="What is included (optional)" rows="2" placeholder="e.g. Fuel, toll fees, and driver included" />

                <x-text class="text-xs text-light-txt-muted dark:text-dark-txt-muted">
                    The passenger has {{ \App\Services\RentalRequestService::QUOTE_VALID_HOURS }} hours to accept. Once they accept and pay the Down Payment, the trip is confirmed.
                </x-text>

                <div class="flex flex-col-reverse sm:flex-row justify-end gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" class="w-full sm:w-auto justify-center font-secondary">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="sendQuote" class="w-full sm:w-auto justify-center font-secondary">
                        Send quote
                    </flux:button>
                </div>
            </form>
        </flux:modal>

        {{-- ===================== DECLINE MODAL ===================== --}}
        <flux:modal name="decline-request" class="w-[calc(100%-2rem)] sm:max-w-md mx-auto rounded-xl">
            <form wire:submit="decline" class="flex flex-col gap-4 p-1">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">Decline this request?</flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        The passenger will be told that you declined. This cannot be undone.
                    </flux:text>
                </div>

                @error('form')
                    <div class="rounded-lg border border-danger/40 bg-danger/10 dark:bg-dark-danger/10 p-3 text-sm text-danger dark:text-dark-danger">{{ $message }}</div>
                @enderror

                <flux:textarea wire:model="declineReason" label="Reason (optional)" rows="3" placeholder="e.g. The vehicle is not available on those dates" />

                <div class="flex flex-col-reverse sm:flex-row justify-end gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" class="w-full sm:w-auto justify-center font-secondary">Keep request</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger" wire:loading.attr="disabled" wire:target="decline" class="w-full sm:w-auto justify-center font-secondary">
                        Decline request
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>