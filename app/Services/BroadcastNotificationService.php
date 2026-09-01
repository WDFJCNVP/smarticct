<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\UserNotification;
use App\Events\NotificationEvent;

class BroadcastNotificationService
{
    /**
     * Send one notification to every active (non-suspended, non-deleted)
     * user in the given roles — e.g. announcing a fare change to every
     * commuter, operator, and cashier at once.
     *
     * Creates a single Notification row and bulk-inserts one UserNotification
     * per recipient, rather than looping with ->create() per user, since this
     * can fan out to hundreds of users at once.
     */
    public function notifyRoles(array $roles, string $type, string $title, string $message, array $metadata = []): int
    {
        $notification = Notification::create([
            'type'     => $type,
            'title'    => $title,
            'message'  => $message,
            'metadata' => $metadata,
        ]);

        $userIds = User::whereIn('role', $roles)
            ->whereDoesntHave('userStatus', function ($q) {
                $q->where('is_deleted', true)->orWhere('status', 'suspended');
            })
            ->pluck('id');

        if ($userIds->isEmpty()) {
            return 0;
        }

        $now = now();
        $rows = $userIds->map(fn ($userId) => [
            'notification_id' => $notification->id,
            'user_id'         => $userId,
            'is_read'         => false,
            'created_at'      => $now,
            'updated_at'      => $now,
        ])->all();

        UserNotification::insert($rows);

        broadcast(new NotificationEvent());

        return count($rows);
    }
}