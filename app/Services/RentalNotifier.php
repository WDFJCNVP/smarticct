<?php

namespace App\Services;

use App\Events\NotificationEvent;
use App\Models\Notification;
use App\Models\RentalRequest;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\Peso;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Every rental notification lives here: who is told, the title, and the message.
 *
 * A message is a short first line that says what happened, then one line per detail
 * (request number, service, vehicle, route, trip dates, amounts), then a closing line.
 *   - On the notification page the lines are shown one under the other.
 *   - In the bell and in the list, the lines run together and are cut to fit.
 * So the first line always carries the main news.
 *
 * Written the same way as the top-up and withdrawal notifications
 * (Notification + UserNotification, then a broadcast).
 *
 * Call these AFTER the money or status transaction has committed, inside a try/catch:
 * a notification problem must never undo a payment or a status change.
 */
class RentalNotifier
{
    /** notifications.message is a TEXT column, so this is only a sanity guard. */
    public const MESSAGE_LIMIT = 2000;

    // ------------------------------------------------------------------ passenger -> operator

    /** 1. The passenger sent a request. */
    public function newRequest(RentalRequest $r): void
    {
        $r = $this->prepare($r);

        $this->send(
            [$this->operatorId($r)],
            'New rental request',
            $this->compose(
                "{$this->passenger($r)} sent you a rental request.",
                [
                    ...$this->common($r),
                    'Passengers'   => (string) $r->passenger_count,
                    'Down payment' => Peso::format($r->down_payment_amount),
                    ...$this->notesLine($r),
                ],
                $r->respond_by ? 'Please reply by ' . $r->respond_by->format('M d, g:i A') . '.' : null,
            ),
            $this->meta($r, 'request_sent'),
        );
    }

    /** 4. The passenger accepted the quote. */
    public function quoteAccepted(RentalRequest $r): void
    {
        $r = $this->prepare($r);

        $this->send(
            [$this->operatorId($r)],
            'Quote accepted',
            $this->compose(
                "{$this->passenger($r)} accepted your quote.",
                [
                    ...$this->common($r),
                    'Full payment' => Peso::format($r->quoted_total),
                    'Down payment' => Peso::format($r->quoted_first_payment) . ' (waiting for payment)',
                ],
                $r->hold_expires_at ? 'The dates are held until ' . $r->hold_expires_at->format('M d, g:i A') . ' while the down payment is paid.' : null,
            ),
            $this->meta($r, 'quote_accepted'),
        );
    }

    /** 5. The passenger declined the quote. */
    public function quoteDeclined(RentalRequest $r): void
    {
        $r = $this->prepare($r);

        $this->send(
            [$this->operatorId($r)],
            'Quote declined',
            $this->compose(
                "{$this->passenger($r)} declined your quote.",
                [...$this->common($r), 'Full payment' => Peso::format($r->quoted_total)],
                'The request is closed.',
            ),
            $this->meta($r, 'quote_declined'),
        );
    }

    /** The passenger cancelled before paying. */
    public function requestCancelled(RentalRequest $r, bool $heldDates): void
    {
        $r = $this->prepare($r);

        $this->send(
            [$this->operatorId($r)],
            'Request cancelled',
            $this->compose(
                "{$this->passenger($r)} cancelled the request.",
                $this->common($r),
                $heldDates ? 'The dates that were held are free again.' : null,
            ),
            $this->meta($r, 'request_cancelled'),
        );
    }

    // ------------------------------------------------------------------ operator -> passenger

    /** 2. The operator sent a quote. */
    public function quoteSent(RentalRequest $r): void
    {
        $r = $this->prepare($r);

        $balance = max(0, (float) $r->quoted_total - (float) $r->quoted_first_payment);

        $this->send(
            [$r->passenger_id],
            'Quote received',
            $this->compose(
                "{$this->operator($r)} sent you a quote.",
                [
                    ...$this->common($r),
                    'Full payment'               => Peso::format($r->quoted_total),
                    'Down payment (pay online)'  => Peso::format($r->quoted_first_payment),
                    'Balance (cash on the trip)' => Peso::format($balance),
                    ...(filled($r->quote_note) ? ['Note' => $r->quote_note] : []),
                ],
                $r->quote_expires_at ? 'Please answer before ' . $r->quote_expires_at->format('M d, g:i A') . '.' : null,
            ),
            $this->meta($r, 'quote_sent'),
        );
    }

    /** 3. The operator declined the request. */
    public function requestDeclined(RentalRequest $r): void
    {
        $r = $this->prepare($r);

        $this->send(
            [$r->passenger_id],
            'Request declined',
            $this->compose(
                "{$this->operator($r)} declined your request.",
                [
                    ...$this->common($r),
                    ...(filled($r->status_reason) ? ['Reason' => $r->status_reason] : []),
                ],
                'You can send a new request for other dates or another vehicle.',
            ),
            $this->meta($r, 'request_declined'),
        );
    }

    // ------------------------------------------------------------------ payment

    /** 6. The down payment arrived and the booking is confirmed. */
    public function bookingConfirmed(RentalRequest $r, float $amount): void
    {
        $r = $this->prepare($r);

        $balance = Peso::format(max(0, (float) $r->quoted_total - $amount));
        $meta    = $this->meta($r, 'payment_received', ['amount' => $amount]);

        $this->send(
            [$this->operatorId($r)],
            'Down payment received',
            $this->compose(
                "{$this->passenger($r)} paid the down payment. The booking is confirmed.",
                [
                    ...$this->common($r),
                    'Down payment received'      => Peso::format($amount) . ' (credited to your card)',
                    'Full payment'               => Peso::format($r->quoted_total),
                    'Balance (cash on the trip)' => $balance,
                ],
            ),
            $meta,
        );

        $this->send(
            [$r->passenger_id],
            'Booking confirmed',
            $this->compose(
                'Your down payment was received. Your booking is confirmed.',
                [
                    ...$this->common($r),
                    'Operator'     => $this->operator($r),
                    'Down payment' => Peso::format($amount) . ' (paid)',
                    'Full payment' => Peso::format($r->quoted_total),
                ],
                "Please pay the balance of {$balance} in cash to the operator on the trip.",
            ),
            $meta,
        );
    }

    /** Money arrived but the booking could not be confirmed. An admin must look at it. */
    public function needsReview(RentalRequest $r, float|int|string|null $amount, string $problem): void
    {
        $r = $this->prepare($r);

        $meta = $this->meta($r, 'payment_needs_review', ['amount' => $amount, 'problem' => $problem]);

        $this->send(
            User::where('role', 'admin')->pluck('id')->all(),
            'Rental payment needs review',
            $this->compose(
                'A rental payment was received but could not be confirmed.',
                [
                    'Amount paid' => Peso::format($amount),
                    'Passenger'   => $this->passenger($r),
                    ...$this->common($r),
                    'Problem'     => $problem,
                ],
                'Please check it in PayMongo and refund it if needed.',
            ),
            $meta,
        );

        $this->send(
            [$r->passenger_id],
            'Payment received, booking not confirmed',
            $this->compose(
                'We received your payment but could not confirm the booking automatically.',
                [...$this->common($r), 'Amount paid' => Peso::format($amount)],
                'The admin will review it.',
            ),
            $meta,
        );
    }

    // ------------------------------------------------------------------ expiry

    /**
     * 7. A time limit passed. Both sides are told that the request is now Expired.
     *
     * @param  string  $stage  pending (operator did not reply) | quoted (passenger did not accept) | payment (passenger did not pay)
     */
    public function requestExpired(RentalRequest $r, string $stage): void
    {
        $r = $this->prepare($r);

        $meta   = $this->meta($r, 'request_expired', ['stage' => $stage]);
        $common = $this->common($r);
        $ref    = $this->ref($r);

        if ($stage === 'quoted') {
            $this->send([$r->passenger_id], 'Quote expired',
                $this->compose("{$ref} has expired because the quote was not accepted in time.",
                    [...$common, 'Full payment' => Peso::format($r->quoted_total)],
                    'You can send a new request.'),
                $meta);

            $this->send([$this->operatorId($r)], 'Quote expired',
                $this->compose("{$ref} has expired because {$this->passenger($r)} did not accept your quote in time.",
                    [...$common, 'Full payment' => Peso::format($r->quoted_total)]),
                $meta);

            return;
        }

        if ($stage === 'payment') {
            $down = Peso::format($r->quoted_first_payment ?? $r->down_payment_amount);

            $this->send([$this->operatorId($r)], 'Booking not paid',
                $this->compose("{$ref} has expired because the down payment was not paid on time.",
                    [
                        'Passenger' => $this->passenger($r),
                        ...$common,
                        'Down payment' => "{$down} (not received)",
                    ],
                    'The dates are free again.'),
                $meta);

            $this->send([$r->passenger_id], 'Payment time passed',
                $this->compose("{$ref} has expired because the down payment was not paid on time.",
                    [...$common, 'Down payment' => "{$down} (not received)"],
                    'The dates were released. You can send a new request.'),
                $meta);

            return;
        }

        // pending: the operator never replied
        $this->send([$r->passenger_id], 'Request expired',
            $this->compose("{$ref} has expired because {$this->operator($r)} did not reply in time.",
                $common,
                'You can send a new request.'),
            $meta);

        $this->send([$this->operatorId($r)], 'Request expired',
            $this->compose("{$ref} from {$this->passenger($r)} has expired because you did not reply in time.", $common),
            $meta);
    }

    /** Another passenger paid first, so this passenger's open request can no longer be served. */
    public function lostToAnotherBooking(RentalRequest $r): void
    {
        $r = $this->prepare($r);

        $this->send(
            [$r->passenger_id],
            'Request expired',
            $this->compose(
                "{$this->ref($r)} has expired because another passenger booked this vehicle for those dates.",
                $this->common($r),
                'You can send a new request for other dates.',
            ),
            $this->meta($r, 'lost_to_other_booking'),
        );
    }

    // ------------------------------------------------------------------ sending

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
            'message'  => Str::limit($message, self::MESSAGE_LIMIT, '…'),
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

    // ------------------------------------------------------------------ wording helpers

    private function prepare(RentalRequest $r): RentalRequest
    {
        return $r->loadMissing(['vehicle.user', 'passenger']);
    }

    /**
     * The number shown in every message ("#12"). When a transaction number is added later,
     * change it here and every notification follows.
     */
    private function number(RentalRequest $r): string
    {
        return "#{$r->id}";
    }

    private function ref(RentalRequest $r): string
    {
        return 'Request ' . $this->number($r);
    }

    /** First line, detail lines, closing line. */
    private function compose(string $lead, array $details, ?string $closing = null): string
    {
        $lines = [$lead];

        foreach ($details as $label => $value) {
            if (filled($value)) {
                $lines[] = "{$label}: {$value}";
            }
        }

        if ($closing) {
            $lines[] = $closing;
        }

        return implode("\n", $lines);
    }

    /** Which request, which service, which vehicle, which route, which dates. */
    private function common(RentalRequest $r): array
    {
        $v = $r->vehicle;

        return [
            'Request' => $this->number($r),
            'Service' => $r->event_name,
            'Vehicle' => "{$v->plate_number} ({$v->vehicle_type})",
            'From'    => $this->place($r->pickup_municipality, $r->pickup_barangay, $r->pickup_details),
            'To'      => $this->place($r->destination_municipality, $r->destination_barangay, $r->destination_details),
            'Trip'    => $this->when($r),
        ];
    }

    private function notesLine(RentalRequest $r): array
    {
        return filled($r->notes) ? ['Notes' => Str::limit($r->notes, 200)] : [];
    }

    /** "Iriga City, Brgy. San Roque, in front of the church" */
    private function place(?string $municipality, ?string $barangay, ?string $details): string
    {
        return collect([$municipality, filled($barangay) ? "Brgy. {$barangay}" : null, $details])
            ->filter()
            ->implode(', ');
    }

    private function when(RentalRequest $r): string
    {
        $fmt = fn (Carbon $d) => $d->format($d->isCurrentYear() ? 'M d, g:i A' : 'M d, Y g:i A');

        return $r->trip_start_at->isSameDay($r->trip_end_at)
            ? $fmt($r->trip_start_at) . ' – ' . $r->trip_end_at->format('g:i A')
            : $fmt($r->trip_start_at) . ' – ' . $fmt($r->trip_end_at);
    }

    private function passenger(RentalRequest $r): string
    {
        $p = $r->passenger;

        return ($p && ! $p->isDeleted()) ? $p->name : 'A passenger';
    }

    private function operator(RentalRequest $r): string
    {
        return $r->vehicle->user?->name ?? 'The operator';
    }

    private function operatorId(RentalRequest $r): ?int
    {
        return $r->vehicle->user_id;
    }

    /** Everything the message says, in structured form (the notification page uses rental_request_id for its link). */
    private function meta(RentalRequest $r, string $event, array $extra = []): array
    {
        return array_merge([
            'event'             => $event,
            'rental_request_id' => $r->id,
            'reference'         => $this->ref($r),
            'status'            => $r->status->value,
            'vehicle_id'        => $r->vehicle_id,
            'plate_number'      => $r->vehicle->plate_number,
            'service'           => $r->event_name,
            'pickup'            => $this->place($r->pickup_municipality, $r->pickup_barangay, $r->pickup_details),
            'destination'       => $this->place($r->destination_municipality, $r->destination_barangay, $r->destination_details),
            'trip_start'        => $r->trip_start_at->toDateTimeString(),
            'trip_end'          => $r->trip_end_at->toDateTimeString(),
            'passenger_count'   => $r->passenger_count,
            'down_payment'      => $r->quoted_first_payment ?? $r->down_payment_amount,
            'full_payment'      => $r->quoted_total,
        ], $extra);
    }
}