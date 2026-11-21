<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DueNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $row  A due-ledger row.
     */
    public function __construct(
        public array $row,
        public string $monthLabel,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Payment due for :month', ['month' => $this->monthLabel]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.due-notice');
    }
}
