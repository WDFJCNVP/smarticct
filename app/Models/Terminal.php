<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Terminal extends Model
{
    /** @use HasFactory<\Database\Factories\TerminalFactory> */
    use HasFactory;

    protected $fillable = [
        'route_id',
        'municipality',
    ];

    public function route() {
        return $this->hasOne(Route::class);
    }
}
