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
        'total_seats'
    ];

    protected function casts(): array
    {
        return [
            'time_queued' => 'datetime',
            'time_departed' => 'datetime',
            'departs_at' => 'datetime',
        ];
    }
    public function dailyScheduleSlots()
    {
        return $this->hasMany(DailyScheduleSlot::class);
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
}
