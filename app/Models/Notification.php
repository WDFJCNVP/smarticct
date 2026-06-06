<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'type',
        'title',
        'message',
        'metadata',
    ];

    public function userNotifications()
    {
        return $this->hasMany(UserNotification::class);
    }
}
