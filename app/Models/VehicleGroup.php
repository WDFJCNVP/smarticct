<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleGroup extends Model
{
    protected $fillable = ['vehicle_id', 'group_number', 'order_number', 'vehicle_type'];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function dailyScheduleSlot()
    {
        return $this->hasMany(DailySchedule::class);
    }
}
