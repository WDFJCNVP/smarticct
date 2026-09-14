<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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