<?php

namespace App\Payment\External\Elavon;

use App\Elavon\Converge2\Client\ClientConfig;
use App\Elavon\Converge2\Converge2;
use App\Elavon\Converge2\Response\OrderResponse;
use App\Elavon\Converge2\Response\PaymentSessionResponse;
use App\Models\ExternalBooking;
use Illuminate\Support\Facades\Log;

class ExternalBookingElavonPayment
{
    public $endpoint;

    protected $elavon;

    protected $shop;

    public $keys;

    protected $booking;

    public function __construct(ExternalBooking $booking)
    {
        $this->booking = $booking;
        $this->shop = $this->booking->paymentMethodAccess;

        Log::info('Elavon ExternalBooking: initializing payment', [
            'booking_id' => $this->booking->id,
            'total' => $this->booking->total,
            'currency' => $this->booking->currency,
            'shop_id' => optional($this->shop)->id,
        ]);

        $merchantAlias = $this->shop->elavon_merchant_alias;
        $publicKey = $this->shop->elavon_public_key;
        $secretKey = $this->shop->elavon_secret_key;
        $this->keys = [
            'mercahantAlias' => str_replace(' ', '', $merchantAlias),
            'publicKey' => str_replace(' ', '', $publicKey),
            'secretKey' => str_replace(' ', '', $secretKey),
        ];

        if ($this->shop->site_mode == 'test') {
            $this->endpoint = 'https://uat.hpp.converge.eu.elavonaws.com';
        } else {
            $this->endpoint = 'https://hpp.eu.convergepay.com';
        }

        $this->elavon = new Converge2($this->config());
    }

    protected function config()
    {
        $config = new ClientConfig;

        $config->setMerchantAlias($this->keys['mercahantAlias']);
        $config->setPublicKey($this->keys['publicKey']);
        $config->setSecretKey($this->keys['secretKey']);

        if ($this->shop->site_mode == 'test') {
            $config->setSandboxMode();
        }

        return $config;
    }

    protected function makeOrderCreateBody()
    {

        return [
            'total' => (object) [
                'amount' => $this->booking->total,
                'currencyCode' => $this->booking->currency,
            ],
            'description' => sprintf('Purchase from %s- %s', env('APP_NAME'), $this->booking->id),
            // 'expiresAt' => now()->addHours(2)->toISOString(),
            // 'returnUrl' => route('callback.elavon.payment.success'),
            'items' => [
                (object) [
                    'total' => (object) [
                        'amount' => $this->booking->total,
                        'currencyCode' => $this->booking->currency,
                    ],
                    'quantity' => 1,
                    'unitPrice' => (object) [
                        'amount' => $this->booking->total,
                        'currencyCode' => $this->booking->currency,
                    ],
                    'description' => $this->booking->booking_number ?? 'Booking',
                ],
            ],
            'shipTo' => null,
            'shopperEmailAddress' => 'john@doe.com',
            'shopperReference' => 'john@doe.com',
            'customFields' => [
                'vendor_id' => env('APP_NAME'),
                'vendor_app_name' => env('APP_NAME'),
                'vendor_app_version' => '1.0.0',
                'php_version' => phpversion(),
                // 'woocommerce_version' => '8.1.1',
                // 'WooCommerceID' => '54eccead-f25d-453b-a799-630fe3f17e53'
            ],
        ];
    }

    protected function makePaymentSessionCreateBody(OrderResponse $response)
    {
        return [
            'order' => $response->getId(),
            'billTo' => [
                'fullName' => 'john doe',
                'company' => '',
                'postalCode' => '',
                'street1' => '',
                'street2' => '',
                'city' => '',
                'countryCode' => 'NOR',
                'primaryPhone' => $this->booking->phone_number ?? '',
                'email' => 'john@doe.com',
            ],
            'returnUrl' => route('callback.plugin.externalbooking.elavon.success'),
            'cancelUrl' => route('callback.plugin.externalbooking.elavon.cancel', $this->booking),
            'originUrl' => url('/'),
            'defaultLanguageTag' => 'en-US',
            'customFields' => [
                'vendor_id' => env('APP_NAME'),
                'vendor_app_name' => env('APP_NAME'),
                'vendor_app_version' => '1.0.0',
                'php_version' => phpversion(),
            ],
            'doCreateTransaction' => true,
            'doThreeDSecure' => 1,
            'hppType' => 'fullPageRedirect',
        ];
    }

    public function getPaymentLink()
    {
        $order_create_body = $this->makeOrderCreateBody();

        $order_create_response = $this->elavon->createOrder($order_create_body);

        Log::info('Elavon ExternalBooking: order created', [
            'booking_id' => $this->booking->id,
            'order_id' => $order_create_response->getId(),
            'total' => $this->booking->total,
            'currency' => $this->booking->currency,
        ]);

        $payment_session_create_body = $this->makePaymentSessionCreateBody($order_create_response);

        $payment_session_create_response = $this->elavon->createPaymentSession($payment_session_create_body);

        Log::info('Elavon ExternalBooking: payment session create response', [
            'booking_id' => $this->booking->id,
            'session_id' => $payment_session_create_response->getId(),
            'is_success' => $payment_session_create_response->isSuccess(),
        ]);

        if ($payment_session_create_response->isSuccess()) {
            return [
                'status' => true,
                'code' => 200,
                'data' => [
                    'payment_id' => $payment_session_create_response->getId(),
                    'url' => $this->endpoint.'/?merchantAlias='.$this->keys['mercahantAlias'].'&publicApiKey='.$this->keys['publicKey'].'&sessionId='.$payment_session_create_response->getId(),
                ],
            ];
        } else {
            $message = '';
            foreach ($payment_session_create_response->getData()->failures as $failure) {

                $message .= ' | '.$failure->getDescription();
            }

            Log::warning('Elavon ExternalBooking: payment session failed', [
                'booking_id' => $this->booking->id,
                'status' => $payment_session_create_response->getData()->status ?? null,
                'message' => $message,
            ]);

            return [
                'status' => false,
                'code' => $payment_session_create_response->getData()->status,
                'data' => [
                    'message' => $message,
                ],
            ];
        }
    }

    public function processPayment($id)
    {
        try {
            if ($this->booking->elavon_transaction_id) {
                Log::info('Elavon ExternalBooking: fetching existing transaction', [
                    'booking_id' => $this->booking->id,
                    'elavon_transaction_id' => $this->booking->elavon_transaction_id,
                ]);

                $tx = $this->elavon->getTransaction($this->booking->elavon_transaction_id);
            } else {
                Log::info('Elavon ExternalBooking: processing new payment session', [
                    'booking_id' => $this->booking->id,
                    'session_id' => $id,
                ]);

                $session = $this->elavon->getPaymentSession($id);
                $transactionUrl = $session->getTransaction();

                if (! $transactionUrl) {
                    Log::info('Elavon ExternalBooking: transaction not yet available for session', [
                        'booking_id' => $this->booking->id,
                        'session_id' => $id,
                    ]);

                    return [
                        'id' => null,
                        'state' => false,
                        'pending' => true,
                        'message' => 'Transaction not yet available; retry shortly.',
                    ];
                }

                $transactionId = $this->parseUrl($transactionUrl);
                $tx = $this->elavon->getTransaction($transactionId);

                // Robust success evaluation: captured, authorized, or issuer/proc codes indicate success
                // Normalize transaction data to array to avoid stdClass property issues
                $dataRaw = $tx->getData();
                $data = is_array($dataRaw) ? $dataRaw : json_decode(json_encode($dataRaw), true);
                $state = $tx->getState();

                $isCaptured = method_exists($state, 'isCaptured') ? $state->isCaptured() : false;
                $isAuthorized = method_exists($state, 'isAuthorized') ? $state->isAuthorized() : false;

                $issuerCode = $data['issuerResponseCode'] ?? $data['processorResponseCode'] ?? null;
                $issuerMsg = $data['issuerResponseMessage'] ?? $data['processorResponseMessage'] ?? null;

                // Some gateways use '00' or '0' as success code
                $codeIndicatesSuccess = in_array((string) $issuerCode, ['00', '0', '000'], true);

                // Check state history for explicit successful states
                $history = $data['state']['history'] ?? [];
                $historyHasSuccess = false;
                foreach ($history as $h) {
                    $s = is_array($h) ? ($h['state'] ?? null) : (is_object($h) && isset($h->state) ? $h->state : null);
                    if (in_array($s, ['captured', 'authorized'], true)) {
                        $historyHasSuccess = true;
                        break;
                    }
                }

                $successful = ($isCaptured || $isAuthorized || $codeIndicatesSuccess || $historyHasSuccess);

                Log::info('Elavon ExternalBooking: transaction state evaluation', [
                    'booking_id' => $this->booking->id,
                    'tx_id' => $tx->getId(),
                    'isCaptured' => $isCaptured,
                    'isAuthorized' => $isAuthorized,
                    'issuerCode' => $issuerCode,
                    'issuerMessage' => $issuerMsg,
                    'historySuccess' => $historyHasSuccess,
                    'successful' => $successful,
                    'data' => $dataRaw,
                ]);

                return [
                    'id' => $tx->getId(),
                    'state' => $successful,
                    'pending' => false,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Elavon ExternalBooking: processPayment failed', [
                'booking_id' => $this->booking->id,
                'session_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return [
                'id' => null,
                'state' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getTransaction()
    {
        $sale_transcation_create_response = $this->elavon->getTransaction($this->booking->elavon_transaction_id);

        return $sale_transcation_create_response->getData();
    }

    /**
     * Read-only gateway inspection for admin tooling.
     *
     * @return array{success:bool,summary:array<string,mixed>,raw:array<string,mixed>,error:?string}
     */
    public function inspectGateway(): array
    {
        $raw = [];
        $summary = [];

        try {
            if (filled($this->booking->payment_id)) {
                $session = $this->elavon->getPaymentSession((string) $this->booking->payment_id);
                $raw['payment_session'] = $this->normalizeConvergePayload($session);
                $summary['session_id'] = $this->booking->payment_id;
                $summary['session_success'] = $session->isSuccess();

                $txHref = method_exists($session, 'getTransaction') ? $session->getTransaction() : null;
                if ($txHref) {
                    $summary['session_transaction'] = $this->parseUrl((string) $txHref);
                }
            }

            $transactionId = (string) ($this->booking->elavon_transaction_id ?? '');
            if ($transactionId !== '') {
                $tx = $this->elavon->getTransaction($transactionId);
                $raw['transaction'] = $this->normalizeConvergePayload($tx);
                $summary['transaction_id'] = $transactionId;
                $summary['transaction_success'] = $tx->isSuccess();

                if (method_exists($tx, 'getState') && $tx->getState()) {
                    $state = $tx->getState();
                    $summary['transaction_state'] = [
                        'authorized' => method_exists($state, 'isAuthorized') ? $state->isAuthorized() : null,
                        'captured' => method_exists($state, 'isCaptured') ? $state->isCaptured() : null,
                        'settled' => method_exists($state, 'isSettled') ? $state->isSettled() : null,
                    ];
                }
            }

            if ($raw === []) {
                return [
                    'success' => false,
                    'summary' => ['message' => 'No Elavon payment_id or transaction id available to inspect.'],
                    'raw' => [],
                    'error' => 'Missing gateway identifiers.',
                ];
            }

            return [
                'success' => true,
                'summary' => $summary,
                'raw' => $raw,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'summary' => ['message' => 'Elavon inspection failed.'],
                'raw' => $raw,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeConvergePayload(object $response): array
    {
        $payload = [
            'id' => method_exists($response, 'getId') ? $response->getId() : null,
            'success' => method_exists($response, 'isSuccess') ? $response->isSuccess() : null,
        ];

        if (method_exists($response, 'getData')) {
            $data = $response->getData();
            $payload['data'] = json_decode(json_encode($data), true);
        }

        if (method_exists($response, 'hasFailures') && $response->hasFailures() && method_exists($response, 'getFailures')) {
            $failures = [];
            foreach ($response->getFailures() as $failure) {
                $failures[] = method_exists($failure, 'getDescription')
                    ? (string) $failure->getDescription()
                    : (string) json_encode($failure);
            }
            $payload['failures'] = $failures;
        }

        return $payload;
    }

    protected function makeTransactionCreateBody(PaymentSessionResponse $response)
    {
        $body = [
            'type' => 'sale',
            'total' => (object) [
                'amount' => $this->booking->total,
                'currencyCode' => $this->booking->currency,
            ],
            'doCapture' => true,
            'shopperInteraction' => 'ecommerce',

            'shopperEmailAddress' => 'john@doe.com',
            'doSendReceipt' => false,
            'shopperIpAddress' => $_SERVER['REMOTE_ADDR'],
            'shopperReference' => 'john@doe.com',
            'shopperStatement' => [
                'name' => 'john doe',
                'phone' => $this->booking->phone_number ?? '',
                'url' => '',
            ],

            'description' => sprintf('Purchase from %s- %s', env('APP_NAME'), $this->booking->id),
            'shopperLanguageTag' => app()->getLocale(),
            'shopperTimeZone' => config('app.timezone'),
            'customFields' => [
                'vendor_id' => env('APP_NAME'),
                'vendor_app_name' => env('APP_NAME'),
                'vendor_app_version' => '1.0.0',
                'php_version' => phpversion(),
            ],
            'createdBy' => env('APP_NAME'),
            'orderReference' => $this->booking->id,
            'order' => $this->parseUrl($response->getOrder()),
            'hostedCard' => $this->parseUrl($response->getHostedCard()),

        ];
        $threeDS = $response?->getThreeDSecure();

        if ($threeDS) {
            $body['threeDSecure'] = [
                'directoryServerTransactionId' => $threeDS->getDirectoryServerTransactionId(),
                'transactionStatus' => $threeDS->getTransactionStatus(),
                'electronicCommerceIndicator' => $threeDS->getElectronicCommerceIndicator(),
                'authenticationValue' => $threeDS->getAuthenticationValue(),
                'protocolVersion' => $threeDS->getProtocolVersion(),
            ];
        }

        return $body;
    }

    protected function parseUrl($url)
    {

        // Parse the URL to get the path
        $path = parse_url($url, PHP_URL_PATH);

        // Split the path using "/"
        $parts = explode('/', $path);

        // Get the last part of the array
        $desiredPart = end($parts);

        // Output the result
        return $desiredPart;
    }
}
