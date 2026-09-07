<?php

namespace App\Services;

use App\Models\User;
use App\Models\VehicleGroup;
use App\Models\RouteList;
use App\Models\Notification;
use App\Models\UserNotification;
use App\Models\Post;
use App\Models\RentTransaction;
use App\Models\CardTransaction;
use App\Events\NotificationEvent;
use App\Services\QueueManagementService;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{

    private function getAdmin() {
        return User::where('role', 'admin')->first();
    }

    public function create( array $userBasicInformation, array $cardInformation = null, ?array $vehicles = null,): User {

        return DB::transaction(function () use ($userBasicInformation, $cardInformation, $vehicles) {

            $userBasicInformation['password'] = Hash::make($userBasicInformation['password']);

            $user = User::create($userBasicInformation);

            if ($cardInformation) {
                $user->card()->create($cardInformation);
            }

            if($this->getAdmin()) {
                $admin = $this->getAdmin();
                $notification = Notification::create([
                    'type'    => 'Registration',
                    'title'   => 'User Registration',
                    'message' => "You have successfully registered a new user. You can manage and monitor the user's details on the Users page. User ID: {$user->user_code}"
                ]);

                UserNotification::create([
                    'notification_id' => $notification->id,
                    'user_id' => $admin->id,
                ]);

            }

            broadcast(new NotificationEvent());

            $created_vehicle;

            if ($userBasicInformation['role'] === 'operator' && !empty($vehicles)) {
                foreach ($vehicles as $vehicle) {

                    $route_list = RouteList::whereHas('operatorTicketRate', function($q) use($vehicle) {
                        $q->where('vehicle_type', $vehicle['vehicle_type']);
                    })->where('terminal', $vehicle['route'])->first();

                    $created_vehicle = $user->vehicles()->create([
                        'route_list_id'          => $route_list->id,
                        'vehicle_type'           => $vehicle['vehicle_type'],
                        'plate_number'           => $vehicle['plate_number'],
                        'total_seats'            => $vehicle['seat_capacity'],
                        'engine_number'          => $vehicle['engine_number'] ?? null,
                        'body_number'            => $vehicle['body_number'] ?? null,
                        'chassis_number'         => $vehicle['chassis_number'] ?? null,
                        'has_franchise'          => $vehicle['has_franchise'] ?? false,
                        'franchise_expiry_date'  => $vehicle['franchise_expiry_date'] ?? null,
                    ]);

                    if ($vehicle['group_number'] !== null) {
                        $order_number = VehicleGroup::where('group_number', $vehicle['group_number'])
                            ->whereHas('vehicle', function($query) use ($created_vehicle) {

                            $query->where('vehicle_type', $created_vehicle->vehicle_type);
                            
                        })->max('order_number') + 1;

                        $created_vehicle->vehicle_group()->create([
                            'group_number' => (int) $vehicle['group_number'],
                            'order_number' => $order_number,
                        ]);
                    }
                }
            }

            app(AuditLogsService::class)->create([
                'user_id' => auth()->id(),
                'action'  => 'User Created',
                'subject' => 'User Account Creation',
                'channel' => 'Web',
                'metadata' => [
                    'ip_address' => request()->ip(),
                    'message'    => "A new user account was successfully created (User ID: {$user->user_code}).",
                ],
            ]);
            return $user;
        });
    }

    public function update(User $user, array $data, bool $byAdmin = true): User
    {
        return DB::transaction(function () use ($user, $data, $byAdmin) {
            $user->update($data);

            // Only notify the admin, and attribute the change to "an administrator",
            // when an admin actually performed the update. Self-service updates
            // (e.g. a commuter/operator completing their own registration/profile)
            // must not trigger the admin-facing or admin-attributed notifications.
            if ($byAdmin && $this->getAdmin()) {
                $admin = $this->getAdmin();
                $notification = Notification::create([
                    'type'    => 'Update',
                    'title'   => 'User Updated',
                    'message' => "You have successfully modified the account details for {$user->name} ({$user->username}). Fields modified: " . implode(', ', array_keys($data))                
                    ]);

                UserNotification::create([
                    'notification_id' => $notification->id,
                    'user_id' => $admin->id,
                ]);

            }

            if ($byAdmin) {
                $notification = Notification::create([
                    'type'    => 'Update',
                    'title'   => 'Profile Updated',
                    'message' => 'Your account details were successfully updated by an administrator on ' . now()->format('F d, Y') . '.',
                ]);
            } else {
                $notification = Notification::create([
                    'type'    => 'Update',
                    'title'   => 'Profile Updated',
                    'message' => 'Your account details were successfully updated on ' . now()->format('F d, Y') . '.',
                ]);
            }

            UserNotification::create([
                'notification_id' => $notification->id,
                'user_id' => $user->id,
            ]);

            broadcast(new NotificationEvent());



        app(AuditLogsService::class)->create([
            'user_id' => auth()->id(),
            'action'  => 'User Updated',
            'subject' => 'User Account Update',
            'channel' => 'Web',
            'metadata' => [
                'ip_address' => request()->ip(),
                'message'    => "User account information was successfully updated (User ID: {$user->user_code}).",
            ],
        ]);

            return $user;
        });
    }

    public function suspend(User $user, string $reason): User
    {
        return DB::transaction(function () use ($user, $reason) {
            if ($user->isDeleted()) {
                throw ValidationException::withMessages([
                    'user' => 'This account has already been permanently deleted and cannot be suspended.',
                ]);
            }

            $admin = auth()->user();

            $user->userStatus()->updateOrCreate([], [
                'status'             => 'suspended',
                'suspension_reason'  => $reason,
                'suspended_at'       => now(),
                'suspended_by'       => $admin?->id,
            ]);

            if ($user->card) {
                $user->card->update(['status' => 'suspended']);
            }

            $notification = Notification::create([
                'type'    => 'Suspension',
                'title'   => 'Account Suspended',
                'message' => "Your account has been suspended. Reason: {$reason} Please visit the terminal office with the required documents to have your account reviewed.",
            ]);

            UserNotification::create([
                'notification_id' => $notification->id,
                'user_id'         => $user->id,
            ]);

            broadcast(new NotificationEvent());

            app(AuditLogsService::class)->create([
                'user_id' => auth()->id(),
                'action'  => 'User Suspended',
                'subject' => 'User Account Suspension',
                'channel' => 'Web',
                'metadata' => [
                    'ip_address' => request()->ip(),
                    'message'    => "User account was suspended (User ID: {$user->user_code}). Reason: {$reason}",
                ],
            ]);

            return $user;
        });
    }

    public function reinstate(User $user): User
    {
        return DB::transaction(function () use ($user) {
            if ($user->isDeleted()) {
                throw ValidationException::withMessages([
                    'user' => 'This account has been permanently deleted and cannot be reinstated.',
                ]);
            }

            $user->userStatus()->updateOrCreate([], [
                'status'            => 'active',
                'suspension_reason' => null,
                'suspended_at'      => null,
                'suspended_by'      => null,
            ]);

            if ($user->card) {
                $user->card->update(['status' => 'active']);
            }

            $notification = Notification::create([
                'type'    => 'Reinstatement',
                'title'   => 'Account Reinstated',
                'message' => 'Your account has been reviewed and reinstated. You may now log in and use the system as normal.',
            ]);

            UserNotification::create([
                'notification_id' => $notification->id,
                'user_id'         => $user->id,
            ]);

            broadcast(new NotificationEvent());

            app(AuditLogsService::class)->create([
                'user_id' => auth()->id(),
                'action'  => 'User Reinstated',
                'subject' => 'User Account Reinstatement',
                'channel' => 'Web',
                'metadata' => [
                    'ip_address' => request()->ip(),
                    'message'    => "User account was reinstated (User ID: {$user->user_code}).",
                ],
            ]);

            return $user;
        });
    }

    /**
     * Reasons (if any) preventing a commuter from permanently deleting
     * their own account. An empty array means the account is eligible
     * for deletion.
     *
     * Rules:
     *  - No active renting post (a "rental" post that is still published/rented).
     *  - No active renting transaction (an "ongoing" rent transaction, whether
     *    the commuter is the post owner or the interested party).
     *
     * Note: a remaining card balance is NOT a blocker. The system has no
     * cash-out mechanism (balance behaves like non-refundable mobile
     * load), so any leftover balance is simply forfeited at deletion time
     * — see deleteOwnAccount(). The commuter is warned about this in the
     * confirmation prompt before they proceed.
     *
     * @return array<int, string>
     */
    public function commuterDeletionBlockers(User $user): array
    {
        $blockers = [];

        $hasActiveRentingPost = Post::where('user_id', $user->id)
            ->where('type', 'rental')
            ->whereIn('status', ['published', 'rented'])
            ->exists();

        if ($hasActiveRentingPost) {
            $blockers[] = 'You still have an active rental post. Please archive it before deleting your account.';
        }

        $hasActiveRentTransaction = RentTransaction::where('status', 'ongoing')
            ->where(function ($query) use ($user) {
                $query->where('post_owner_id', $user->id)
                    ->orWhere('interested_user_id', $user->id);
            })
            ->exists();

        if ($hasActiveRentTransaction) {
            $blockers[] = 'You still have an ongoing rental transaction. Please wait for it to complete or be cancelled first.';
        }

        return $blockers;
    }

    public function canCommuterDeleteOwnAccount(User $user): bool
    {
        return empty($this->commuterDeletionBlockers($user));
    }

    /**
     * The card balance that will be forfeited if this commuter deletes
     * their own account right now. Used to disclose this clearly in the
     * confirmation prompt before they proceed.
     */
    public function forfeitableBalance(User $user): float
    {
        return (float) ($user->card?->balance ?? 0);
    }

    /**
     * Permanently delete a commuter's own account.
     *
     * Only commuters may delete their own account, and only once none of
     * the blockers in commuterDeletionBlockers() apply. The account's
     * personal information is wiped/anonymized to "Deleted User" and the
     * account is flagged so it can never log in again. The row itself is
     * intentionally NOT soft-deleted, so transaction/travel history rows
     * that reference this user (belonging to other users) keep resolving
     * their `user` relation correctly and will display "Deleted User"
     * instead of breaking.
     *
     * Admins can never delete accounts (they may only suspend), and
     * cashiers/operators can never delete their own accounts — this
     * method must not be used for those roles.
     */
    public function deleteOwnAccount(User $user): void
    {
        if ($user->role !== 'commuter') {
            throw ValidationException::withMessages([
                'account' => 'Only commuter accounts can be self-deleted.',
            ]);
        }

        $blockers = $this->commuterDeletionBlockers($user);

        if (!empty($blockers)) {
            throw ValidationException::withMessages([
                'account' => $blockers,
            ]);
        }

        DB::transaction(function () use ($user) {
            $userCode = $user->user_code;
            $forfeitedBalance = (float) ($user->card?->balance ?? 0);

            if ($user->card) {
                // Balance cannot be cashed out (same as unused mobile load) —
                // it is forfeited, not refunded. Log it as a card
                // transaction for audit purposes before zeroing it out.
                if ($forfeitedBalance > 0) {
                    CardTransaction::create([
                        'card_id'           => $user->card->id,
                        'processed_by'      => null,
                        'source'            => 'account_deletion',
                        'transaction_type'  => 'adjustment',
                        'reference_no'      => 'FORFEIT-' . now()->format('YmdHis') . '-' . Str::random(6),
                        'amount'            => $forfeitedBalance,
                        'balance_before'    => $forfeitedBalance,
                        'balance_after'     => 0,
                        'status'            => 'success',
                        'message'           => 'Balance forfeited: account was permanently deleted by the account holder.',
                        'transaction_time'  => now(),
                    ]);
                }

                $user->card->update([
                    'balance' => 0,
                    'status'  => 'terminated',
                ]);
            }

            // Wipe/anonymize personally identifiable information. The row
            // is deliberately kept (not soft-deleted) so that existing
            // transaction/travel history rows belonging to OTHER users —
            // which load this user via a normal belongsTo relation — keep
            // resolving correctly and simply display "Deleted User" from
            // now on, instead of the relation silently returning null.
            $user->forceFill([
                'name'          => 'Deleted User',
                'email_address' => 'deleted-user-' . $user->id . '-' . Str::random(8) . '@deleted.local',
                'phone_number'  => null,
                'address'       => null,
                'password'      => Hash::make(Str::random(40)),
            ])->save();

            // Mark the account as permanently deleted so the user can
            // never log in again, without soft-deleting the row itself.
            $user->userStatus()->updateOrCreate([], [
                'is_deleted'         => true,
                'deleted_at_by_user' => now(),
            ]);

            app(AuditLogsService::class)->create([
                'user_id' => $user->id,
                'action'  => 'Account Deleted',
                'subject' => 'User Account Deletion',
                'channel' => 'Web',
                'metadata' => [
                    'ip_address' => request()->ip(),
                    'message'    => "Commuter account was permanently deleted by the account holder (User ID: {$userCode}). Remaining card balance of ₱" . number_format($forfeitedBalance, 2) . " was forfeited. Personal information was wiped; transaction history was retained and will display as \"Deleted User\".",
                ],
            ]);
        });
    }
}