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

    public $name;
    public $email;
    public $password;

    public function __construct($name, $email, $password)
    {
        $this->name = $name;
        $this->email = $email;
        $this->password = $password;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to SMART ICCT - Your Account Details',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: "
            <div style='font-family: sans-serif; padding: 20px; color: #333;'>
                <h2>Welcome to SMART ICCT, {$this->name}!</h2>
                <p>An administrator has successfully registered your account.</p>
                
                <div style='background: #f3f4f6; padding: 15px; border-radius: 8px; margin-top: 15px;'>
                    <p style='margin: 0 0 10px 0;'><strong>Email:</strong> {$this->email}</p>
                    <p style='margin: 0;'><strong>Temporary Password:</strong> <span style='font-family: monospace; font-size: 16px; letter-spacing: 1px;'>{$this->password}</span></p>
                </div>

                <p style='margin-top: 20px; color: #dc2626;'><em>⚠️ For your security, please log in and change your password immediately.</em></p>
            </div>"
        );
    }
}