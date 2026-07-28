<?php

namespace App\Mail;

use App\Models\ExternalBooking;
use App\Payment\External\Elavon\ExternalBookingElavonPayment;
use App\Payment\External\Surfboard\ExternalBookingSurfboardApi;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExternalBookingReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public $externalBooking;

    public $response;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(ExternalBooking $externalBooking, $response = null)
    {
        $this->externalBooking = $externalBooking;
        if ($externalBooking->usesElavon()) {
            $this->response = (new ExternalBookingElavonPayment($externalBooking))->getTransaction();
        } else {
            $this->response = (new ExternalBookingSurfboardApi($externalBooking))->getTransaction();
        }

    }

    /**
     * Get the message envelope.
     *
     * @return Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: 'Payment Receipt - Order #'.$this->externalBooking->booking_number,
        );
    }

    /**
     * Get the message content definition.
     *
     * @return Content
     */
    public function content()
    {
        return new Content(
            view: 'emails.external-booking-receipt',
            with: [
                'externalBooking' => $this->externalBooking,
                'response' => $this->response,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments()
    {
        return [];
    }
}
