<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;

class SuperAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $password;

    /**
     * Create a new message instance.
     */
    public function __construct($user, $password = null)
    {
        $this->user = $user;
        $this->password = $password;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'JeevaLink - Super Admin Account Created',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: "<p>Hello {$this->user->full_name},</p>"
                . "<p>Your District Super Admin account for {$this->user->district} District has been created successfully.</p>"
                . "<p>Login Credentials:</p>"
                . "<ul>"
                . "<li>URL: https://jeevalink-frontend.vercel.app/login</li>"
                . "<li>Email: {$this->user->email}</li>"
                . ($this->password ? "<li>Password: {$this->password}</li>" : "")
                . "</ul>"
                . "<p>Please change your password upon logging in.</p>"
                . "<p>Regards,<br>JeevaLink Technical Team</p>"
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
