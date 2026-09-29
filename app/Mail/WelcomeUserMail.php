<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeUserMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public ?string $rawPin = null, 
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to SMART ICCT - Your Account Details',
        );
    }

    public function content(): Content
    {
        $name     = e($this->name);
        $email    = e($this->email);
        $password = e($this->password);

        $pinBlock = '';
        if (!empty($this->rawPin)) {
            $pin = e($this->rawPin);
            $pinBlock = "
                <p style='margin: 10px 0 0 0;'>Your card PIN: <strong style='font-family: monospace; font-size: 16px; letter-spacing: 1px;'>{$pin}</strong></p>
                <p style='margin: 10px 0 0 0;'>Keep this PIN private — anyone with your card and PIN can access your account at the kiosk.</p>";
        }

        return new Content(
            htmlString: "
            <div style='font-family: sans-serif; padding: 20px; color: #333;'>
                <h2>Welcome to SMART ICCT, {$name}!</h2>
                <p>An administrator has successfully registered your account.</p>

                <div style='background: #f3f4f6; padding: 15px; border-radius: 8px; margin-top: 15px;'>
                    <p style='margin: 0 0 10px 0;'><strong>Email:</strong> {$email}</p>
                    <p style='margin: 0;'><strong>Temporary Password:</strong> <span style='font-family: monospace; font-size: 16px; letter-spacing: 1px;'>{$password}</span></p>
                    {$pinBlock}
                </div>

                <p style='margin-top: 20px; color: #dc2626;'><em>⚠️ For your security, please log in and change your password immediately.</em></p>
            </div>"
        );
    }
}