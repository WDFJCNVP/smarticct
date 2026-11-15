<?php

namespace App\Services;

use App\Enums\RentalRequestStatus;
use App\Models\RentalRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cleans up rental requests whose time has run out:
 *   pending           the operator did not reply in time       (respond_by)
 *   quoted            the passenger did not accept in time      (quote_expires_at)
 *   awaiting_payment  the passenger did not pay in time         (hold_expires_at)
 * A request whose trip start has already passed is expired as well.
 *
 * The pages already treat a lapsed request as expired, so this job is what makes the
 * stored status, the counts, the notifications and the PayMongo payment pages catch up.
 */
class RentalExpiryService
{
    public function __construct(
        private readonly RentalCheckoutService $checkout,
        private readonly RentalNotifier $notifier,
    ) {
    }

    /** @return array{pending:int, quoted:int, payment:int, pages_not_closed:int, swept:int} */
    public function run(): array
    {
        $pagesNotClosed = 0;

        $summary = [
            'pending' => $this->expirePending(),
            'quoted'  => $this->expireQuoted(),
            'payment' => $this->expireUnpaidHolds($pagesNotClosed),
        ];

        $summary['pages_not_closed'] = $pagesNotClosed;
        $summary['swept']            = $this->sweepOpenPaymentPages();

        if (array_sum($summary) > 0) {
            Log::info('RENTAL EXPIRY: run finished.', $summary);
        }

        return $summary;
    }

    /** The operator never answered. */
    private function expirePending(): int
    {
        $count = 0;

        $this->candidates(RentalRequestStatus::Pending, 'respond_by')->each(function (RentalRequest $candidate) use (&$count) {
            $request = $this->markExpired($candidate->id, RentalRequestStatus::Pending, 'respond_by', 'The operator did not respond in time.');

            if (! $request) {
                return;
            }

            $count++;
            $this->tell($request, 'pending');
        });

        return $count;
    }

    /** The passenger never answered the quote. */
    private function expireQuoted(): int
    {
        $count = 0;

        $this->candidates(RentalRequestStatus::Quoted, 'quote_expires_at')->each(function (RentalRequest $candidate) use (&$count) {
            $request = $this->markExpired($candidate->id, RentalRequestStatus::Quoted, 'quote_expires_at', 'The quote was not accepted in time.');

            if (! $request) {
                return;
            }

            $count++;
            $this->tell($request, 'quoted');
        });

        return $count;
    }

    /** The passenger accepted but never paid. The dates are released and the payment page is closed. */
    private function expireUnpaidHolds(int &$pagesNotClosed): int
    {
        $count = 0;

        $this->candidates(RentalRequestStatus::AwaitingPayment, 'hold_expires_at')->each(function (RentalRequest $candidate) use (&$count, &$pagesNotClosed) {
            // Close the payment page FIRST. If the passenger pays in the next second, the
            // payment message confirms the booking and the re-check below leaves it alone.
            if (! $this->checkout->expireOpenSessions($candidate)) {
                $pagesNotClosed++;
            }

            $request = $this->markExpired($candidate->id, RentalRequestStatus::AwaitingPayment, 'hold_expires_at', 'The payment time passed.');

            if (! $request) {
                return;
            }

            $count++;
            $this->tell($request, 'payment');
        });

        return $count;
    }

    /**
     * Retry closing payment pages that are still open for requests that are no longer
     * waiting for payment (a close that failed earlier, or a late leftover).
     */
    private function sweepOpenPaymentPages(): int
    {
        $closed = 0;

        RentalRequest::whereHas('payments', fn ($q) => $q->whereIn('status', ['pending', 'failed']))
            ->where('status', '!=', RentalRequestStatus::AwaitingPayment->value)
            ->limit(50)
            ->get()
            ->each(function (RentalRequest $request) use (&$closed) {
                if ($this->checkout->expireOpenSessions($request)) {
                    $closed++;
                }
            });

        return $closed;
    }

    /** Requests of a status whose deadline (or trip start) has passed. */
    private function candidates(RentalRequestStatus $status, string $deadlineColumn)
    {
        return RentalRequest::where('status', $status->value)
            ->where(function ($q) use ($deadlineColumn) {
                $q->where($deadlineColumn, '<', now())->orWhere('trip_start_at', '<', now());
            })
            ->orderBy('id')
            ->limit(200)
            ->get();
    }

    /**
     * Marks one request expired, but only if it is still in the expected state and still late.
     * Returns null when something else got there first (answered, paid, cancelled).
     */
    private function markExpired(int $id, RentalRequestStatus $from, string $deadlineColumn, string $reason): ?RentalRequest
    {
        return DB::transaction(function () use ($id, $from, $deadlineColumn, $reason) {
            $locked = RentalRequest::whereKey($id)->lockForUpdate()->with('vehicle')->first();

            if (! $locked || $locked->status !== $from) {
                return null;
            }

            $deadline = $locked->{$deadlineColumn};
            $late     = ($deadline && $deadline->isPast()) || $locked->trip_start_at->isPast();

            if (! $late) {
                return null;
            }

            // Money already arrived: the payment message handles it, never expire it here.
            if ($locked->payments()->whereIn('status', ['paid', 'needs_review'])->exists()) {
                return null;
            }

            $locked->update([
                'status'        => RentalRequestStatus::Expired,
                'status_reason' => $reason,
            ]);

            return $locked;
        });
    }

    /** Tells both sides that the request is now Expired. A notification problem must never stop the clean-up. */
    private function tell(RentalRequest $request, string $stage): void
    {
        try {
            $this->notifier->requestExpired($request, $stage);
        } catch (\Throwable $e) {
            Log::warning('RENTAL EXPIRY: notification failed.', [
                'rental_request_id' => $request->id,
                'error'             => $e->getMessage(),
            ]);
        }
    }
}