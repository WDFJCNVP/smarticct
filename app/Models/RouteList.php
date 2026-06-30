<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RouteList extends Model
{
    protected $casts = [
        'metadata' => 'array',
    ];

    protected $fillable = [
        'operator_ticket_rate_id',
        'terminal',
        'first_trip',
        'last_trip',
        'fare',
        'metadata'
    ];

    public function terminal() {
        return $this->belongsTo(Terminal::class);
    }

    public function operatorTicketRate() {
        return $this->belongsTo(OperatorTicketRate::class);
    }
}
