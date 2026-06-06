<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    /** @use HasFactory<\Database\Factories\CardFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'card_number',
        'uid',
        'balance',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }
    
    public function cardTransaction() {
        return $this->hasMany(CardTransaction::class);
    }

    public function topUpTransaction() {
        return $this->hasMany(TopUpTransaction::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Card $card) {
            $terminalCode = str_pad(config('app.terminal_code'), 4, '0', STR_PAD_LEFT);
            
            $typeMap = [
                'commuter' => '1001',
                'operator'  => '1002',
                'admin'     => '1003',
            ];
            $type = $typeMap[$card->user->role] ?? '1001';
            
            $year = now()->year; 
            
            $latest = static::max('id') ?? 0;
            $sequence = str_pad($latest + 1, 4, '0', STR_PAD_LEFT);

            $card->card_number = "{$terminalCode}-{$type}-{$year}-{$sequence}";
        });
    }
}
