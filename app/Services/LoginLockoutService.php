<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginLockoutService
{
    public const MAX_ATTEMPTS = 5;

    // 1 min, 5 min, 30 min, 24 hours. One more round of failures after this = blocked.
    public const LOCKOUT_SECONDS = [60, 300, 1800, 86400];

    private function key(string $email): string
    {
        return 'login:' . Str::lower($email);
    }

    public function isBlocked(User $user): bool
    {
        return $user->login_blocked_at !== null;
    }

    public function secondsLocked(User $user): int
    {
        if (! $user->login_locked_until || $user->login_locked_until->isPast()) {
            return 0;
        }

        return $user->login_locked_until->timestamp - now()->timestamp;
    }

    /**
     * Call after a failed credential check.
     * Returns ['status' => 'none'|'locked'|'blocked', 'seconds' => int]
     */
    public function recordFailure(User $user): array
    {
        $key = $this->key($user->email_address);

        RateLimiter::hit($key, 900); // count failures within a 15-minute window

        if (RateLimiter::attempts($key) < self::MAX_ATTEMPTS) {
            return ['status' => 'none', 'seconds' => 0];
        }

        RateLimiter::clear($key); // start a fresh round of 5 after each penalty

        $level = $user->login_lockout_level;

        if ($level >= count(self::LOCKOUT_SECONDS)) {
            $user->forceFill(['login_blocked_at' => now()])->save();

            return ['status' => 'blocked', 'seconds' => 0];
        }

        $seconds = self::LOCKOUT_SECONDS[$level];

        $user->forceFill([
            'login_lockout_level' => $level + 1,
            'login_locked_until' => now()->addSeconds($seconds),
        ])->save();

        return ['status' => 'locked', 'seconds' => $seconds];
    }

    /** Call after a fully successful login. */
    public function reset(User $user): void
    {
        RateLimiter::clear($this->key($user->email_address));

        $user->forceFill([
            'login_lockout_level' => 0,
            'login_locked_until' => null,
        ])->save();
    }

    /** Admin action. */
    public function unblock(User $user, User $admin): void
    {
        $this->reset($user);

        $user->forceFill(['login_blocked_at' => null])->save();

        app(AuditLogsService::class)->create([
            'user_id' => $admin->id,
            'action' => 'login_unblocked',
            'subject' => "Account unblocked - {$user->email_address}",
            'channel' => 'Web',
            'metadata' => json_encode([
                'message' => 'Account unblocked by admin after repeated failed login attempts.',
            ]),
        ]);
    }
}