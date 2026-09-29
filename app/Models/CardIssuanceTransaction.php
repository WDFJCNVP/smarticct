<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardIssuanceTransaction extends Model
{
    protected $fillable = [
        'card_id',
        'user_id',
        'processed_by',
        'issuance_type',
        'amount',
        'amount_received',
        'change',
        'reference_no',
        'notes',
        'status',
    ];

    protected $casts = [
        'amount'          => 'decimal:2',
        'amount_received' => 'decimal:2',
        'change'          => 'decimal:2',
    ];

    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
