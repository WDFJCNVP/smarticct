<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardPricingSetting extends Model
{
    protected $fillable = [
        'price',
        'updated_by',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], ['price' => 50.00]);
    }

    public static function currentPrice(): float
    {
        return (float) static::current()->price;
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
