<?php

namespace App\Notification;

use App\Models\Vehicle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FranchiseExpiryNotification
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    class FranchiseExpiryNotification extends Notification implements ShouldQueue
    {
        use Queueable;

        public function __construct(public Vehicle $vehicle, public string $status) {}

        public function via($notifiable): array
        {
            return ['database', 'mail']; // add 'broadcast' for real-time via Reverb
        }

        public function toMail($notifiable): MailMessage
        {
            return (new MailMessage)
                ->subject("Franchise {$this->status}: {$this->vehicle->plate_number}")
                ->line("The franchise for {$this->vehicle->plate_number} is {$this->status}.")
                ->line("Expiry date: {$this->vehicle->franchise_expiry_date->toFormattedDateString()}");
        }

        public function toArray($notifiable): array
        {
            return [
                'vehicle_id' => $this->vehicle->id,
                'plate_number' => $this->vehicle->plate_number,
                'status' => $this->status,
            ];
        }
    }
}
