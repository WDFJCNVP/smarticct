<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentTransaction extends Model
{
    protected $fillable = [
        'operator_id',
        'client_id',
        'post_interest_id',
        'status',
    ];
}
