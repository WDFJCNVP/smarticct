<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostInterest extends Model
{   
    protected $casts = [
        'metadata' => 'array',
        'trip_date' => 'date',
    ];

    protected $fillable = [
        'post_id',
        'user_id',
        'purpose',
        'message',
        'body_count',
        'pick_up_location',
        'drop_off_location',
        'trip_date',
        'trip_type',
        'metadata',
        'status',
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
}
