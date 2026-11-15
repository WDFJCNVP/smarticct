<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

use Flux\Flux;

use App\Services\RentalNotifier;
use Illuminate\Support\Facades\Log;

use App\Enums\RentalRequestStatus;
use App\Models\RentalRequest;
use App\Services\RentalCheckoutService;
use App\Services\RentalRequestService;

new #[Layout('layouts.commuter-layout')] class extends Component
{
    use WithPagination;

    /** Set by PayMongo's success / cancel redirect: ?payment=return | cancelled */
    #[Url]
    public ?string $payment = null;

    /** The request shown in the "Pay now" modal. */
    public ?int $payingRequestId = null;

    /** Status filter: 'all' or one of the keys in statusOptions(). Kept in the URL as ?status=... */
    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    /** Back to the first page whenever the filter changes. */
    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    /** The statuses offered in the dropdown (value => label). */
    #[Computed]
    public function statusOptions(): array
    {
        return [
            RentalRequestStatus::AwaitingPayment->value => 'Awaiting first payment',
            RentalRequestStatus::Confirmed->value       => RentalRequestStatus::Confirmed->label(),
            RentalRequestStatus::Completed->value       => RentalRequestStatus::Completed->label(),
            RentalRequestStatus::Cancelled->value       => RentalRequestStatus::Cancelled->label(),
            RentalRequestStatus::Expired->value         => RentalRequestStatus::Expired->label(),
        ];
    }

    /**
     * Matches what the cards show: a request whose quote, payment or reply
     * deadline has passed is displayed as "Expired" even if its stored status
     * has not been updated yet, so the Expired filter includes those too and
     * the Awaiting filter leaves them out.
     */
    private function applyStatusFilter($query)
    {
        $now = now();

        if ($this->statusFilter === RentalRequestStatus::Expired->value) {
            return $query->where(function ($q) use ($now) {
                $q->where('status', RentalRequestStatus::Expired->value)
                    ->orWhere(fn ($q2) => $q2->where('status', RentalRequestStatus::Quoted->value)
                        ->whereNotNull('quote_expires_at')->where('quote_expires_at', '<', $now))
                    ->orWhere(fn ($q2) => $q2->where('status', RentalRequestStatus::AwaitingPayment->value)
                        ->whereNotNull('hold_expires_at')->where('hold_expires_at', '<', $now))
                    ->orWhere(fn ($q2) => $q2->where('status', RentalRequestStatus::Pending->value)
                        ->whereNotNull('respond_by')->where('respond_by', '<', $now));
            });
        }

        if ($this->statusFilter === RentalRequestStatus::AwaitingPayment->value) {
            return $query->where('status', RentalRequestStatus::AwaitingPayment->value)
                ->where(fn ($q) => $q->whereNull('hold_expires_at')->orWhere('hold_expires_at', '>=', $now));
        }

        return $query->where('status', $this->statusFilter);
    }

    #[Computed]
    public function requests()
    {
        return RentalRequest::forPassenger(auth()->user())
            ->with('vehicle.user:id,name,phone_number')
            ->when(
                array_key_exists($this->statusFilter, $this->statusOptions),
                fn ($q) => $this->applyStatusFilter($q)
            )
            // Quotes to answer and payments to make come first, then newest.
            ->orderByRaw('CASE WHEN status IN (?, ?) THEN 0 ELSE 1 END', [
                RentalRequestStatus::Quoted->value,
                RentalRequestStatus::AwaitingPayment->value,
            ])
            ->latest()
            ->paginate(8);
    }

    /** The request currently open in the Pay now modal. */
    #[Computed]
    public function paying(): ?RentalRequest
    {
        return $this->payingRequestId
            ? RentalRequest::forPassenger(auth()->user())->with('vehicle')->find($this->payingRequestId)
            : null;
    }

    public function dismissPaymentNotice(): void
    {
        $this->payment = null;
    }

    public function acceptQuote(int $id, RentalRequestService $service, RentalNotifier $notifier): void
    {
        $request = $this->mine($id);

        try {
            $updated = $service->acceptQuote($request, auth()->user());
        } catch (\DomainException $e) {
            $this->toastError('Cannot accept this quote', $e->getMessage());
            return;
        }

        $this->notify(fn () => $notifier->quoteAccepted($updated), $updated->id);

        Flux::toast(
            variant: 'success',
            heading: 'Quote accepted',
            duration: 4000,
            text: 'The dates are held for you. Pay the Down Payment to confirm your booking.',
        );

        // The payment window appears right away.
        $this->openPayment($id);
    }

    public function rejectQuote(int $id, RentalRequestService $service, RentalNotifier $notifier): void
    {
        try {
            $updated = $service->rejectQuote($this->mine($id), auth()->user());
        } catch (\DomainException $e) {
            $this->toastError('Cannot decline this quote', $e->getMessage());
            return;
        }

        $this->notify(fn () => $notifier->quoteDeclined($updated), $updated->id);

        Flux::toast(variant: 'success', heading: 'Quote declined', duration: 3000, text: 'The operator will see that you declined.');
    }

    public function cancel(int $id, RentalRequestService $service, RentalNotifier $notifier): void
    {
        $request     = $this->mine($id);
        // Read before cancelling: the notification says whether held dates were freed.
        $heldDates   = $request->status === RentalRequestStatus::AwaitingPayment;

        try {
            $updated = $service->cancel($this->mine($id), auth()->user());
        } catch (\DomainException $e) {
            $this->toastError('Cannot cancel this request', $e->getMessage());
            return;
        }

        $this->notify(fn () => $notifier->requestCancelled($updated, $heldDates), $updated->id);


        Flux::toast(variant: 'success', heading: 'Request cancelled', duration: 3000, text: 'Your request was cancelled.');
    }

    private function notify(\Closure $send, int $requestId): void
    {
        try {
            $send();
        } catch (\Throwable $e) {
            Log::error('Rental notification failed', ['request_id' => $requestId, 'error' => $e->getMessage()]);
        }
    }

    /** Opens the payment procedure modal for a request waiting for its first payment. */
    public function openPayment(int $id): void
    {
        $request = $this->mine($id);

        if ($request->status !== RentalRequestStatus::AwaitingPayment
            || ! $request->hold_expires_at
            || $request->hold_expires_at->isPast()) {
            $this->toastError('Payment not available', 'The payment time for this request has passed.');
            return;
        }

        $this->payingRequestId = $id;
        $this->resetErrorBag();

        Flux::modal('pay-now')->show();
    }

    /** "Continue to payment": creates the PayMongo checkout and sends the passenger there. */
    public function startPayment(RentalCheckoutService $checkout): void
    {
        $request = $this->paying;

        if (! $request) {
            $this->addError('payment', 'Please reopen the payment and try again.');
            return;
        }

        try {
            $url = $checkout->createCheckout($request, auth()->user());
        } catch (\DomainException $e) {
            $this->addError('payment', $e->getMessage());
            return;
        }

        // External PayMongo page, so a normal redirect (not wire:navigate).
        $this->redirect($url);
    }

    /** Only the passenger's own requests can be acted on. */
    private function mine(int $id): RentalRequest
    {
        return RentalRequest::forPassenger(auth()->user())->findOrFail($id);
    }

    private function toastError(string $heading, string $text): void
    {
        Flux::toast(variant: 'danger', heading: $heading, duration: 5000, text: $text);
    }
};
?>

@php
    $S = \App\Enums\RentalRequestStatus::class;
@endphp

<div>
    <x-page-header heading="My Rental Requests"></x-page-header>

    <div class="mb-6 flex items-center justify-between gap-3">
        <div class="min-w-0">
            <x-heading
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-section-heading)"
            >
                Your requests and quotes
            </x-heading>
            <x-text class="mt-1 text-sm text-light-txt-muted dark:text-dark-txt-muted">
                Check the operator's price, accept it, and pay the Down Payment to confirm your booking.
            </x-text>
        </div>

        <flux:button
            variant="ghost"
            icon="arrow-left"
            x-on:click="window.history.back()"
            class="shrink-0 font-secondary"
        >
            Back to vehicles
        </flux:button>
    </div>

    {{-- ===================== STATUS FILTER ===================== --}}
    <div class="mb-4 flex justify-end">
        <flux:dropdown align="end">
            <flux:button icon="funnel" icon:trailing="chevron-down" class="font-secondary">
                Status: {{ $this->statusOptions[$this->statusFilter] ?? 'All' }}
            </flux:button>

            <flux:menu>
                <flux:menu.radio.group wire:model.live="statusFilter">
                    <flux:menu.radio value="all">All statuses</flux:menu.radio>
                    @foreach ($this->statusOptions as $value => $label)
                        <flux:menu.radio value="{{ $value }}">{{ $label }}</flux:menu.radio>
                    @endforeach
                </flux:menu.radio.group>
            </flux:menu>
        </flux:dropdown>
    </div>

    {{-- Shown after PayMongo sends the passenger back --}}
    @if ($payment === 'return')
        <div wire:poll.5s class="mb-4 flex items-start justify-between gap-3 rounded-lg border border-primary/30 bg-primary/10 dark:bg-primary/20 p-3 text-sm text-light-txt-body dark:text-dark-txt-body">
            <p>Thank you. If you just paid, your booking is confirmed automatically once the payment is received. This page updates by itself.</p>
            <button type="button" wire:click="dismissPaymentNotice" class="shrink-0 text-light-txt-muted dark:text-dark-txt-muted" aria-label="Dismiss">
                <flux:icon.x-mark class="w-4 h-4" />
            </button>
        </div>
    @elseif ($payment === 'cancelled')
        <div class="mb-4 flex items-start justify-between gap-3 rounded-lg border border-warning/40 bg-warning/10 dark:bg-dark-warning/10 p-3 text-sm text-light-txt-body dark:text-dark-txt-body">
            <p>The payment was not completed. You can try again with Pay now before the deadline.</p>
            <button type="button" wire:click="dismissPaymentNotice" class="shrink-0 text-light-txt-muted dark:text-dark-txt-muted" aria-label="Dismiss">
                <flux:icon.x-mark class="w-4 h-4" />
            </button>
        </div>
    @endif

    <div class="space-y-3 mb-4">
        @forelse ($this->requests as $request)
            @php
                $first     = (float) ($request->quoted_first_payment ?? 0);
                $total     = $request->quoted_total !== null ? (float) $request->quoted_total : null;
                $lapsedQuote = $request->status === $S::Quoted
                    && $request->quote_expires_at && $request->quote_expires_at->isPast();
                $lapsedHold  = $request->status === $S::AwaitingPayment
                    && $request->hold_expires_at && $request->hold_expires_at->isPast();
                $lapsedWait  = $request->status === $S::Pending
                    && $request->respond_by && $request->respond_by->isPast();
                $lapsed = $lapsedQuote || $lapsedHold || $lapsedWait;

                $badgeColor = $lapsed ? 'red' : match ($request->status) {
                    $S::Pending         => 'yellow',
                    $S::Quoted          => 'blue',
                    $S::AwaitingPayment => 'orange',
                    $S::Confirmed       => 'green',
                    $S::Completed       => 'zinc',
                    default             => 'red',
                };
                $badgeText = $lapsed ? 'Expired' : $request->status->label();
            @endphp

            <flux:card wire:key="request-{{ $request->id }}" class="!p-4 sm:!p-5 !bg-primary !border-primary dark:!border-white/15">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/10 text-white shrink-0">
                            <flux:icon.truck class="w-4 h-4 sm:w-5 sm:h-5" />
                        </div>
                        <div class="min-w-0">
                            <x-text class="font-medium text-white text-sm sm:text-base">
                                {{ $request->event_name }}
                            </x-text>
                            <x-text class="text-xs text-white/70">
                                <span class="font-mono">{{ $request->vehicle->plate_number }}</span> · {{ $request->vehicle->vehicle_type }}
                            </x-text>
                        </div>
                    </div>
                    <span class="inline-flex shrink-0 rounded-md bg-white">
                        <flux:badge :color="$badgeColor" size="sm" class="font-secondary text-badge text-xs">{{ $badgeText }}</flux:badge>
                    </span>
                </div>

                <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-x-4 gap-y-2 text-sm">
                    <div>
                        <span class="block text-[10px] sm:text-xs text-white/70 uppercase tracking-wider">Route</span>
                        <span class="text-white/85">{{ $request->pickup_municipality }} → {{ $request->destination_municipality }}</span>
                    </div>
                    <div>
                        <span class="block text-[10px] sm:text-xs text-white/70 uppercase tracking-wider">Trip</span>
                        <span class="text-white/85">
                            {{ $request->trip_start_at->format('M d, g:i A') }} – {{ $request->trip_end_at->format($request->trip_end_at->isSameDay($request->trip_start_at) ? 'g:i A' : 'M d, g:i A') }}
                        </span>
                    </div>
                    <div>
                        <span class="block text-[10px] sm:text-xs text-white/70 uppercase tracking-wider">Passengers</span>
                        <span class="text-white/85">{{ $request->passenger_count }}</span>
                    </div>
                </div>

                {{-- ---------- state-specific area ---------- --}}

                {{-- Waiting for the operator --}}
                @if ($request->status === $S::Pending && ! $lapsedWait)
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-white/15 pt-3">
                        <x-text class="text-xs text-white/70">
                            Waiting for the operator to reply{{ $request->respond_by ? ' by ' . $request->respond_by->format('M d, g:i A') : '' }}.
                        </x-text>
                        <flux:button
                            wire:click="cancel({{ $request->id }})"
                            wire:confirm="Cancel this request?"
                            size="sm" variant="ghost" class="font-secondary !text-white hover:!bg-white/10"
                        >Cancel request</flux:button>
                    </div>

                {{-- A quote to answer --}}
                @elseif ($request->status === $S::Quoted && ! $lapsedQuote)
                    <div class="mt-4 rounded-lg bg-white/10 p-3 sm:p-4">
                        <p class="text-[10px] sm:text-xs uppercase tracking-wider text-white/70">The operator's price</p>
                        <p class="text-2xl font-bold font-primary text-white tabular-nums">₱{{ number_format($total, 2) }}</p>

                        <div class="mt-2 grid grid-cols-2 gap-3 text-sm tabular-nums">
                            <div>
                                <span class="block text-[10px] text-white/70 uppercase tracking-wider">Pay online now</span>
                                <span class="font-medium text-white/85">₱{{ number_format($first, 2) }}</span>
                            </div>
                            <div>
                                <span class="block text-[10px] text-white/70 uppercase tracking-wider">Cash to the operator on the trip</span>
                                <span class="font-medium text-white/85">₱{{ number_format(max(0, $total - $first), 2) }}</span>
                            </div>
                        </div>

                        @if (filled($request->quote_note))
                            <p class="mt-2 text-sm text-white/85 break-words">{{ $request->quote_note }}</p>
                        @endif

                        <x-text class="mt-2 text-xs text-white/70">
                            Answer by {{ $request->quote_expires_at?->format('M d, g:i A') }} ({{ $request->quote_expires_at?->diffForHumans() }}).
                        </x-text>
                    </div>

                    <div class="mt-3 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                        <flux:button
                            wire:click="rejectQuote({{ $request->id }})"
                            wire:confirm="Decline this price?"
                            variant="ghost" class="w-full sm:w-auto justify-center font-secondary !text-white hover:!bg-white/10"
                        >Decline</flux:button>
                        <flux:button
                            wire:click="acceptQuote({{ $request->id }})"
                            wire:loading.attr="disabled"
                            wire:target="acceptQuote({{ $request->id }})"
                            variant="primary" class="w-full sm:w-auto justify-center font-secondary !bg-white !text-primary hover:!bg-white/90"
                        >Accept quote</flux:button>
                    </div>

                {{-- Accepted: waiting for the Down Payment --}}
                @elseif ($request->status === $S::AwaitingPayment && ! $lapsedHold)
                    <div class="mt-4 rounded-lg border border-amber-300/40 bg-amber-300/10 p-3 text-sm">
                        <p class="font-medium text-white tabular-nums">
                            Pay ₱{{ number_format($first, 2) }} to confirm your booking.
                        </p>
                        <p class="text-xs text-white/70">
                            The dates are held for you until {{ $request->hold_expires_at->format('M d, g:i A') }} ({{ $request->hold_expires_at->diffForHumans() }}).
                        </p>
                    </div>

                    <div class="mt-3 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                        <flux:button
                            wire:click="cancel({{ $request->id }})"
                            wire:confirm="Cancel this booking? The dates will be released."
                            variant="ghost" class="w-full sm:w-auto justify-center font-secondary !text-white hover:!bg-white/10"
                        >Cancel booking</flux:button>
                        <flux:button
                            wire:click="openPayment({{ $request->id }})"
                            variant="primary" class="w-full sm:w-auto justify-center font-secondary !bg-white !text-primary hover:!bg-white/90"
                        >Pay now</flux:button>
                    </div>

                {{-- Confirmed or finished: show the operator's contact --}}
                @elseif (in_array($request->status, [$S::Confirmed, $S::Completed], true))
                    <div class="mt-4 rounded-lg bg-white/10 p-3 text-sm text-white/85 space-y-1">
                        <p class="tabular-nums">
                            Total price: <strong>₱{{ number_format($total ?? 0, 2) }}</strong>.
                            You paid <strong>₱{{ number_format($first, 2) }}</strong> online.
                            @if ($request->status === $S::Confirmed)
                                Pay the balance of <strong>₱{{ number_format(max(0, ($total ?? 0) - $first), 2) }}</strong> in cash to the operator on the trip.
                            @endif
                        </p>
                        <p>
                            Operator: <strong>{{ $request->vehicle->user?->name ?? '—' }}</strong>
                            @if (filled($request->vehicle->user?->phone_number))
                                · {{ $request->vehicle->user->phone_number }}
                            @endif
                            @if (filled($request->vehicle->driver_name))
                                · Driver: {{ $request->vehicle->driver_name }}
                            @endif
                        </p>
                    </div>

                {{-- Everything else: closed, declined, expired, cancelled --}}
                @else
                    <div class="mt-4 border-t border-white/15 pt-3 text-xs text-white/70">
                        @if ($lapsedQuote)
                            The quote was not answered in time, so it expired.
                        @elseif ($lapsedHold)
                            The payment time passed, so the dates were released.
                        @elseif ($lapsedWait)
                            The operator did not reply in time.
                        @elseif (filled($request->status_reason))
                            {{ $request->status_reason }}
                        @else
                            This request is closed.
                        @endif
                    </div>
                @endif
            </flux:card>
        @empty
            <flux:card class="!p-6">
                <div class="flex flex-col items-center justify-center py-8 gap-2 text-center">
                    <flux:icon.inbox-stack class="w-8 h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                    <x-text class="text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        @if (isset($this->statusOptions[$this->statusFilter]))
                            You have no "{{ $this->statusOptions[$this->statusFilter] }}" requests.
                        @else
                            You have not sent any rental requests yet.
                        @endif
                    </x-text>
                </div>
            </flux:card>
        @endforelse
    </div>

    @if ($this->requests->hasPages())
        <div class="flex justify-end">{{ $this->requests->links() }}</div>
    @endif

    {{-- ===================== PAY NOW MODAL ===================== --}}
    <flux:modal name="pay-now" class="w-[calc(100%-2rem)] sm:max-w-lg mx-auto rounded-xl">
        @if ($this->paying)
            @php
                $p      = $this->paying;
                $pFirst = (float) ($p->quoted_first_payment ?? 0);
                $pTotal = (float) $p->quoted_total;
            @endphp

            <div class="flex flex-col gap-4 max-h-[75vh] overflow-y-auto p-1">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">Pay your Down Payment</flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        {{ $p->event_name }} · {{ $p->vehicle->plate_number }} · {{ $p->trip_start_at->format('M d, Y') }}
                    </flux:text>
                </div>

                @error('payment')
                    <div class="rounded-lg border border-danger/40 bg-danger/10 dark:bg-dark-danger/10 p-3 text-sm text-danger dark:text-dark-danger">{{ $message }}</div>
                @enderror

                <div class="rounded-lg bg-light-subtle dark:bg-dark-subtle p-3 sm:p-4 tabular-nums">
                    <div class="flex items-center justify-between text-sm text-light-txt-body dark:text-dark-txt-body">
                        <span>Total price</span><span>₱{{ number_format($pTotal, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm text-light-txt-body dark:text-dark-txt-body mt-1">
                        <span>Cash to the operator on the trip</span><span>₱{{ number_format(max(0, $pTotal - $pFirst), 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between mt-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        <span>Pay now (online)</span><span>₱{{ number_format($pFirst, 2) }}</span>
                    </div>
                </div>

                <div>
                    <p class="text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary mb-2">How paying works</p>
                    <ol class="space-y-2 text-sm text-light-txt-body dark:text-dark-txt-body list-decimal pl-5">
                        <li>Press <strong>Continue to payment</strong>. You are taken to PayMongo's secure payment page.</li>
                        <li>Choose GCash, Maya, a card, or QR Ph, and pay <strong>₱{{ number_format($pFirst, 2) }}</strong>.</li>
                        <li>You return to this page. Your booking is confirmed automatically once the payment is received.</li>
                        <li>On the trip, pay the remaining <strong>₱{{ number_format(max(0, $pTotal - $pFirst), 2) }}</strong> in cash to the operator.</li>
                    </ol>
                </div>

                <div class="rounded-lg border border-warning/40 bg-warning/10 dark:bg-dark-warning/10 p-3 text-xs text-light-txt-body dark:text-dark-txt-body space-y-1">
                    <p>Pay before <strong>{{ $p->hold_expires_at->format('M d, g:i A') }}</strong>. After that the request expires and the dates are released.</p>
                    <p>Please pay only once. Your Down Payment is part of the total price.</p>
                </div>

                <div class="flex flex-col-reverse sm:flex-row justify-end gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" class="w-full sm:w-auto justify-center font-secondary">Not now</flux:button>
                    </flux:modal.close>
                    <flux:button
                        wire:click="startPayment"
                        wire:loading.attr="disabled"
                        wire:target="startPayment"
                        variant="primary"
                        class="w-full sm:w-auto justify-center font-secondary"
                    >Continue to payment</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>