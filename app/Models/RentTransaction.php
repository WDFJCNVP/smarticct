<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentTransaction extends Model
{
    protected $fillable = [
        'post_owner_id',
        'interested_user_id',
        'trip_request_id',
        'rental_offer_id',
        'status',
    ];

    public function post() {
        return $this->belongsTo(Post::class);
    }

    public function tripRequest() {
        return $this->belongsTo(TripRequest::class);
    }

    public function rentalOffer() {
        return $this->belongsTo(RentalOffer::class);
    }
}
