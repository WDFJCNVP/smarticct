<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserStatus extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'suspension_reason',
        'suspended_at',
        'suspended_by',
        'is_deleted',
        'deleted_at_by_user',
    ];

    protected function casts(): array
    {
        return [
            'suspended_at'       => 'datetime',
            'is_deleted'         => 'boolean',
            'deleted_at_by_user' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function suspendedBy()
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }
}
