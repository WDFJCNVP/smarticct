<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Models\Notification;
use App\Models\UserNotification;
use App\Events\NotificationEvent;
use Illuminate\Support\Carbon;

class DocumentExpiryNotificationService
{
    // Which expiry columns to watch, and how each one should read in a message.
    private const WATCHED_DOCUMENTS = [
        'or_cr_expiry_date'     => 'OR/CR',
        'franchise_expiry_date' => 'franchise',
    ];

    /**
     * Notify operators whose vehicle documents (OR/CR, franchise) are about
     * to expire, once per document per expiry date.
     *
     * Runs daily via the scheduler (see routes/console.php). Because it runs
     * every day, a vehicle sitting at "9 days left" today will still be
     * "8 days left" tomorrow and so on — so instead of firing only on the
     * exact 7-day mark (which would silently miss anyone if a run is ever
     * skipped), it fires as soon as a document enters the warning window
     * and then de-dupes so the operator only gets one notification per
     * document per expiry date, not one every day of the window.
     */
    public function notifyExpiringDocuments(int $daysBefore = 7): int
    {
        $today = today();
        $windowEnd = $today->copy()->addDays($daysBefore)->endOfDay();

        $vehicles = Vehicle::with('user')
            ->where(function ($query) use ($today, $windowEnd) {
                foreach (array_keys(self::WATCHED_DOCUMENTS) as $column) {
                    $query->orWhere(function ($q) use ($column, $today, $windowEnd) {
                        $q->whereNotNull($column)
                          ->whereDate($column, '>=', $today)
                          ->where($column, '<=', $windowEnd);
                    });
                }
            })
            ->get();

        $sentCount = 0;

        foreach ($vehicles as $vehicle) {
            if (!$vehicle->user) {
                continue;
            }

            foreach (self::WATCHED_DOCUMENTS as $column => $label) {
                $expiryDate = $vehicle->{$column};

                if (!$expiryDate || $expiryDate->lt($today) || $expiryDate->gt($windowEnd)) {
                    continue;
                }

                if ($this->alreadyNotified($vehicle->id, $column, $expiryDate)) {
                    continue;
                }

                $this->sendNotification($vehicle, $column, $label, $expiryDate, $today);
                $sentCount++;
            }
        }

        if ($sentCount > 0) {
            broadcast(new NotificationEvent());
        }

        return $sentCount;
    }

    private function alreadyNotified(int $vehicleId, string $column, Carbon $expiryDate): bool
    {
        return Notification::where('type', 'DocumentExpiring')
            ->where('metadata->dedup_key', $this->dedupKey($vehicleId, $column, $expiryDate))
            ->exists();
    }

    private function sendNotification(Vehicle $vehicle, string $column, string $label, Carbon $expiryDate, Carbon $today): void
    {
        $daysLeft = (int) $today->diffInDays($expiryDate, false);
        $daysLeftText = $daysLeft <= 0
            ? 'today'
            : ($daysLeft === 1 ? '1 day' : "{$daysLeft} days");

        $notification = Notification::create([
            'type'    => 'DocumentExpiring',
            'title'   => "{$label} Expiring Soon",
            'message' => "Your {$label} for vehicle {$vehicle->plate_number} will expire on "
                . "{$expiryDate->format('F d, Y')}. You have {$daysLeftText} left to remediate.",
            'metadata' => [
                'dedup_key'    => $this->dedupKey($vehicle->id, $column, $expiryDate),
                'vehicle_id'   => $vehicle->id,
                'plate_number' => $vehicle->plate_number,
                'document'     => $column,
                'expiry_date'  => $expiryDate->toDateString(),
            ],
        ]);

        UserNotification::create([
            'notification_id' => $notification->id,
            'user_id'         => $vehicle->user_id,
        ]);
    }

    private function dedupKey(int $vehicleId, string $column, Carbon $expiryDate): string
    {
        return "vehicle:{$vehicleId}:{$column}:{$expiryDate->toDateString()}";
    }
}
