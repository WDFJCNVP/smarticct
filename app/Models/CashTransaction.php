<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTransaction extends Model
{
    protected $fillable = [
        'processed_by',
        'operator_id',
        'vehicle_id',
        'queue_id',
        'amount',
        'amount_received',
        'change',
        'reference_no',
        'notes',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_received' => 'decimal:2',
        'change' => 'decimal:2',
    ];

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function queue(): BelongsTo
    {
        return $this->belongsTo(Queue::class);
    }
}