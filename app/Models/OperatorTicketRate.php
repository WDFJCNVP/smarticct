<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperatorTicketRate extends Model
{
    public function routeList() {
        return $this->hasMany(RouteList::class);
    }
}
