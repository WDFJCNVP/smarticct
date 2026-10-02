<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FranchiseExpiryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $operatorName,
        public string $plateNumber,
        public string $documentLabel,
        public string $expiryDate,
        public string $status,          // 'expiring' | 'expired'
        public ?string $daysLeftText = null,
    ) {}

    public function envelope(): Envelope
    {
        $suffix = $this->status === 'expired' ? 'Has Expired' : 'Expiring Soon';

        return new Envelope(subject: "SMART ICCT - {$this->documentLabel} {$suffix}");
    }

    public function content(): Content
    {
        $expired = $this->status === 'expired';

        $name  = e($this->operatorName);
        $label = e($this->documentLabel);
        $plate = e($this->plateNumber);
        $date  = e($this->expiryDate);

        $title    = $expired ? "{$label} Has Expired" : "{$label} Expiring Soon";
        $lead     = $expired ? 'expired on' : 'will expire on';
        $subline  = $expired
            ? 'This document is already expired.'
            : 'You have ' . e($this->daysLeftText) . ' left to renew.';
        $warning  = $expired
            ? 'Please renew and update your documents immediately. Your vehicle may be suspended from travel operations.'
            : 'Please renew and update your documents before the expiry date to avoid suspension from travel operations.';

        return new Content(
            htmlString: "
            <div style='font-family: sans-serif; padding: 20px; color: #333;'>
                <h2>{$title}</h2>
                <p>Hi {$name},</p>
                <p>Your <strong>{$label}</strong> for vehicle <strong>{$plate}</strong> {$lead}:</p>
                <div style='background: #f3f4f6; padding: 15px; border-radius: 8px; margin-top: 15px; text-align: center;'>
                    <h1 style='letter-spacing: 1px; color: #4F46E5; margin: 0;'>{$date}</h1>
                    <p style='margin: 8px 0 0 0;'>{$subline}</p>
                </div>
                <p style='margin-top: 20px; color: #dc2626;'><em>&#9888; {$warning}</em></p>
            </div>"
        );
    }
}