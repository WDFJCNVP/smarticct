<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Queue extends Model
{
    protected $casts = [
    'time_queued' => 'datetime',
    ];

    protected $fillable = [
        'user_id',
        'vehicle_type',
        'vehicle_id',
        'status',
        'destination',
        'plate_number',
        'driver_name',
        'seat_capacity',
        'seat_count',
        'time_queued',
        'time_departed',
        'departs_at',
        'daily_schedule_slot_id',
        'slot_position',
    ];

    protected function casts(): array
    {
        return [
            'time_queued' => 'datetime',
            'time_departed' => 'datetime',
            'departs_at' => 'datetime', // Good practice to cast this too if you use it later
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function dailyScheduleSlot()
    {
        return $this->belongsTo(\App\Models\DailyScheduleSlot::class);
    }

    use HasFactory;
}
