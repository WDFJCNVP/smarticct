<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    /** @use HasFactory<\Database\Factories\RouteFactory> */
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'first_trip',
        'last_trip',
        'base_fare',
    ];

    public function vehicle() {
        return $this->belongsTo(Vehicle::class);
    }

    public function terminal() {
        return $this->belongsTo(Terminal::class);
    }
}
