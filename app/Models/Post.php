<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{

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
}
