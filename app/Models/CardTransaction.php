<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CardTransaction extends Model
{
    /** @use HasFactory<\Database\Factories\CardTransactionFactory> */
    use HasFactory;

protected $fillable = [
    'card_id',
    'transaction_type',
    'amount',
    'balance_before',
    'balance_after',
    'device_id',
    'location',
    'status',
    'message',
    'transaction_time',
    'points_deducted',
];

    public function card() {
        return $this->belongsTo(Card::class);
    }
}
