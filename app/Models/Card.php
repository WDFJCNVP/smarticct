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
        'uid',
        'balance',
    ];

    public function user() {
        return $this->hasOne(User::class);
    }
    
    public function cardTransaction() {
        return $this->hasMany(CardTransaction::class);
    }

    public function topUpTransaction() {
        return $this->hasMany(TopUpTransaction::class);
    }
}
