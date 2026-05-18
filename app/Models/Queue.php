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

    use HasFactory;
}
