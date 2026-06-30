<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperatorTicketRate extends Model
{

    protected $fillable = [
        'vehicle_type',
        'queueing_fee',
    ];

    public function routeList() {
        return $this->hasMany(RouteList::class);
    }
}
