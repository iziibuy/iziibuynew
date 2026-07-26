<?php

declare(strict_types=1);

namespace App\Payment\Surfboard;

use App\Models\ExternalSubscription;
use App\Models\PaymentMethodAccess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Surfboard subscription button: tokenize on first PaymentPage order, renew via MIT + CTOKEN.
 *
 * @see https://www.surfboardpayments.com/developers/guides/recurring-payments/
 */
class ApiSurfboardButtonSubscription
{
    protected PaymentMethodAccess $access;

    protected SurfboardPayment $surfboard;

    public function __construct(protected ExternalSubscription $subscription)
    {
        $this->subscription->loadMissing(['paymentMethodAccess', 'paymentApi']);
        $this->access = $this->subscription->paymentMethodAccess;

        $sandbox = ($this->access->site_mode ?? 'test') !== 'live';
        $this->surfboard = new SurfboardPayment(
            merchantId: $this->access->surfboard_merchantId,
            storeId: $this->access->surfboard_storeId,
            sandbox: $sandbox
        );
    }

    /** @return array{status:bool,code?:int,data:array<string,mixed>} */
    public function getPaymentLink(): array
    {
        if (blank($this->access->surfboard_merchantId) || blank($this->access->surfboard_terminalId)) {
            return [
                'status' => false,
                'code' => 400,
                'data' => ['message' => 'Surfboard is not configured (merchant/terminal missing).'],
            ];
        }

        $response = Http::withHeaders($this->headers())
            ->timeout(30)
            ->post("{$this->surfboard->apiUrl}/orders", $this->makeInitialOrderPayload());

        $json = $response->json() ?? [];

        if ($response->successful() && ($json['status'] ?? null) === 'SUCCESS' && isset($json['data']['orderId'])) {
            return [
                'status' => true,
                'code' => 200,
                'data' => [
                    'payment_id' => $json['data']['orderId'],
                    'url' => $json['data']['paymentPageLink'] ?? null,
                ],
            ];
        }

        return [
            'status' => false,
            'code' => $response->status() ?: 500,
            'data' => [
                'message' => $json['message'] ?? 'Could not create Surfboard subscription order.',
            ],
        ];
    }

    /**
     * After customer completes payment page: confirm status, fetch token, activate.
     *
     * @return array{status:bool,data:array<string,mixed>}
     */
    public function finalizeFromOrder(string $orderId): array
    {
        $status = $this->getOrderStatus($orderId);
        $orderStatus = data_get($status, 'data.orderStatus');

        if (! in_array($orderStatus, ['PAYMENT_COMPLETED', 'PARTIAL_PAYMENT_COMPLETED'], true)) {
            return [
                'status' => false,
                'data' => [
                    'message' => 'Payment not completed.',
                    'orderStatus' => $orderStatus,
                ],
            ];
        }

        $tokenId = $this->fetchTokenId($orderId);
        if ($tokenId === '') {
            Log::warning('Surfboard button subscription: token missing after payment', [
                'subscription_id' => $this->subscription->id,
                'order_id' => $orderId,
            ]);

            return [
                'status' => false,
                'data' => ['message' => 'Card token was not returned. Ensure tokenization is enabled.'],
            ];
        }

        $transactionId = (string) (data_get($status, 'data.paymentId')
            ?? data_get($status, 'data.transactionId')
            ?? $orderId);

        $this->subscription->update([
            'surfboard_token' => $tokenId,
            'stored_card_id' => $tokenId,
            'initial_transaction_id' => $transactionId,
            'payment_id' => $orderId,
            'status' => 'ACTIVE',
            'paid_at' => now(),
            'payment_method' => 'surfboard',
            'next_charge_at' => now()->addDays(max(1, (int) $this->subscription->interval_days)),
        ]);

        $this->subscription->charges()->create([
            'amount' => $this->subscription->amount,
            'currency' => $this->subscription->currency,
            'status' => true,
            'type' => 'signup',
            'surfboard_transaction_id' => $transactionId,
            'payment_details' => json_encode([
                'provider' => 'surfboard',
                'order_id' => $orderId,
                'type' => 'signup',
            ]),
        ]);

        return [
            'status' => true,
            'data' => [
                'tokenId' => $tokenId,
                'transactionId' => $transactionId,
            ],
        ];
    }

    /**
     * MIT renewal using stored token.
     *
     * @return array{status:bool,data:array<string,mixed>,transaction_id?:string}
     */
    public function chargeRenewal(): array
    {
        $tokenId = (string) ($this->subscription->surfboard_token ?: $this->subscription->stored_card_id);
        if ($tokenId === '') {
            return ['status' => false, 'data' => ['message' => 'No Surfboard token on file.']];
        }

        $mitTerminal = (string) ($this->access->surfboard_mit_terminalId ?: '');
        if ($mitTerminal === '') {
            return [
                'status' => false,
                'data' => ['message' => 'Surfboard MIT terminal (surfboard_mit_terminalId) is not configured.'],
            ];
        }

        $orderResponse = Http::withHeaders($this->headers())
            ->timeout(30)
            ->post("{$this->surfboard->apiUrl}/orders", $this->makeMitOrderPayload($mitTerminal));

        $orderJson = $orderResponse->json() ?? [];
        $orderId = data_get($orderJson, 'data.orderId');

        if (! $orderResponse->successful() || ! $orderId) {
            $message = $orderJson['message'] ?? 'Could not create Surfboard renewal order.';
            $this->recordFailedRenewal($message);

            return ['status' => false, 'data' => ['message' => $message]];
        }

        $payResponse = Http::withHeaders($this->headers())
            ->timeout(30)
            ->post("{$this->surfboard->apiUrl}/payments", [
                'orderId' => $orderId,
                'paymentMethod' => 'CTOKEN',
                'tokenId' => $tokenId,
            ]);

        $payJson = $payResponse->json() ?? [];

        $status = $this->getOrderStatus((string) $orderId);
        $orderStatus = data_get($status, 'data.orderStatus');

        if (! in_array($orderStatus, ['PAYMENT_COMPLETED', 'PARTIAL_PAYMENT_COMPLETED'], true)) {
            $message = $payJson['message']
                ?? data_get($status, 'data.failureReason')
                ?? 'Surfboard renewal payment failed ('.$orderStatus.').';

            $this->recordFailedRenewal($message, (string) $orderId);

            return ['status' => false, 'data' => ['message' => $message]];
        }

        $transactionId = (string) (data_get($payJson, 'data.paymentId')
            ?? data_get($status, 'data.paymentId')
            ?? $orderId);

        $this->subscription->charges()->create([
            'amount' => $this->subscription->amount,
            'currency' => $this->subscription->currency,
            'status' => true,
            'type' => 'renewal',
            'surfboard_transaction_id' => $transactionId,
            'payment_details' => json_encode([
                'provider' => 'surfboard',
                'order_id' => $orderId,
                'type' => 'renewal',
            ]),
        ]);

        $this->subscription->paid_at = now();
        $this->subscription->status = 'ACTIVE';
        $this->subscription->scheduleNextCharge();

        return [
            'status' => true,
            'transaction_id' => $transactionId,
            'data' => ['transactionId' => $transactionId, 'orderId' => $orderId],
        ];
    }

    /** @return array<string, mixed> */
    public function getOrderStatus(string $orderId): array
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(30)
            ->get("{$this->surfboard->apiUrl}/orders/{$orderId}/status");

        return $response->json() ?? [];
    }

    protected function fetchTokenId(string $orderId): string
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(30)
            ->get("{$this->surfboard->apiUrl}/orders/{$orderId}/tokens");

        $json = $response->json() ?? [];

        $tokenId = data_get($json, 'data.tokenId')
            ?? data_get($json, 'data.0.tokenId')
            ?? data_get($json, 'data.tokens.0.tokenId')
            ?? data_get($json, 'tokenId');

        if (is_array(data_get($json, 'data')) && $tokenId === null) {
            foreach ((array) data_get($json, 'data') as $item) {
                if (is_array($item) && filled($item['tokenId'] ?? null)) {
                    return (string) $item['tokenId'];
                }
            }
        }

        return filled($tokenId) ? (string) $tokenId : '';
    }

    /** @return array<string, mixed> */
    protected function makeInitialOrderPayload(): array
    {
        $minor = $this->amountMinor();
        $currency = $this->subscription->currency ?: 'NOK';
        $taxMinor = (int) round(((float) ($this->subscription->taxTotal ?? 0)) * 100);
        $taxPct = (float) ($this->subscription->taxValue ?? 0);

        return [
            'terminal$id' => $this->access->surfboard_terminalId,
            'purchaseOrderId' => $this->subscription->id,
            'companyPurchase' => false,
            'type' => 'PURCHASE',
            'referenceId' => 'extsub-'.$this->subscription->id,
            'customer' => [
                'person' => [
                    'name' => $this->subscription->customer_name,
                    'email' => $this->subscription->customer_email,
                    'phone' => $this->subscription->customer_phone,
                ],
            ],
            'orderLines' => [[
                'id' => 'sub-'.$this->subscription->id,
                'name' => $this->subscription->description ?: 'Subscription',
                'quantity' => 1,
                'itemAmount' => [
                    'regular' => $minor,
                    'total' => $minor,
                    'currency' => $currency,
                    'tax' => [[
                        'amount' => $taxMinor,
                        'percentage' => $taxPct,
                        'type' => 'vat',
                    ]],
                ],
            ]],
            'adjustments' => [],
            'totalOrderAmount' => [
                'regular' => $minor,
                'total' => $minor,
                'currency' => $currency,
                'campaign' => null,
                'shipping' => null,
                'tax' => [[
                    'amount' => $taxMinor,
                    'percentage' => $taxPct,
                    'type' => 'VAT',
                ]],
            ],
            'metaData' => [
                'external_subscription_id' => (string) $this->subscription->id,
            ],
            'controlFunctions' => [
                'includeAdjustments' => null,
                'orderLineLevelCalculation' => false,
                'callBackUrl' => route('callback.api.surfboard.subscription.success'),
                'readTags' => 'NONE',
                'authMode' => 'AUTH',
                'lockToPaymentMethods' => null,
                'storeId' => null,
                'online' => [
                    'delayCapture' => false,
                    'enforceTokenization' => true,
                    'subscription' => true,
                    'errorIfTokenizationFails' => true,
                    'enforce3DSecure' => null,
                    'paymentPageValidFor' => '60d',
                    'delayPayout' => 'DEFAULT',
                    'redirectUrl' => route('callback.api.surfboard.subscription.redirect'),
                    'generateShortLink' => true,
                    'accountNameVerification' => 'UNVERIFIED',
                    'recurring' => [
                        'subscriptionAmountType' => 'fixed',
                        'frequency' => 'unscheduled',
                        'uniqueReference' => 'extsub-'.$this->subscription->uuid,
                        'validation' => 'validated',
                    ],
                ],
                'recurring' => [],
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function makeMitOrderPayload(string $mitTerminalId): array
    {
        $minor = $this->amountMinor();
        $currency = $this->subscription->currency ?: 'NOK';

        return [
            'terminal$id' => $mitTerminalId,
            'referenceId' => 'extsub-renew-'.$this->subscription->id.'-'.now()->format('YmdHis'),
            'type' => 'PURCHASE',
            'orderLines' => [[
                'id' => 'sub-renew-'.$this->subscription->id,
                'name' => ($this->subscription->description ?: 'Subscription').' renewal',
                'quantity' => 1,
                'itemAmount' => [
                    'regular' => $minor,
                    'total' => $minor,
                    'currency' => $currency,
                ],
                // Some API variants use `amount` instead of itemAmount — also send totalOrderAmount.
            ]],
            'totalOrderAmount' => [
                'regular' => $minor,
                'total' => $minor,
                'currency' => $currency,
            ],
            'metaData' => [
                'external_subscription_id' => (string) $this->subscription->id,
                'type' => 'renewal',
            ],
        ];
    }

    protected function amountMinor(): int
    {
        return (int) round(((float) $this->subscription->amount) * 100);
    }

    /** @return array<string, string> */
    protected function headers(): array
    {
        return [
            'API-KEY' => $this->surfboard->apiKey,
            'API-SECRET' => $this->surfboard->apiSecret,
            'MERCHANT-ID' => $this->surfboard->merchantId,
            'Content-Type' => 'application/json',
        ];
    }

    protected function recordFailedRenewal(string $message, ?string $orderId = null): void
    {
        $this->subscription->charges()->create([
            'amount' => $this->subscription->amount,
            'currency' => $this->subscription->currency,
            'status' => false,
            'type' => 'renewal',
            'failure_message' => $message,
            'surfboard_transaction_id' => $orderId,
            'payment_details' => json_encode([
                'provider' => 'surfboard',
                'type' => 'renewal',
                'order_id' => $orderId,
            ]),
        ]);
    }
}
