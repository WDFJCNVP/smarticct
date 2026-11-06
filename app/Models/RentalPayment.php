<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalPayment extends Model
{
    protected $fillable = [
        'rental_request_id',
        'user_id',
        'checkout_session_id',
        'amount',
        'amount_confirmed',
        'payment_method',
        'status',
        'failure_reason',
        'paymongo_event_id',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_confirmed' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function rentalRequest(): BelongsTo
    {
        return $this->belongsTo(RentalRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}