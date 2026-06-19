<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelRecord extends Model
{

    protected $fillable = [
        'user_id',
        'queue_id',

    ];

    public function queue() {
        return $this->belongsTo(Queue::class);
    }
    public function user() {
        return $this->belongsTo(User::class);
    }
}
