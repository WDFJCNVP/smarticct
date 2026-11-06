<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalEventType extends Model
{
    /** How many services of their own an operator may create. */
    public const MAX_CUSTOM_PER_OPERATOR = 10;

    protected $fillable = ['user_id', 'name', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** The operator who created this service, or null for a standard event. */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function vehicleRates(): HasMany
    {
        return $this->hasMany(VehicleEventRate::class);
    }

    public function isCustom(): bool
    {
        return $this->user_id !== null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Standard events plus the services this operator created. */
    public function scopeAvailableTo(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId) {
            $q->whereNull('user_id')->orWhere('user_id', $userId);
        });
    }
}