<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostInterest extends Model
{   
    protected $casts = [
        'metadata' => 'array',
    ];

    protected $fillable = [
        'post_id',
        'user_id',
        'purpose',
        'body_count',
        'pick_up_location',
        'drop_off_location',
        'trip_date',
        'metadata',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
