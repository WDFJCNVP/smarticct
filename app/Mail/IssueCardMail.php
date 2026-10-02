<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IssueCardMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string name,
        public rawPin,
    )
    {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SMARTICCT - CARD PIN',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {

        $name = $this->name;

        return new Content(
            htmlContent: "        
            <div style='font-family: sans-serif; padding: 20px; color: #333;'>
                <h2>Hi, {$name}!</h2>
                <p>Your SmartICCT card has been successfully issued and linked to your account.
                    Please use the Personal Identification Number (PIN) below to authorize transactions with your card.
                </p>

                <div style='background-color:#f3f4f6; border:1px solid #e5e7eb; border-radius:8px; padding:20px; text-align:center; margin:24px 0;'>
                    <p style='margin:0 0 8px 0; font-size:12px; letter-spacing:1px; text-transform:uppercase; color:#6b7280;'>

                        Your Card PIN
                    </p
                    <p style='margin:0; font-size:32px; font-weight:bold; letter-spacing:8px; font-family:'Courier New', monospace; color:#111827;'>
                        {$rawPin}
                    </p>
                </div>

                <p style='margin-top: 20px; color: #dc2626;'><em> For your security, Never share your PIN with anyone.</em></p>
            </div>",
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
