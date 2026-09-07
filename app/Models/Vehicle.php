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
        'engine_number',
        'body_number',
        'chassis_number',
        'has_franchise',
        'franchise_expiry_date',
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