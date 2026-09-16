<?php

namespace App\Payment\Elavon;

use App\Elavon\Converge2\Client\ClientConfig;
use App\Elavon\Converge2\Converge2;
use App\Elavon\Converge2\Response\OrderResponse;
use App\Elavon\Converge2\Response\PaymentSessionResponse;
use App\Models\ExternalOrder;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class ApiElavonPayment
{
    public $endpoint;

    protected $elavon;

    protected $shop;

    public $keys;

    protected $order;

    public function __construct(ExternalOrder $order)
    {
        $this->order = $order;
        $this->shop = $this->order->paymentMethodAccess;

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

        // dd($this->config(),$this->endpoint,$this->keys);
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
                'amount' => $this->order->amount,
                'currencyCode' => $this->order->currency,
            ],
            'description' => sprintf('Purchase from %s- %s', env('APP_NAME'), $this->order->id),
            // 'expiresAt' => now()->addDays(14)->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z'),
            // 'returnUrl' => route('callback.elavon.payment.success'),
            'items' => null,
            'shipTo' => [
                'fullName' => $this->order->first_name.' '.$this->order->last_name,
                'company' => '',
                'postalCode' => $this->order->post_code,
                'street1' => $this->order->address,
                'street2' => '',
                'city' => $this->order->city,
                'countryCode' => 'NOR',
                'primaryPhone' => $this->order->phone,
                'email' => $this->order->customer_email,
            ],
            'shopperEmailAddress' => $this->order->customer_email,
            'shopperReference' => $this->order->customer_email,
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

    public function usesCheckoutJs(): bool
    {
        $api = $this->order->paymentApi;

        return $api?->usesElavonCheckoutJs() ?? false;
    }

    public function hostedFieldsScriptUrl(): string
    {
        return rtrim($this->endpoint, '/').'/hosted-fields-client/index.js';
    }

    public function hostedPaymentPageUrl(string $sessionId): string
    {
        return $this->endpoint.'/?merchantAlias='.$this->keys['mercahantAlias']
            .'&publicApiKey='.$this->keys['publicKey']
            .'&sessionId='.$sessionId;
    }

    protected function checkoutJsOriginUrl(): string
    {
        $configured = config('services.enterprise_elavon.hpp_origin_url');

        if (is_string($configured) && trim($configured) !== '') {
            $origin = rtrim(trim($configured), '/');
        } else {
            $origin = rtrim((string) config('app.url'), '/');
        }

        return $origin.'/';
    }

    protected function makePaymentSessionCreateBody(OrderResponse $response)
    {
        $billTo = [
            'fullName' => $this->order->customer_name,
            'company' => '',
            'postalCode' => $this->order->customer_post_code,
            'street1' => $this->order->customer_address,
            'street2' => '',
            'city' => $this->order->city,
            'countryCode' => 'NOR',
            'primaryPhone' => $this->order->customer_phone,
            'email' => $this->order->customer_email,
        ];

        $customFields = [
            'vendor_id' => env('APP_NAME'),
            'vendor_app_name' => env('APP_NAME'),
            'vendor_app_version' => '1.0.0',
            'php_version' => phpversion(),
        ];

        if ($this->usesCheckoutJs()) {
            return [
                'order' => $response->getId(),
                'billTo' => $billTo,
                'originUrl' => $this->checkoutJsOriginUrl(),
                'defaultLanguageTag' => 'en-US',
                'customFields' => $customFields,
                'doCreateTransaction' => true,
                'doThreeDSecure' => 1,
                'hppType' => 'hostedPaymentFields',
            ];
        }

        return [
            'order' => $response->getId(),
            'billTo' => $billTo,
            'returnUrl' => route('callback.api.elavon.payment.success'),
            'cancelUrl' => route('callback.api.elavon.payment.cancel', ['order_id' => $this->order->id]),
            'originUrl' => $this->order->source_url,
            'defaultLanguageTag' => 'en-US',
            'customFields' => $customFields,
            'doCreateTransaction' => false,
            'doThreeDSecure' => 1,
            'hppType' => 'fullPageRedirect',
        ];
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

    protected function makeTransactionCreateBody(PaymentSessionResponse $response)
    {
        // Base payload common to 3DS and non‑3DS flows (e.g. Apple Pay)
        $body = [
            'type' => 'sale',
            'total' => (object) [
                'amount' => $this->order->amount,
                'currencyCode' => $this->order->currency,
            ],
            'doCapture' => true,
            'shopperInteraction' => 'ecommerce',
            'shipTo' => [
                'fullName' => $this->order->customer_name,
                'company' => '',
                'postalCode' => $this->order->customer_post_code,
                'street1' => $this->order->customer_address,
                'street2' => '',
                'city' => $this->order->city,
                'countryCode' => 'NOR',
                'primaryPhone' => $this->order->customer_phone,
                'email' => $this->order->customer_email,
            ],
            'shopperEmailAddress' => $this->order->customer_email,
            'doSendReceipt' => false,
            'shopperIpAddress' => $_SERVER['REMOTE_ADDR'] ?? request()->ip(),
            'shopperReference' => $this->order->customer_email,
            'shopperStatement' => [
                'name' => $this->order->customer_name,
                'phone' => $this->order->customer_phone,
                'url' => '',
            ],

            'description' => sprintf('Purchase from %s- %s', env('APP_NAME'), $this->order->id),
            'shopperLanguageTag' => app()->getLocale(),
            'shopperTimeZone' => config('app.timezone'),
            'customFields' => [
                'vendor_id' => env('APP_NAME'),
                'vendor_app_name' => env('APP_NAME'),
                'vendor_app_version' => '1.0.0',
                'php_version' => phpversion(),
            ],
            'createdBy' => env('APP_NAME'),
            'orderReference' => $this->order->id,
            'order' => $this->parseUrl($response->getOrder()),
            'hostedCard' => $this->parseUrl($response->getHostedCard()),
        ];

        // Guard 3DS access – some payment methods (like Apple Pay) may not
        // return any threeDSecure data, in which case these calls would be null.
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

    /**
     * Ensure the order has a hostedPaymentFields session for embedded CheckoutJS.
     *
     * @return array{status:bool,payment_id?:string,message?:string,data?:array<string,mixed>}
     */
    public function ensureCheckoutJsSession(): array
    {
        if (! $this->usesCheckoutJs()) {
            return [
                'status' => false,
                'message' => 'CheckoutJS mode is not enabled for this button.',
            ];
        }

        if (app()->runningUnitTests() && filled($this->order->payment_id)) {
            return [
                'status' => true,
                'payment_id' => (string) $this->order->payment_id,
            ];
        }

        if (filled($this->order->payment_id)) {
            $session = $this->elavon->getPaymentSession((string) $this->order->payment_id);

            if ($session->isSuccess() && method_exists($session, 'getData')) {
                $data = $session->getData();
                $hppType = is_object($data) ? ($data->hppType ?? null) : ($data['hppType'] ?? null);

                if ($hppType === 'hostedPaymentFields') {
                    return [
                        'status' => true,
                        'payment_id' => (string) $this->order->payment_id,
                    ];
                }
            }
        }

        $created = $this->getPaymentLink();

        if (! ($created['status'] ?? false)) {
            return [
                'status' => false,
                'message' => $created['data']['message'] ?? 'Unable to create CheckoutJS payment session.',
                'data' => $created['data'] ?? [],
            ];
        }

        $sessionId = (string) $created['data']['payment_id'];
        $publicId = $this->order->uuid ?? $this->order->ulid ?? $this->order->id;

        $this->order->update([
            'payment_id' => $sessionId,
            'payment_url' => route('elavon.checkoutjs.pay', $publicId),
            'payment_method' => 'elavon',
        ]);

        return [
            'status' => true,
            'payment_id' => $sessionId,
        ];
    }

    public function getPaymentLink()
    {
        $order_create_body = $this->makeOrderCreateBody();

        $order_create_response = $this->elavon->createOrder($order_create_body);

        $payment_session_create_body = $this->makePaymentSessionCreateBody($order_create_response);

        $payment_session_create_response = $this->elavon->createPaymentSession($payment_session_create_body);

        if ($payment_session_create_response->isSuccess()) {
            $sessionId = $payment_session_create_response->getId();
            $publicId = $this->order->uuid ?? $this->order->ulid ?? $this->order->id;
            $url = $this->usesCheckoutJs()
                ? route('elavon.checkoutjs.pay', $publicId)
                : $this->hostedPaymentPageUrl($sessionId);

            return [
                'status' => true,
                'code' => 200,
                'data' => [
                    'payment_id' => $sessionId,
                    'url' => $url,
                    'hpp_url' => $this->hostedPaymentPageUrl($sessionId),
                    'mode' => $this->usesCheckoutJs() ? 'checkoutjs' : 'hosted',
                ],
            ];
        }

        $message = '';
        foreach ($payment_session_create_response->getData()->failures as $failure) {
            $message .= ' | '.$failure->getDescription();
        }

        return [
            'status' => false,
            'code' => $payment_session_create_response->getData()->status,
            'data' => [
                'message' => $message,
            ],
        ];
    }

    /**
     * Verify a CheckoutJS payment session and mark the order paid.
     *
     * @return array{status:bool,redirect_url:string,message:?string,transaction_id:?string}
     */
    public function finalizeCheckoutJsPayment(string $sessionId): array
    {
        if ($this->order->payment_id && $this->order->payment_id !== $sessionId) {
            return [
                'status' => false,
                'redirect_url' => $this->failedRedirectUrl(),
                'message' => 'Payment session mismatch.',
                'transaction_id' => null,
            ];
        }

        if (strtoupper((string) $this->order->status) === 'COMPLETED') {
            return [
                'status' => true,
                'redirect_url' => $this->successRedirectUrl(),
                'message' => null,
                'transaction_id' => is_string($this->order->response) ? $this->order->response : null,
            ];
        }

        $session = $this->elavon->getPaymentSession($sessionId);

        if (! $session->isSuccess()) {
            return [
                'status' => false,
                'redirect_url' => $this->failedRedirectUrl(),
                'message' => 'Unable to verify payment session.',
                'transaction_id' => null,
            ];
        }

        $transactionHref = method_exists($session, 'getTransaction') ? $session->getTransaction() : null;

        if (! $transactionHref) {
            return [
                'status' => false,
                'redirect_url' => $this->failedRedirectUrl(),
                'message' => 'Payment has not been completed yet.',
                'transaction_id' => null,
            ];
        }

        $transactionId = $this->parseUrl((string) $transactionHref);
        $transaction = $this->elavon->getTransaction($transactionId);
        $authorized = false;

        if ($transaction->isSuccess() && method_exists($transaction, 'getState') && $transaction->getState()) {
            $state = $transaction->getState();
            $authorized = (method_exists($state, 'isCaptured') && $state->isCaptured())
                || (method_exists($state, 'isAuthorized') && $state->isAuthorized());
        }

        if (! $authorized) {
            return [
                'status' => false,
                'redirect_url' => $this->failedRedirectUrl(),
                'message' => 'Payment was declined.',
                'transaction_id' => $transactionId,
            ];
        }

        $this->order->update([
            'status' => 'COMPLETED',
            'paid_at' => now(),
            'payment_id' => $sessionId,
            'response' => $transactionId,
        ]);

        return [
            'status' => true,
            'redirect_url' => $this->successRedirectUrl($transactionId),
            'message' => null,
            'transaction_id' => $transactionId,
        ];
    }

    protected function successRedirectUrl(?string $transactionId = null): string
    {
        $query = http_build_query(array_filter([
            'order' => $this->order->orderId,
            'payment_id' => $this->order->payment_id,
            'transaction_id' => $transactionId,
        ]));

        return $this->order->success_redirect_url.($query ? '?'.$query : '');
    }

    protected function failedRedirectUrl(): string
    {
        $query = http_build_query(array_filter([
            'order' => $this->order->orderId,
            'payment_id' => $this->order->payment_id,
        ]));

        return $this->order->failed_redirect_url.($query ? '?'.$query : '');
    }

    /**
     * Read-only gateway inspection for admin tooling (does not create transactions).
     *
     * @return array{success:bool,summary:array<string,mixed>,raw:array<string,mixed>,error:?string}
     */
    public function inspectGateway(): array
    {
        $raw = [];
        $summary = [];

        try {
            if (filled($this->order->payment_id)) {
                $session = $this->elavon->getPaymentSession((string) $this->order->payment_id);
                $raw['payment_session'] = $this->normalizeConvergePayload($session);
                $summary['session_id'] = $this->order->payment_id;
                $summary['session_success'] = $session->isSuccess();

                $txHref = method_exists($session, 'getTransaction') ? $session->getTransaction() : null;
                if ($txHref) {
                    $summary['session_transaction'] = $this->parseUrl((string) $txHref);
                }
            }

            $transactionId = (string) ($this->order->elavon_transaction_id
                ?: (is_string($this->order->response) ? $this->order->response : ''));

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

    public function processPayment($id)
    {

        if ($this->order->elavon_transaction_id) {
            $sale_transcation_create_response = $this->elavon->getTransaction($this->order->elavon_transaction_id);
        } else {

            $payment_session_response = $this->elavon->getPaymentSession($id);

            $sale_transcation_create_body = $this->makeTransactionCreateBody($payment_session_response);
            $sale_transcation_create_response = $this->elavon->createSaleTransaction($sale_transcation_create_body);
        }

        return [
            'id' => $sale_transcation_create_response->getId(),
            'state' => $sale_transcation_create_response->getState()->isCaptured() || $sale_transcation_create_response->getState()->isAuthorized(),
        ];
    }

    // public function processPayment($id)
    // {
    //     try {
    //         if ($this->order->elavon_transaction_id) {
    //             Log::info('Elavon Order: fetching existing transaction', [
    //                 'order_id'            => $this->order->id,
    //                 'elavon_transaction_id' => $this->order->elavon_transaction_id,
    //             ]);

    //             $tx = $this->elavon->getTransaction($this->order->elavon_transaction_id);
    //         } else {
    //             Log::info('Elavon Order: processing new payment session', [
    //                 'order_id' => $this->order->id,
    //                 'session_id' => $id,
    //             ]);

    //             $session = $this->elavon->getPaymentSession($id);
    //             $transactionUrl = $session->getTransaction();

    //             if (!$transactionUrl) {
    //                 Log::info('Elavon Order: transaction not yet available for session', [
    //                     'order_id' => $this->order->id,
    //                     'session_id' => $id,
    //                 ]);

    //                 return [
    //                     'id'      => null,
    //                     'state'   => false,
    //                     'pending' => true,
    //                     'message' => 'Transaction not yet available; retry shortly.',
    //                 ];
    //             }

    //             $transactionId = $this->parseUrl($transactionUrl);
    //             $tx = $this->elavon->getTransaction($transactionId);

    //         // Robust success evaluation: captured, authorized, or issuer/proc codes indicate success
    //         // Normalize transaction data to array to avoid stdClass property issues
    //         $dataRaw = $tx->getData();
    //         $data = is_array($dataRaw) ? $dataRaw : json_decode(json_encode($dataRaw), true);
    //         $state = $tx->getState();

    //         $isCaptured   = method_exists($state, 'isCaptured') ? $state->isCaptured() : false;
    //         $isAuthorized = method_exists($state, 'isAuthorized') ? $state->isAuthorized() : false;

    //         $issuerCode = $data['issuerResponseCode'] ?? $data['processorResponseCode'] ?? null;
    //         $issuerMsg  = $data['issuerResponseMessage'] ?? $data['processorResponseMessage'] ?? null;

    //         // Some gateways use '00' or '0' as success code
    //         $codeIndicatesSuccess = in_array((string) $issuerCode, ['00', '0', '000'], true);

    //         // Check state history for explicit successful states
    //         $history = $data['state']['history'] ?? [];
    //         $historyHasSuccess = false;
    //         foreach ($history as $h) {
    //             $s = is_array($h) ? ($h['state'] ?? null) : (is_object($h) && isset($h->state) ? $h->state : null);
    //             if (in_array($s, ['captured', 'authorized'], true)) {
    //                 $historyHasSuccess = true;
    //                 break;
    //             }
    //         }

    //         $successful = ($isCaptured || $isAuthorized || $codeIndicatesSuccess || $historyHasSuccess);

    //         Log::info('Elavon Order: transaction state evaluation', [
    //             'order_id'     => $this->order->id,
    //             'tx_id'          => $tx->getId(),
    //             'isCaptured'     => $isCaptured,
    //             'isAuthorized'   => $isAuthorized,
    //             'issuerCode'     => $issuerCode,
    //             'issuerMessage'  => $issuerMsg,
    //             'historySuccess' => $historyHasSuccess,
    //             'successful'     => $successful,
    //             'data'           => $dataRaw,
    //         ]);

    //         return [
    //             'id'      => $tx->getId(),
    //             'state'   => $successful,
    //             'pending' => false,
    //         ];
    //     }
    //     } catch (\Throwable $e) {
    //         Log::warning('Elavon Order: processPayment failed', [
    //             'order_id' => $this->order->id,
    //             'session_id' => $id,
    //             'error'      => $e->getMessage(),
    //         ]);

    //         return [
    //             'id'    => null,
    //             'state' => false,
    //             'error' => $e->getMessage(),
    //         ];
    //     }
    // }
}
