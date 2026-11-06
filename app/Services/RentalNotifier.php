<?php

namespace App\Services;

use App\Events\NotificationEvent;
use App\Models\Notification;
use App\Models\RentalRequest;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\Peso;
use Illuminate\Support\Facades\Log;

/**
 * Writes rental notifications the same way the top-up and withdrawal webhooks do
 * (Notification + UserNotification, then a broadcast).
 *
 * Call it AFTER the money transaction has committed and inside a try/catch:
 * a notification problem must never undo a payment.
 */
class RentalNotifier
{
    /** @param list<int|null> $userIds */
    public function send(array $userIds, string $title, string $message, array $metadata = []): void
    {
        $userIds = array_values(array_unique(array_filter($userIds)));

        if ($userIds === []) {
            return;
        }

        $notification = Notification::create([
            'type'     => 'Rental',
            'title'    => $title,
            'message'  => $message,
            'metadata' => $metadata,
        ]);

        foreach ($userIds as $userId) {
            UserNotification::create([
                'notification_id' => $notification->id,
                'user_id'         => $userId,
            ]);
        }

        try {
            broadcast(new NotificationEvent());
        } catch (\Throwable $e) {
            Log::warning('Rental notification saved but broadcast failed', ['error' => $e->getMessage()]);
        }
    }

    /** The first payment arrived and the booking is confirmed. */
    public function bookingConfirmed(RentalRequest $request, float $amount): void
    {
        $request->loadMissing('vehicle');

        $when = $request->trip_start_at->format('M d, Y');
        $meta = ['rental_request_id' => $request->id, 'amount' => $amount];

        $this->send(
            [$request->vehicle->user_id],
            'Booking confirmed',
            "{$request->event_name} on {$when} is booked. " . Peso::format($amount) . ' was credited to your card.',
            $meta,
        );

        $balance = max(0, (float) $request->quoted_total - $amount);

        $this->send(
            [$request->passenger_id],
            'Payment received',
            "Your booking for {$request->event_name} on {$when} is confirmed. Pay the balance of " . Peso::format($balance) . ' in cash to the operator on the trip.',
            $meta,
        );
    }

    /** Money arrived but the booking could not be confirmed. An admin must look at it. */
    public function needsReview(RentalRequest $request, float|int|string|null $amount, string $problem): void
    {
        $meta = ['rental_request_id' => $request->id, 'amount' => $amount, 'problem' => $problem];

        $adminIds = User::where('role', 'admin')->pluck('id')->all();

        $this->send(
            $adminIds,
            'Rental payment needs review',
            Peso::format($amount) . " was paid for rental request #{$request->id}, but it could not be confirmed: {$problem} Please check it in PayMongo and refund if needed.",
            $meta,
        );

        $this->send(
            [$request->passenger_id],
            'Payment received, booking not confirmed',
            'We received your payment but could not confirm the booking automatically. The admin will review it.',
            $meta,
        );
    }

    /** Other passengers' requests that were closed because the vehicle was booked. */
    public function expiredByBooking(array $passengerIds): void
    {
        $this->send(
            $passengerIds,
            'Request expired',
            'The vehicle was booked by another passenger for those dates.',
        );
    }
}