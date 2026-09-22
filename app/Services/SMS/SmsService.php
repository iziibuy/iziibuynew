<?php

namespace App\Services\SMS;

use Twilio\Rest\Client;

class SmsService
{
    private Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client(
            (string) config('services.twilio.sid'),
            (string) config('services.twilio.token'),
        );
    }

    public function send(string $phone, string $message): void
    {
        if (! str_starts_with($phone, '+')) {
            $phone = '+'.$phone;
        }

        $this->client->messages->create(
            $phone,
            [
                'from' => config('services.twilio.from'),
                'body' => $message,
            ]
        );
    }
}
