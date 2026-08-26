<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    protected $casts = [
        'metadata' => 'array',
    ];

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'status',
        'metadata'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function postInterest()
    {
        return $this->hasMany(PostInterest::class);
    }

    public function tripRequest()
    {
        return $this->hasMany(TripRequest::class);
    }

    public function rentalOffer()
    {
        return $this->hasMany(RentalOffer::class);
    }
}