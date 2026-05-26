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
        'card_id',
        'vehicle_type',
        'status',
        'destination',
        'plate_number',
        'driver_name',
        'seat_capacity',
        'seat_count',
        'time_queued',
        'time_departed',
        'departs_at',
    ];

    protected function casts(): array
    {
        return [
            'time_queued' => 'datetime',
            'time_departed' => 'datetime',
            'departs_at' => 'datetime', // Good practice to cast this too if you use it later
        ];
    }

    use HasFactory;
}
