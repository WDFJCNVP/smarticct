<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardReport extends Model
{
    protected $fillable = [
        'user_id',
        'card_id',
        'valid_id_path',
        'reason',
        'description',
        'status',
        'approved_by',
        'new_card_id',
        'rejection_reason',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    public function newCard()
    {
        return $this->belongsTo(Card::class, 'new_card_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}