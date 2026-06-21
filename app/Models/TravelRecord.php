<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelRecord extends Model
{

    protected $fillable = [
        'user_id',
        'queue_id',
        'card_transaction_id',
        'destination',
        'vehicle_type',
        'plate_number',
        'driver_name',
        'commuter_type',
        'fare_amount',
        'departed_at',

    ];

    public function queue() {
        return $this->belongsTo(Queue::class);
    }
    public function user() {
        return $this->belongsTo(User::class);
    }
}
