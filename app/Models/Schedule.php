<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    /** @use HasFactory<\Database\Factories\ScheduleFactory> */
    use HasFactory;

    public function vehicle() {
        return $this->belongsTo(Vehicle::class);
    }

    public function route() {
        return $this->belongsTo(Route::class);
    }
}
