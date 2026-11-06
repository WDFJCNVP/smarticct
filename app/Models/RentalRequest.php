<?php

namespace App\Models;

use App\Enums\RentalRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class RentalRequest extends Model
{
    protected $fillable = [
        'passenger_id',
        'vehicle_id',
        'vehicle_event_rate_id',
        'event_name',
        'down_payment_amount',
        'quoted_first_payment',
        'pickup_municipality',
        'pickup_barangay',
        'pickup_details',
        'destination_municipality',
        'destination_barangay',
        'destination_details',
        'trip_start_at',
        'trip_end_at',
        'passenger_count',
        'notes',
        'status',
        'status_reason',
        'respond_by',
        'quoted_total',
        'quote_note',
        'quoted_at',
        'quote_expires_at',
        'quote_accepted_at',
        'hold_expires_at',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RentalRequestStatus::class,
            'down_payment_amount' => 'decimal:2',
            'quoted_first_payment' => 'decimal:2',
            'quoted_total' => 'decimal:2',
            'passenger_count' => 'integer',
            'trip_start_at' => 'datetime',
            'trip_end_at' => 'datetime',
            'respond_by' => 'datetime',
            'quoted_at' => 'datetime',
            'quote_expires_at' => 'datetime',
            'quote_accepted_at' => 'datetime',
            'hold_expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function eventRate(): BelongsTo
    {
        return $this->belongsTo(VehicleEventRate::class, 'vehicle_event_rate_id');
    }

    /** Checkout attempts for the first payment (a request can have several). */
    public function payments(): HasMany
    {
        return $this->hasMany(RentalPayment::class);
    }

    /** The operator who owns the requested vehicle. */
    public function operator(): HasOneThrough
    {
        return $this->hasOneThrough(
            User::class,     // related
            Vehicle::class,  // through
            'id',            // vehicles.id        (through key)
            'id',            // users.id           (related key)
            'vehicle_id',    // rental_requests.vehicle_id (local key)
            'user_id'        // vehicles.user_id   (through local key)
        );
    }

    public function scopeForPassenger(Builder $query, User|int $user): Builder
    {
        return $query->where('passenger_id', $user instanceof User ? $user->id : $user);
    }

    /**
     * Requests that currently reserve dates: confirmed bookings, plus holds that
     * are still inside their payment window. An expired hold stops blocking the
     * moment hold_expires_at passes, even before the scheduled job updates its status.
     */
    public function scopeBlockingDates(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('status', RentalRequestStatus::Confirmed->value)
                ->orWhere(function (Builder $q) {
                    $q->where('status', RentalRequestStatus::AwaitingPayment->value)
                        ->where('hold_expires_at', '>', now());
                });
        });
    }

    /** Requests overlapping the given window. */
    public function scopeOverlapping(Builder $query, $from, $to): Builder
    {
        return $query->where('trip_start_at', '<', $to)
            ->where('trip_end_at', '>', $from);
    }
}