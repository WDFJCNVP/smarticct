<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $casts = [
        'metadata' => 'array',
    ];

    protected $fillable = [
        'user_id',
        'action',
        'subject',
        'channel',
        'metadata',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
