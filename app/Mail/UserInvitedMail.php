<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInvitedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $activationUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bienvenido a Ventro — Activa tu cuenta',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.user-invited',
        );
    }
}
