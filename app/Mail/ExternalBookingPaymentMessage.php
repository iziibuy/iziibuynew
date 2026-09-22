<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExternalBookingPaymentMessage extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $bodyText,
        public string $bookingNumber,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment for booking '.$this->bookingNumber,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: nl2br(e($this->bodyText), false),
        );
    }

    /**
     * @return array<int, mixed>
     */
    public function attachments(): array
    {
        return [];
    }
}
