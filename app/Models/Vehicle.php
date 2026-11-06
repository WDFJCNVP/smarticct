<?php

namespace App\Models;

use App\Enums\RentalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'route_list_id',
        'vehicle_type',
        'plate_number',
        'total_seats',
        'engine_number',
        'body_number',
        'chassis_number',
        'has_franchise',
        'franchise_expiry_date',
        'last_notified_status',
    ];

    /**
     * Automatically append virtual attributes to model / JSON serialization.
     */
    protected $appends = [
        'queueing_fee',
        'destination',
    ];

    protected function casts(): array
    {
        return [
            'time_queued'           => 'datetime',
            'time_departed'         => 'datetime',
            'departs_at'            => 'datetime',
            'has_franchise'         => 'boolean',
            'franchise_expiry_date' => 'date',
            'rental_status'         => RentalStatus::class,
        ];
    }

    /**
     * Safely resolve the queueing fee from the linked route list and ticket rate.
     */
    public function getQueueingFeeAttribute(): float
    {
        return (float) ($this->route_list?->operatorTicketRate?->queueing_fee ?? 0.00);
    }

    /**
     * Safely resolve the default terminal/destination.
     */
    public function getDestinationAttribute(): ?string
    {
        return $this->route_list?->terminal;
    }

    public function route_list()
    {
        return $this->belongsTo(RouteList::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function dailyScheduleSlots()
    {
        return $this->hasMany(DailyScheduleSlot::class);
    }

    public function queue()
    {
        return $this->hasOne(Queue::class);
    }

    public function todaySlot()
    {
        return $this->hasOne(DailyScheduleSlot::class)
                    ->whereDate('schedule_date', today());
    }

    public function route()
    {
        return $this->hasOne(Route::class);
    }

    public function vehicle_group()
    {
        return $this->hasMany(VehicleGroup::class);
    }

    // ---------------------------------------------------------------
    // Vehicle rental
    // rental_status is intentionally NOT in $fillable: set it explicitly
    // in the rental service so an operator can never mass-assign a
    // suspended vehicle back to active.
    // ---------------------------------------------------------------

    /** Every event rate this vehicle has (active or not). */
    public function eventRates(): HasMany
    {
        return $this->hasMany(VehicleEventRate::class);
    }

    /** Rates currently offered to passengers. */
    public function offeredEventRates(): HasMany
    {
        return $this->hasMany(VehicleEventRate::class)->offered();
    }

    /** Event types this vehicle can be rented for, with the rate columns on the pivot. */
    public function eventTypes(): BelongsToMany
    {
        return $this->belongsToMany(RentalEventType::class, 'vehicle_event_rates')
            ->withPivot(['id', 'down_payment_amount', 'is_active'])
            ->withTimestamps();
    }

    public function rentalRequests(): HasMany
    {
        return $this->hasMany(RentalRequest::class);
    }

    /** Vehicles shown on the passenger Booking Page. */
    public function scopeRentable(Builder $query): Builder
    {
        return $query->where('rental_status', RentalStatus::Active->value)
            ->whereHas('eventRates', fn (Builder $q) => $q->offered());
    }

    public function documentStatus(int $warningDays = 30): ?string
    {
        $date = $this->franchise_expiry_date;

        if (empty($date)) {
            return null;
        }

        $today = today();

        if ($date->lt($today)) {
            return 'expired';
        }

        if ($date->lte($today->copy()->addDays($warningDays))) {
            return 'expiring';
        }

        return 'valid';
    }
}