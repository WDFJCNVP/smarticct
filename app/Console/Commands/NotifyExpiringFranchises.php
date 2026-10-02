<?php

namespace App\Console\Commands;

use App\Mail\FranchiseExpiryMail;
use App\Models\Notification;
use App\Models\UserNotification;
use App\Models\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('app:notify-expiring-franchises')]
#[Description('Notify operators of expiring or expired vehicle franchises')]

class NotifyExpiringFranchises extends Command
{

    public function handle(): int
    {
        $count = 0;

        Vehicle::with('user')
            ->whereNotNull('franchise_expiry_date')
            ->whereNotNull('user_id')
            ->each(function (Vehicle $vehicle) use (&$count) {
                $status = $vehicle->documentStatus();

                if (! in_array($status, ['expiring', 'expired'], true)
                    || $vehicle->last_notified_status === $status
                    || ! $vehicle->user) {
                    return;
                }

                $expiry = $vehicle->franchise_expiry_date;
                $days   = (int) today()->diffInDays($expiry, false);
                $daysLeftText = $days === 1 ? '1 day' : "{$days} days";

                $expired = $status === 'expired';
                $title   = $expired ? 'Franchise expired' : 'Franchise expiring soon';
                $message = $expired
                    ? "The franchise for {$vehicle->plate_number} expired on {$expiry->toFormattedDateString()}. Please renew it immediately."
                    : "The franchise for {$vehicle->plate_number} expires on {$expiry->toFormattedDateString()} ({$daysLeftText} left).";

                // 1) Site notification: create both rows atomically
                DB::transaction(function () use ($vehicle, $status, $title, $message) {
                    $notification = Notification::create([
                        'type'     => 'FranchiseExpiry',
                        'title'    => $title,
                        'message'  => $message,
                        'metadata' => [
                            'vehicle_id'   => $vehicle->id,
                            'plate_number' => $vehicle->plate_number,
                            'status'       => $status,
                        ],
                    ]);

                    UserNotification::create([
                        'notification_id' => $notification->id,
                        'user_id'         => $vehicle->user_id,
                    ]);
                });

                // 2) Email: a mail failure must not block the site notification or other vehicles
                if (! $vehicle->user->email_address) {
                    $this->warn("Vehicle {$vehicle->id}: user has no email, skipped mail.");
                } else {
                    try {
                        Mail::to($vehicle->user->email_address)->send(new FranchiseExpiryMail(
                            $vehicle->user->name,
                            $vehicle->plate_number,
                            'Franchise',
                            $expiry->toFormattedDateString(),
                            $status,
                            $expired ? null : $daysLeftText,
                        ));
                    } catch (\Throwable $e) {
                        $this->error("Vehicle {$vehicle->id}: mail failed - {$e->getMessage()}");
                        Log::error("Franchise expiry mail failed for vehicle {$vehicle->id}: {$e->getMessage()}");
                    }
                }

                $vehicle->update(['last_notified_status' => $status]);
                $count++;
            });

        $this->info("Sent {$count} notification(s).");

        return self::SUCCESS;
    }
}