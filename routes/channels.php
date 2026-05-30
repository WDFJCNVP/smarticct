<?php
use Illuminate\Support\Facades\Broadcast;
use App\Models\User;

Broadcast::channel('user-info-updated.{userId}', function (User $user, int $userId) {
    return (int) $user->id === (int) $userId;
});
