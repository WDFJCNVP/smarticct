<?php

namespace App\Services;

use App\Enums\RentalRequestStatus;
use App\Models\RentalPayment;
use App\Models\RentalRequest;
use App\Models\User;
use DomainException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Creates and closes PayMongo checkout sessions for a rental's first payment.
 * Mirrors CheckoutSessionService (wallet top-up), but is keyed to a rental request
 * and does not need the passenger to own a card.
 */
class RentalCheckoutService
{
    /**
     * Safety switch. Until the webhook branch that confirms rental payments exists,
     * a real payment would be received but the booking would never be confirmed.
     * Turn on with 'rentals_enabled' => env('PAYMONGO_RENTALS_ENABLED', false)
     * in config/services.php (paymongo section).
     */
    public function isEnabled(): bool
    {
        return (bool) config('services.paymongo.rentals_enabled', false);
    }

    /**
     * Create a checkout session for the request's first payment and return its URL.
     *
     * @throws DomainException with a message that is safe to show to the passenger
     */
    public function createCheckout(RentalRequest $request, User $passenger): string
    {
        if (! $this->isEnabled()) {
            throw new DomainException('Online payment for rentals is not switched on yet. Please try again later.');
        }

        return DB::transaction(function () use ($request, $passenger) {
            $locked = RentalRequest::whereKey($request->id)->lockForUpdate()->with('vehicle.user')->firstOrFail();

            if ($locked->passenger_id !== $passenger->id) {
                throw new DomainException('This request does not belong to you.');
            }

            if ($locked->status !== RentalRequestStatus::AwaitingPayment) {
                throw new DomainException('This request is not waiting for payment.');
            }

            if (! $locked->hold_expires_at || $locked->hold_expires_at->isPast()) {
                throw new DomainException('The payment time for this request has passed.');
            }

            // Money for this request already arrived (paid, or waiting for an admin to review).
            if ($locked->payments()->whereIn('status', ['paid', 'needs_review'])->exists()) {
                throw new DomainException('We already received a payment for this request. Please wait while it is processed.');
            }

            // The operator's card receives the first payment.
            if ($locked->vehicle->user?->card === null) {
                throw new DomainException('The operator cannot receive payments right now. Please try again later.');
            }

            // Only one open checkout session per request, so it cannot be paid twice.
            if (! $this->expireOpenSessions($locked)) {
                throw new DomainException('Your previous payment attempt could not be closed. If you already paid, please wait for the confirmation. Otherwise try again in a moment.');
            }

            // The first payment the operator set in the quote.
            if ($locked->quoted_first_payment === null) {
                throw new DomainException('This request has no first payment yet.');
            }

            $amount        = (float) $locked->quoted_first_payment;
            $amountInCents = (int) round($amount * 100);

            try {
                $response = Http::withBasicAuth(config('services.paymongo.secret_key'), '')
                    ->acceptJson()
                    ->post('https://api.paymongo.com/v1/checkout_sessions', [
                        'data' => [
                            'attributes' => [
                                'line_items' => [
                                    [
                                        'name'        => 'Rental first payment: ' . $locked->event_name,
                                        'amount'      => $amountInCents,
                                        'currency'    => 'PHP',
                                        'quantity'    => 1,
                                        'description' => "Vehicle {$locked->vehicle->plate_number}, trip on {$locked->trip_start_at->format('M d, Y')}",
                                    ],
                                ],
                                'payment_method_types' => ['gcash', 'paymaya', 'card', 'qrph'],
                                'send_email_receipt'   => true,
                                'show_description'     => true,
                                'show_line_items'      => true,
                                'success_url'          => route('my-rental-requests', ['payment' => 'return']),
                                'cancel_url'           => route('my-rental-requests', ['payment' => 'cancelled']),
                            ],
                        ],
                    ]);
            } catch (ConnectionException $e) {
                Log::error('PayMongo rental checkout could not connect', [
                    'rental_request_id' => $locked->id,
                    'error'             => $e->getMessage(),
                ]);

                throw new DomainException('Could not reach the payment provider. Please try again.');
            }

            if ($response->failed()) {
                Log::error('PayMongo rental checkout session creation returned an error', [
                    'rental_request_id' => $locked->id,
                    'status'            => $response->status(),
                    'body'              => $response->body(),
                ]);

                throw new DomainException('We could not start the payment. Please try again.');
            }

            $session = $response->json('data');

            RentalPayment::create([
                'rental_request_id'   => $locked->id,
                'user_id'             => $passenger->id,
                'checkout_session_id' => $session['id'],
                'amount'              => $amount,
                'status'              => 'pending',
            ]);

            return $session['attributes']['checkout_url'];
        });
    }

    /**
     * Close every open checkout session of a request at PayMongo, so it can no longer be paid.
     *
     * @return bool true when no open session is left
     */
    public function expireOpenSessions(RentalRequest $request): bool
    {
        $allClosed = true;

        // A failed attempt can still be retried inside the same checkout page, so it counts as open.
        foreach ($request->payments()->whereIn('status', ['pending', 'failed'])->get() as $payment) {
            try {
                $response = Http::withBasicAuth(config('services.paymongo.secret_key'), '')
                    ->acceptJson()
                    ->post("https://api.paymongo.com/v1/checkout_sessions/{$payment->checkout_session_id}/expire");
            } catch (ConnectionException $e) {
                Log::error('PayMongo could not expire a rental checkout session (connection)', [
                    'rental_payment_id' => $payment->id,
                    'error'             => $e->getMessage(),
                ]);
                $allClosed = false;
                continue;
            }

            if ($response->successful()) {
                $payment->update(['status' => 'expired']);
            } else {
                Log::warning('PayMongo could not expire a rental checkout session', [
                    'rental_payment_id' => $payment->id,
                    'status'            => $response->status(),
                    'body'              => $response->body(),
                ]);
                $allClosed = false;
            }
        }

        return $allClosed;
    }
}