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

    public $operatorName;
    public $plateNumber;
    public $documentLabel;
    public $expiryDate;
    public $daysLeftText;

    public function __construct($operatorName, $plateNumber, $documentLabel, $expiryDate, $daysLeftText)
    {
        $this->operatorName = $operatorName;
        $this->plateNumber = $plateNumber;
        $this->documentLabel = $documentLabel;
        $this->expiryDate = $expiryDate;
        $this->daysLeftText = $daysLeftText;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "SMART ICCT - {$this->documentLabel} Expiring Soon",
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: "
            <div style='font-family: sans-serif; padding: 20px; color: #333;'>
                <h2>{$this->documentLabel} Expiring Soon</h2>
                <p>Hi {$this->operatorName},</p>
                <p>Your <strong>{$this->documentLabel}</strong> for vehicle <strong>{$this->plateNumber}</strong> will expire on:</p>

                <div style='background: #f3f4f6; padding: 15px; border-radius: 8px; margin-top: 15px; text-align: center;'>
                    <h1 style='letter-spacing: 1px; color: #4F46E5; margin: 0;'>{$this->expiryDate}</h1>
                    <p style='margin: 8px 0 0 0;'>You have {$this->daysLeftText} left to remediate.</p>
                </div>

                <p style='margin-top: 20px; color: #dc2626;'><em>&#9888; Please renew and update your documents before the expiry date to avoid suspension from travel operations.</em></p>
            </div>"
        );
    }
}