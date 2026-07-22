<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentalOffer extends Model
{
    protected $casts = [
        'metadata' => 'array',
        'available_from' => 'date',
        'available_until' => 'date',
    ];

    protected $fillable = [
        'post_id',
        'user_id',
        'vehicle_id',
        'message',
        'status',
        'destination_coverage',
        'available_from',
        'available_until',
        'metadata',
    ];

    public function rentTransactions()
    {
        return $this->hasMany(RentTransaction::class);
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
