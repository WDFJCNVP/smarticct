<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyScheduleSlot extends Model
{
   protected $fillable = ['schedule_date', 'vehicle_id', 'slot_position', 'metadata', 'status', 'vehicle_group_id'];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function vehicleGroup()
    {
        return $this->belongsTo(VehicleGroup::class);
    }
}
