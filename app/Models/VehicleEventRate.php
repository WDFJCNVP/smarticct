<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleEventRate extends Model
{
    protected $fillable = [
        'vehicle_id',
        'rental_event_type_id',
        'down_payment_amount',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'down_payment_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(RentalEventType::class, 'rental_event_type_id');
    }

    public function rentalRequests(): HasMany
    {
        return $this->hasMany(RentalRequest::class);
    }

    /** Active rate whose event type is also still active. */
    public function scopeOffered(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereHas('eventType', fn (Builder $q) => $q->where('is_active', true));
    }
}