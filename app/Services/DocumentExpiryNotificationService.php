<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Models\Notification;
use App\Models\UserNotification;
use App\Events\NotificationEvent;
use App\Mail\FranchiseExpiryMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class DocumentExpiryNotificationService
{
    // Which expiry columns to watch, and how each one should read in a message.
    private const WATCHED_DOCUMENTS = [
        'franchise_expiry_date' => 'franchise',
    ];

    /**
     * Notify operators whose vehicle documents (franchise) are about
     * to expire, once per document per scheduled run.
     *
     * Runs twice a week via the scheduler (see routes/console.php — Monday
     * and Thursday), giving operators a running reminder for the whole month
     * leading up to expiry instead of a single one-off notice. The window is
     * "within N days" rather than an exact day-N check so a vehicle already
     * inside the window still gets caught even if a run is ever skipped.
     *
     * De-dupe is scoped to (vehicle, document, expiry date, day) — this only
     * stops the *same* run from firing twice (e.g. if triggered manually and
     * via schedule on the same day), it does not stop the next scheduled
     * reminder later in the week. Once the franchise is renewed the expiry
     * date changes, which naturally resets the reminder cycle.
     */
    public function notifyExpiringDocuments(int $daysBefore = 30): int
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

                if ($this->alreadyNotifiedToday($vehicle->id, $column, $expiryDate, $today)) {
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

    private function alreadyNotifiedToday(int $vehicleId, string $column, Carbon $expiryDate, Carbon $today): bool
    {
        return Notification::where('type', 'DocumentExpiring')
            ->where('metadata->dedup_key', $this->dedupKey($vehicleId, $column, $expiryDate, $today))
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
                'dedup_key'    => $this->dedupKey($vehicle->id, $column, $expiryDate, $today),
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

        $this->sendExpiryEmail($vehicle, $label, $expiryDate, $daysLeftText);
    }

    /**
     * Email the operator on their registered account email, in addition to
     * the in-app notification above. Failures are logged instead of thrown
     * so one bad email doesn't stop the rest of the batch from processing.
     */
    private function sendExpiryEmail(Vehicle $vehicle, string $label, Carbon $expiryDate, string $daysLeftText): void
    {
        $email = $vehicle->user->email_address ?? null;

        if (!$email) {
            return;
        }

        try {
            Mail::to($email)->send(new FranchiseExpiryMail(
                $vehicle->user->name,
                $vehicle->plate_number,
                ucfirst($label),
                $expiryDate->format('F d, Y'),
                $daysLeftText,
            ));
        } catch (\Throwable $e) {
            Log::error('Failed to send franchise expiry email', [
                'vehicle_id' => $vehicle->id,
                'user_id'    => $vehicle->user_id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    private function dedupKey(int $vehicleId, string $column, Carbon $expiryDate, Carbon $sentOn): string
    {
        return "vehicle:{$vehicleId}:{$column}:{$expiryDate->toDateString()}:{$sentOn->toDateString()}";
    }
}