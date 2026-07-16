<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentTransaction extends Model
{
    protected $fillable = [
        'operator_id',
        'client_id',
        'post_interest_id',
        'status',
    ];

    public function post() {
        return $this->belongsTo(Post::class);
    }

    public function postInterest() {
        return $this->belongsTo(PostInterest::class);
    }
}
