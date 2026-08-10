<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;

    public function __construct($otp)
    {
        $this->otp = $otp;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your SMART ICCT Registration Code',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: "
            <div style='font-family: sans-serif; text-align: center; padding: 20px;'>
                <h2>Verify Your Email</h2>
                <p>Thank you for registering with SMART ICCT. Your 6-digit verification code is:</p>
                <h1 style='letter-spacing: 5px; color: #4F46E5; background: #f3f4f6; padding: 15px; border-radius: 8px; display: inline-block;'>{$this->otp}</h1>
                <p>This code will expire in 10 minutes.</p>
            </div>"
        );
    }
}