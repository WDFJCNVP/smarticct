<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// Intentionally NOT queued: the admin is waiting on the modal, and a queue
// failure would silently swallow the error the page needs to report.
class EmailChangeOtpMail extends Mailable
{
    public function __construct(
        public string $userName,
        public string $code,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your email verification code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.email-change-otp');
    }
}