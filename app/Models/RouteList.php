<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RouteList extends Model
{
    protected $fillable = [
        'terminal_id',
        'vehicle_type',
        'first_trip',
        'last_trip',
        'base_fare',
        'type',
    ];

    public function terminal() {
        return $this->belongsTo(Terminal::class);
    }

    public function operatorTicketRate() {
        return $this->belongsTo(OperatorTicketRate::class);
    }
}
