<?php

namespace App\Services;

use App\Enums\RentalRequestStatus;
use App\Enums\RentalStatus;
use App\Models\RentalRequest;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleEventRate;
use App\Support\Peso;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RentalRequestService
{
    /** How many requests a passenger may have open at once (pending, quoted, awaiting payment). */
    public const MAX_OPEN_REQUESTS = 5;

    /** How long the operator has to respond to a new request. */
    public const RESPONSE_HOURS = 48;

    /** Longest trip a single request may cover. */
    public const MAX_TRIP_DAYS = 30;

    /** How long a passenger has to accept a quote (never past the trip start). */
    public const QUOTE_VALID_HOURS = 24;

    /** Highest total price that fits the decimal(10,2) columns. */
    public const MAX_TOTAL = 999999.99;

    /** How long a passenger has to pay the first payment after accepting a quote. */
    public const HOLD_HOURS = 24;

    public function __construct(private readonly RentalCheckoutService $checkout)
    {
    }

    /**
     * Create a pending rental request from a passenger.
     *
     * @param  array{pickup_municipality:string, pickup_barangay:?string, pickup_details:string,
     *               destination_municipality:string, destination_barangay:?string, destination_details:string,
     *               trip_start_at:string, trip_end_at:string, passenger_count:int, notes:?string}  $data
     *
     * @throws DomainException with a message that is safe to show to the passenger
     */
    public function create(User $passenger, Vehicle $vehicle, int $rateId, array $data): RentalRequest
    {
        if ($passenger->isSuspended() || $passenger->isDeleted()) {
            throw new DomainException('Your account cannot send rental requests.');
        }

        if ($vehicle->user_id === $passenger->id) {
            throw new DomainException('You cannot rent your own vehicle.');
        }

        $start = Carbon::parse($data['trip_start_at']);
        $end   = Carbon::parse($data['trip_end_at']);

        if ($start->isPast()) {
            throw new DomainException('The trip must start in the future.');
        }

        if ($end->lessThanOrEqualTo($start)) {
            throw new DomainException('The trip must end after it starts.');
        }

        if ($end->greaterThan($start->copy()->addDays(self::MAX_TRIP_DAYS))) {
            throw new DomainException('A single request can cover at most ' . self::MAX_TRIP_DAYS . ' days.');
        }

        return DB::transaction(function () use ($passenger, $vehicle, $rateId, $data, $start, $end) {
            // Lock the vehicle row so the checks below see a consistent picture.
            $locked = Vehicle::whereKey($vehicle->id)->lockForUpdate()->firstOrFail();

            if ($locked->rental_status !== RentalStatus::Active) {
                throw new DomainException('This vehicle is no longer available for rent.');
            }

            if ((int) $data['passenger_count'] > $locked->total_seats) {
                throw new DomainException("This vehicle seats {$locked->total_seats} passengers at most.");
            }

            $rate = VehicleEventRate::offered()
                ->where('vehicle_id', $locked->id)
                ->whereKey($rateId)
                ->with('eventType')
                ->first();

            if (! $rate) {
                throw new DomainException('That event is no longer offered for this vehicle.');
            }

            // Dates already confirmed, or held while another passenger pays.
            $taken = RentalRequest::where('vehicle_id', $locked->id)
                ->blockingDates()
                ->overlapping($start, $end)
                ->exists();

            if ($taken) {
                throw new DomainException('This vehicle is already booked or on hold for those dates. Please choose other dates.');
            }

            $open = [
                RentalRequestStatus::Pending->value,
                RentalRequestStatus::Quoted->value,
                RentalRequestStatus::AwaitingPayment->value,
            ];

            if (RentalRequest::forPassenger($passenger)->whereIn('status', $open)->count() >= self::MAX_OPEN_REQUESTS) {
                throw new DomainException('You already have ' . self::MAX_OPEN_REQUESTS . ' open requests. Wait for a reply or cancel one first.');
            }

            $duplicate = RentalRequest::forPassenger($passenger)
                ->where('vehicle_id', $locked->id)
                ->whereIn('status', $open)
                ->overlapping($start, $end)
                ->exists();

            if ($duplicate) {
                throw new DomainException('You already have an open request for this vehicle on those dates.');
            }

            // The operator's response window never runs past the trip start.
            $respondBy = now()->addHours(self::RESPONSE_HOURS);
            if ($respondBy->greaterThan($start)) {
                $respondBy = $start->copy();
            }

            return RentalRequest::create([
                'passenger_id'          => $passenger->id,
                'vehicle_id'            => $locked->id,
                'vehicle_event_rate_id' => $rate->id,

                // Snapshot of what the passenger saw.
                'event_name'            => $rate->eventType->name,
                'down_payment_amount'   => $rate->down_payment_amount,

                'pickup_municipality'      => trim($data['pickup_municipality']),
                'pickup_barangay'          => filled($data['pickup_barangay'] ?? null) ? trim($data['pickup_barangay']) : null,
                'pickup_details'           => trim($data['pickup_details']),
                'destination_municipality' => trim($data['destination_municipality']),
                'destination_barangay'     => filled($data['destination_barangay'] ?? null) ? trim($data['destination_barangay']) : null,
                'destination_details'      => trim($data['destination_details']),

                'trip_start_at'   => $start,
                'trip_end_at'     => $end,
                'passenger_count' => (int) $data['passenger_count'],
                'notes'           => filled($data['notes'] ?? null) ? trim($data['notes']) : null,

                'status'     => RentalRequestStatus::Pending,
                'respond_by' => $respondBy,
            ]);
        });
    }

    /**
     * The operator declines a pending request.
     *
     * @throws DomainException with a message that is safe to show to the operator
     */
    public function decline(RentalRequest $request, User $operator, ?string $reason = null): RentalRequest
    {
        $expired = false;

        $result = DB::transaction(function () use ($request, $operator, $reason, &$expired) {
            $locked = $this->lockForResponse($request->id, $operator, $expired);

            if ($expired) {
                return $locked;
            }

            $locked->update([
                'status'        => RentalRequestStatus::Declined,
                'status_reason' => filled($reason) ? trim($reason) : null,
            ]);

            return $locked;
        });

        $this->failIfExpired($expired);

        return $result->fresh();
    }

    /**
     * The operator answers a pending request with a quote: the full payment for the trip.
     * The first payment is the fixed down payment the passenger saw when requesting; it is
     * paid online, and the balance (full payment minus first payment) is paid in cash.
     *
     * @throws DomainException with a message that is safe to show to the operator
     */
    public function sendQuote(RentalRequest $request, User $operator, float $total, ?string $note = null): RentalRequest
    {
        $total   = round($total, 2);
        $expired = false;

        $result = DB::transaction(function () use ($request, $operator, $total, $note, &$expired) {
            $locked = $this->lockForResponse($request->id, $operator, $expired);

            if ($expired) {
                return $locked;
            }

            $firstPayment = (float) $locked->down_payment_amount;

            if ($total < $firstPayment) {
                throw new DomainException('The full payment cannot be lower than the first payment (' . Peso::format($firstPayment) . ').');
            }

            if ($total > self::MAX_TOTAL) {
                throw new DomainException('That full payment is too high.');
            }

            // Lock the vehicle too, so the date check below is consistent.
            $vehicle = Vehicle::whereKey($locked->vehicle_id)->lockForUpdate()->firstOrFail();

            if ($vehicle->rental_status === RentalStatus::Suspended) {
                throw new DomainException('Rental for this vehicle was suspended by the admin.');
            }

            // First payments are credited to the operator's card.
            if ($operator->card === null) {
                throw new DomainException('You need an issued card before you can send quotes, because your first payments are credited to it.');
            }

            $taken = RentalRequest::where('vehicle_id', $vehicle->id)
                ->whereKeyNot($locked->id)
                ->blockingDates()
                ->overlapping($locked->trip_start_at, $locked->trip_end_at)
                ->exists();

            if ($taken) {
                throw new DomainException('Those dates are already booked or on hold for another passenger. You can decline this request.');
            }

            // The passenger's window never runs past the trip start.
            $expiresAt = now()->addHours(self::QUOTE_VALID_HOURS);
            if ($expiresAt->greaterThan($locked->trip_start_at)) {
                $expiresAt = $locked->trip_start_at->copy();
            }

            $locked->update([
                'status'               => RentalRequestStatus::Quoted,
                'status_reason'        => null,
                // Locked in now, so later edits to the event's down payment cannot change it.
                'quoted_first_payment' => $firstPayment,
                'quoted_total'         => $total,
                'quote_note'           => filled($note) ? trim($note) : null,
                'quoted_at'            => now(),
                'quote_expires_at'     => $expiresAt,
            ]);

            return $locked;
        });

        $this->failIfExpired($expired);

        return $result->fresh();
    }

    /**
     * The passenger accepts a quote. The dates are held for HOLD_HOURS while they pay.
     *
     * @throws DomainException with a message that is safe to show to the passenger
     */
    public function acceptQuote(RentalRequest $request, User $passenger): RentalRequest
    {
        $problem = null;

        $result = DB::transaction(function () use ($request, $passenger, &$problem) {
            $locked = $this->lockForPassenger($request->id, $passenger);

            if ($locked->status !== RentalRequestStatus::Quoted) {
                throw new DomainException('This quote is no longer open.');
            }

            if (($locked->quote_expires_at && $locked->quote_expires_at->isPast()) || $locked->trip_start_at->isPast()) {
                $locked->update([
                    'status'        => RentalRequestStatus::Expired,
                    'status_reason' => 'The quote expired before it was accepted.',
                ]);
                $problem = 'This quote has expired. You can send a new request.';

                return $locked;
            }

            // Lock the vehicle so two passengers cannot take the same dates.
            $vehicle = Vehicle::whereKey($locked->vehicle_id)->lockForUpdate()->firstOrFail();

            if ($vehicle->rental_status === RentalStatus::Suspended) {
                throw new DomainException('Rental for this vehicle was suspended by the admin.');
            }

            if ($vehicle->user?->card === null) {
                throw new DomainException('The operator cannot receive payments right now. Please try again later.');
            }

            $taken = RentalRequest::where('vehicle_id', $vehicle->id)
                ->whereKeyNot($locked->id)
                ->blockingDates()
                ->overlapping($locked->trip_start_at, $locked->trip_end_at)
                ->exists();

            if ($taken) {
                $locked->update([
                    'status'        => RentalRequestStatus::Expired,
                    'status_reason' => 'The dates were booked by another passenger.',
                ]);
                $problem = 'Sorry, another passenger just booked those dates. Please choose other dates.';

                return $locked;
            }

            // The payment window never runs past the trip start.
            $holdUntil = now()->addHours(self::HOLD_HOURS);
            if ($holdUntil->greaterThan($locked->trip_start_at)) {
                $holdUntil = $locked->trip_start_at->copy();
            }

            $locked->update([
                'status'            => RentalRequestStatus::AwaitingPayment,
                'quote_accepted_at' => now(),
                'hold_expires_at'   => $holdUntil,
            ]);

            return $locked;
        });

        // Thrown after the transaction so the "expired" update is not rolled back.
        if ($problem) {
            throw new DomainException($problem);
        }

        return $result->fresh();
    }

    /**
     * The passenger turns down a quote.
     *
     * @throws DomainException with a message that is safe to show to the passenger
     */
    public function rejectQuote(RentalRequest $request, User $passenger): RentalRequest
    {
        return DB::transaction(function () use ($request, $passenger) {
            $locked = $this->lockForPassenger($request->id, $passenger);

            if ($locked->status !== RentalRequestStatus::Quoted) {
                throw new DomainException('This quote is no longer open.');
            }

            $locked->update([
                'status'        => RentalRequestStatus::QuoteRejected,
                'status_reason' => 'The passenger declined the quote.',
            ]);

            return $locked->fresh();
        });
    }

    /**
     * The passenger cancels a request that has not been paid yet
     * (pending, or accepted and waiting for the first payment).
     *
     * @throws DomainException with a message that is safe to show to the passenger
     */
    public function cancel(RentalRequest $request, User $passenger): RentalRequest
    {
        $wasAwaitingPayment = false;

        $result = DB::transaction(function () use ($request, $passenger, &$wasAwaitingPayment) {
            $locked = $this->lockForPassenger($request->id, $passenger);

            if (! in_array($locked->status, [RentalRequestStatus::Pending, RentalRequestStatus::AwaitingPayment], true)) {
                throw new DomainException('This request can no longer be cancelled.');
            }

            $wasAwaitingPayment = $locked->status === RentalRequestStatus::AwaitingPayment;

            $locked->update([
                'status'        => RentalRequestStatus::Cancelled,
                'status_reason' => 'Cancelled by the passenger.',
                'cancelled_at'  => now(),
            ]);

            return $locked;
        });

        // Close any open payment page, outside the transaction (it calls PayMongo).
        if ($wasAwaitingPayment) {
            $this->checkout->expireOpenSessions($result);
        }

        return $result->fresh();
    }

    private function lockForPassenger(int $requestId, User $passenger): RentalRequest
    {
        $locked = RentalRequest::whereKey($requestId)->lockForUpdate()->firstOrFail();

        if ($locked->passenger_id !== $passenger->id) {
            throw new DomainException('This request does not belong to you.');
        }

        return $locked;
    }

    /**
     * Load the request with a row lock and make sure this operator may answer it.
     * If its response window has passed, it is marked expired (and $expired is set)
     * instead of being answered.
     */
    private function lockForResponse(int $requestId, User $operator, bool &$expired): RentalRequest
    {
        $locked = RentalRequest::whereKey($requestId)->lockForUpdate()->with('vehicle')->firstOrFail();

        if ($locked->vehicle->user_id !== $operator->id) {
            throw new DomainException('You cannot respond to this request.');
        }

        if ($locked->status !== RentalRequestStatus::Pending) {
            throw new DomainException('This request was already answered or is no longer open.');
        }

        $late = ($locked->respond_by && $locked->respond_by->isPast()) || $locked->trip_start_at->isPast();

        if ($late) {
            $locked->update([
                'status'        => RentalRequestStatus::Expired,
                'status_reason' => 'The operator did not respond in time.',
            ]);
            $expired = true;
        }

        return $locked;
    }

    /** Thrown after the transaction so the "expired" update is not rolled back. */
    private function failIfExpired(bool $expired): void
    {
        if ($expired) {
            throw new DomainException('This request has expired because the response time has passed.');
        }
    }
}