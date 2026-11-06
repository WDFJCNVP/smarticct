<?php

namespace App\Services;

use App\Enums\RentalRequestStatus;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\RentalPayment;
use App\Models\RentalRequest;
use App\Support\Peso;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Turns PayMongo's "payment received" message into a confirmed booking and credits
 * the operator. Mirrors the top-up webhook: row locks and a status check make a
 * repeated message harmless, and the money steps all happen in one transaction.
 *
 * rental_payments.status values:
 *   pending       checkout created, nothing paid yet
 *   failed        a payment attempt failed (the session may still be paid later)
 *   expired       the checkout session was closed unpaid
 *   paid          paid, booking confirmed, operator credited
 *   needs_review  money was received but the booking could NOT be confirmed
 *                 (late, wrong amount, request cancelled, ...). An admin refunds it.
 */
class RentalPaymentService
{
    public function __construct(
        private readonly RentalNotifier $notifier,
        private readonly RentalCheckoutService $checkout,
    ) {
    }

    /**
     * @return string  ignored | duplicate | confirmed | needs_review
     */
    public function handlePaid(string $sessionId, ?string $eventId, ?string $method, ?float $confirmedPesos): string
    {
        $result = DB::transaction(function () use ($sessionId, $eventId, $method, $confirmedPesos) {
            $payment = RentalPayment::where('checkout_session_id', $sessionId)->lockForUpdate()->first();

            // Not a rental payment (for example a wallet top-up that reached this endpoint).
            if (! $payment) {
                return ['outcome' => 'ignored'];
            }

            // Already handled: a repeated message must not credit twice.
            if (in_array($payment->status, ['paid', 'needs_review'], true)) {
                return ['outcome' => 'duplicate'];
            }

            $request = RentalRequest::whereKey($payment->rental_request_id)
                ->lockForUpdate()
                ->with('vehicle.user')
                ->firstOrFail();

            $operatorCard = $request->vehicle->user?->card;
            $problem      = $this->problem($payment, $request, $operatorCard !== null, $confirmedPesos);

            $common = [
                'paymongo_event_id' => $eventId,
                'payment_method'    => $method,
                'amount_confirmed'  => $confirmedPesos,
                'paid_at'           => now(),
            ];

            // Money arrived, but the booking cannot be confirmed. Keep the money visible
            // for an admin instead of silently crediting or ignoring it.
            if ($problem !== null) {
                $payment->update($common + [
                    'status'         => 'needs_review',
                    'failure_reason' => Str::limit($problem, 250, ''),
                ]);

                Log::error('RENTAL WEBHOOK: payment received but not confirmed.', [
                    'rental_payment_id' => $payment->id,
                    'rental_request_id' => $request->id,
                    'problem'           => $problem,
                ]);

                return ['outcome' => 'needs_review', 'request' => $request, 'amount' => $confirmedPesos, 'problem' => $problem];
            }

            // ---- credit the operator's card (same balance the top-up and cashout use) ----
            $card   = Card::whereKey($operatorCard->id)->lockForUpdate()->firstOrFail();
            $before = (float) $card->balance;
            $card->increment('balance', $confirmedPesos);
            $after  = (float) $card->fresh()->balance;

            // The unique reference_no is a second guard against crediting the same payment twice.
            CardTransaction::create([
                'card_id'          => $card->id,
                'source'           => 'rental',
                'reference_no'     => 'RNT-' . str_pad((string) $payment->id, 8, '0', STR_PAD_LEFT),
                'reference_type'   => RentalPayment::class,
                'reference_id'     => $payment->id,
                'transaction_type' => 'rental_earning',
                'amount'           => $confirmedPesos,
                'balance_before'   => $before,
                'balance_after'    => $after,
                'status'           => 'success',
                'message'          => "Rental first payment: {$request->event_name}",
                'transaction_time' => now(),
                'metadata'         => [
                    'rental_request_id' => $request->id,
                    'rental_payment_id' => $payment->id,
                    'payment_method'    => $method,
                ],
            ]);

            $payment->update($common + ['status' => 'paid', 'failure_reason' => null]);

            $request->update([
                'status'       => RentalRequestStatus::Confirmed,
                'confirmed_at' => now(),
            ]);

            // Other open requests for the same dates can no longer be served.
            $others = RentalRequest::where('vehicle_id', $request->vehicle_id)
                ->whereKeyNot($request->id)
                ->whereIn('status', [RentalRequestStatus::Pending->value, RentalRequestStatus::Quoted->value])
                ->overlapping($request->trip_start_at, $request->trip_end_at)
                ->get(['id', 'passenger_id']);

            if ($others->isNotEmpty()) {
                RentalRequest::whereIn('id', $others->pluck('id'))->update([
                    'status'        => RentalRequestStatus::Expired->value,
                    'status_reason' => 'The vehicle was booked by another passenger.',
                ]);
            }

            return [
                'outcome'             => 'confirmed',
                'request'             => $request,
                'amount'              => $confirmedPesos,
                'expired_passengers'  => $others->pluck('passenger_id')->all(),
            ];
        });

        $this->afterCommit($result);

        return $result['outcome'];
    }

    /** "payment.failed" or "payment.expired": keep the payment row honest. */
    public function handleFailedOrExpired(string $sessionId, string $type, ?string $eventId): void
    {
        $expired = $type === 'checkout_session.payment.expired';

        DB::transaction(function () use ($sessionId, $expired, $eventId) {
            $payment = RentalPayment::where('checkout_session_id', $sessionId)->lockForUpdate()->first();

            // Unknown session (not a rental), or already settled: leave it alone.
            if (! $payment || in_array($payment->status, ['paid', 'needs_review'], true)) {
                return;
            }

            $payment->update([
                'status'            => $expired ? 'expired' : 'failed',
                'failure_reason'    => $expired ? 'Checkout session expired unpaid.' : 'Payment failed at PayMongo.',
                'paymongo_event_id' => $eventId,
            ]);
        });
    }

    /** Why a payment cannot confirm its booking, or null when everything checks out. */
    private function problem(RentalPayment $payment, RentalRequest $request, bool $operatorHasCard, ?float $confirmed): ?string
    {
        if ($confirmed === null) {
            return 'The payment message had no confirmable amount.';
        }

        if ((int) round($confirmed * 100) !== (int) round((float) $payment->amount * 100)) {
            return 'Amount mismatch: PayMongo confirmed ' . Peso::format($confirmed) . ' but ' . Peso::format($payment->amount) . ' was expected.';
        }

        if ($request->status !== RentalRequestStatus::AwaitingPayment) {
            return "The request was no longer waiting for payment (status: {$request->status->label()}).";
        }

        if (! $request->hold_expires_at || $request->hold_expires_at->isPast()) {
            return 'The payment arrived after the payment time ended.';
        }

        $taken = RentalRequest::where('vehicle_id', $request->vehicle_id)
            ->whereKeyNot($request->id)
            ->blockingDates()
            ->overlapping($request->trip_start_at, $request->trip_end_at)
            ->exists();

        if ($taken) {
            return 'The dates were taken by another booking.';
        }

        if (! $operatorHasCard) {
            return 'The operator has no card to receive the payment.';
        }

        return null;
    }

    /** Things that must never undo the payment: closing other pages, notifications. */
    private function afterCommit(array $result): void
    {
        try {
            if ($result['outcome'] === 'confirmed') {
                // Close any other open checkout page for this request so it cannot be paid twice.
                $this->checkout->expireOpenSessions($result['request']);

                $this->notifier->bookingConfirmed($result['request'], (float) $result['amount']);

                if (! empty($result['expired_passengers'])) {
                    $this->notifier->expiredByBooking($result['expired_passengers']);
                }
            } elseif ($result['outcome'] === 'needs_review') {
                $this->notifier->needsReview($result['request'], $result['amount'], $result['problem']);
            }
        } catch (\Throwable $e) {
            Log::warning('Rental payment processed but the follow-up step failed', [
                'outcome' => $result['outcome'],
                'error'   => $e->getMessage(),
            ]);
        }
    }
}