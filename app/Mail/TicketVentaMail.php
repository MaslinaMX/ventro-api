<?php

namespace App\Mail;

use App\Models\Venta;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketVentaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Venta $venta,
        public string $pdfContent,
        public string $numeroTicketCompleto,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Tu ticket de compra #{$this->numeroTicketCompleto}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-venta',
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => $this->pdfContent,
                "ticket-{$this->numeroTicketCompleto}.pdf"
            )->withMime('application/pdf'),
        ];
    }
}
