<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    /** @use HasFactory<\Database\Factories\VehicleFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'route_list_id',
        'vehicle_type',
        'plate_number',
        'total_seats',
        'official_record',
        'has_or_cr',
        'or_cr_expiry_date',
        'has_franchise',
        'franchise_expiry_date',
        'driver_name',
    ];

    protected function casts(): array
    {
        return [
            'time_queued'           => 'datetime',
            'time_departed'         => 'datetime',
            'departs_at'            => 'datetime',
            'has_or_cr'             => 'boolean',
            'or_cr_expiry_date'     => 'date',
            'has_franchise'         => 'boolean',
            'franchise_expiry_date' => 'date',
        ];
    }
    public function dailyScheduleSlots()
    {
        return $this->hasMany(DailyScheduleSlot::class);
    }

    public function queue() {
        return $this->hasOne(Queue::class);
    }

    public function todaySlot()
    {
        return $this->hasOne(DailyScheduleSlot::class)
                    ->whereDate('schedule_date', today());
    }
    public function user() {
        return $this->belongsTo(User::class);
    }

    public function route() {
        return $this->hasOne(Route::class);
    }

    public function route_list() {
        return $this->belongsTo(RouteList::class);
    }

    public function vehicle_group() {
        return $this->hasMany(VehicleGroup::class);
    }

    /**
     * Worst-case OR/CR + franchise status for this vehicle: 'expired',
     * 'expiring' (within $warningDays), 'valid', or null if neither
     * document has a date on file. Mirrors the threshold used by
     * OperatorsExport so "expiring soon" means the same thing everywhere
     * in the admin UI.
     */
    public function documentStatus(int $warningDays = 30): ?string
    {
        $dates = array_filter([$this->or_cr_expiry_date, $this->franchise_expiry_date]);

        if (empty($dates)) {
            return null;
        }

        $today = today();
        $statuses = [];

        foreach ($dates as $date) {
            if ($date->lt($today)) {
                $statuses[] = 'expired';
            } elseif ($date->lte($today->copy()->addDays($warningDays))) {
                $statuses[] = 'expiring';
            } else {
                $statuses[] = 'valid';
            }
        }

        if (in_array('expired', $statuses, true)) {
            return 'expired';
        }

        if (in_array('expiring', $statuses, true)) {
            return 'expiring';
        }

        return 'valid';
    }
}