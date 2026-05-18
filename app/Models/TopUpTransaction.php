<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopUpTransaction extends Model
{

    protected $fillable = [
        'id',
        'user_id',
        'card_id',
        'checkout_session_id',
        'points_to_load',
        'amount_paid',
        'payment_method',
        'status',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function card() {
        return $this->belongsTo(Card::class);
    }
}
