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
        'vehicle_type',
        'plate_number',
        'total_seats'
    ];

    protected function casts(): array
    {
        return [
            'time_queued' => 'datetime',
            'time_departed' => 'datetime',
            'departs_at' => 'datetime', // Good practice to cast this too if you use it later
        ];
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function route() {
        return $this->hasOne(Route::class);
    }
}
