<?php

declare(strict_types=1);

namespace App\Payment\Elavon;

use App\Elavon\Converge2\Client\ClientConfig;
use App\Elavon\Converge2\Converge2;
use App\Elavon\Converge2\DataObject\Resource\Endpoint;
use App\Elavon\Converge2\Response\OrderResponse;
use App\Elavon\Converge2\Response\PaymentSessionResponse;
use App\Elavon\Converge2\Response\ResponseInterface;
use App\Elavon\Converge2\Response\StoredCardResponse;
use App\Models\ExternalSubscription;
use App\Models\PaymentMethodAccess;
use App\Services\Elavon\ElavonRecurringTransaction;
use Illuminate\Support\Facades\Log;

/**
 * Merchant-credential Elavon flow for external subscription buttons:
 * HPP → vault stored card → first sale (FIRST) → later MIT renewals (SUBSEQUENT).
 */
class ApiElavonButtonSubscription
{
    public string $endpoint;

    protected Converge2 $elavon;

    protected string $apiBase;

    /** @var array{mercahantAlias:string,publicKey:string,secretKey:string} */
    protected array $keys;

    protected PaymentMethodAccess $access;

    public function __construct(protected ExternalSubscription $subscription)
    {
        $this->subscription->loadMissing(['paymentMethodAccess', 'paymentApi']);
        $this->access = $this->subscription->paymentMethodAccess;

        $this->keys = [
            'mercahantAlias' => str_replace(' ', '', (string) $this->access->elavon_merchant_alias),
            'publicKey' => str_replace(' ', '', (string) $this->access->elavon_public_key),
            'secretKey' => str_replace(' ', '', (string) $this->access->elavon_secret_key),
        ];

        $sandbox = ($this->access->site_mode ?? 'test') === 'test';
        $this->endpoint = $sandbox
            ? 'https://uat.hpp.converge.eu.elavonaws.com'
            : 'https://hpp.eu.convergepay.com';
        $this->apiBase = $sandbox
            ? 'https://uat.api.converge.eu.elavonaws.com'
            : 'https://api.converge.eu.elavonaws.com';

        $this->elavon = new Converge2($this->config());
    }

    protected function config(): ClientConfig
    {
        $config = new ClientConfig;
        $config->setMerchantAlias($this->keys['mercahantAlias']);
        $config->setPublicKey($this->keys['publicKey']);
        $config->setSecretKey($this->keys['secretKey']);
        if (($this->access->site_mode ?? 'test') === 'test') {
            $config->setSandboxMode();
        }

        return $config;
    }

    /** @return array{status:bool,code?:int,data:array<string,mixed>} */
    public function getPaymentLink(): array
    {
        if ($this->keys['mercahantAlias'] === '' || $this->keys['publicKey'] === '' || $this->keys['secretKey'] === '') {
            return [
                'status' => false,
                'code' => 400,
                'data' => ['message' => 'Elavon is not configured for this account.'],
            ];
        }

        $orderResponse = $this->elavon->createOrder($this->makeOrderCreateBody());
        if (! $orderResponse->isSuccess()) {
            return $this->failureFromResponse($orderResponse);
        }

        $sessionResponse = $this->elavon->createPaymentSession($this->makePaymentSessionCreateBody($orderResponse));
        if (! $sessionResponse->isSuccess()) {
            return $this->failureFromResponse($sessionResponse);
        }

        $sessionId = (string) $sessionResponse->getId();

        return [
            'status' => true,
            'code' => 200,
            'data' => [
                'payment_id' => $sessionId,
                'url' => $this->endpoint.'/?merchantAlias='.$this->keys['mercahantAlias'].'&publicApiKey='.$this->keys['publicKey'].'&sessionId='.$sessionId,
            ],
        ];
    }

    /**
     * Complete HPP return: vault card, take first charge, activate subscription.
     *
     * @return array{status:bool,data:array<string,mixed>}
     */
    public function finalizeFromSession(string $sessionId): array
    {
        $session = $this->elavon->getPaymentSession($sessionId);
        if (! $session->isSuccess()) {
            return ['status' => false, 'data' => ['message' => 'Could not load payment session.']];
        }

        $amount = round((float) $this->subscription->amount, 2);
        $shopperCandidates = $this->shopperCandidatesFromSession($session);
        $cardId = $this->resolveStoredCardId($session, $shopperCandidates);

        if ($cardId === '') {
            $hostedCardRef = $this->resolveHostedCardReference($session);
            if ($hostedCardRef !== '' && $shopperCandidates === []) {
                $shopperCandidates = $this->createShopperCandidates();
            }
            if ($hostedCardRef !== '' && $shopperCandidates !== []) {
                $cardId = $this->createStoredCardIdFromHostedInstrument($shopperCandidates, $hostedCardRef);
            }
        }

        if ($cardId === '') {
            Log::warning('Elavon button subscription: card vault failed', [
                'subscription_id' => $this->subscription->id,
                'session_id' => $sessionId,
            ]);

            return ['status' => false, 'data' => ['message' => 'Card was not saved. Please try again.']];
        }

        $transactionId = $this->resolveTransactionId($session);

        if ($transactionId === '' && $amount > 0) {
            $charge = $this->chargeFirstWithStoredCard($session, $amount, $cardId);
            if (! $charge['status']) {
                return $charge;
            }
            $transactionId = (string) ($charge['data']['transactionId'] ?? '');
        } elseif ($transactionId !== '' && $amount > 0) {
            $tx = $this->elavon->getTransaction($transactionId);
            if (! $tx->isSuccess() || ! $this->transactionIsApproved($tx)) {
                return ['status' => false, 'data' => ['message' => 'Payment was not approved.']];
            }
        }

        $primaryShopper = $shopperCandidates[0] ?? '';
        $this->subscription->update([
            'stored_card_id' => $cardId,
            'shopper_id' => $primaryShopper !== '' ? ($this->entityId($primaryShopper) ?: $primaryShopper) : null,
            'initial_transaction_id' => $transactionId !== '' ? $transactionId : null,
            'status' => 'ACTIVE',
            'paid_at' => now(),
            'payment_method' => 'elavon',
            'next_charge_at' => now()->addDays(max(1, (int) $this->subscription->interval_days)),
        ]);

        $this->subscription->charges()->create([
            'amount' => $amount,
            'currency' => $this->subscription->currency,
            'status' => true,
            'type' => 'signup',
            'elavon_transaction_id' => $transactionId !== '' ? $transactionId : null,
            'payment_details' => json_encode([
                'provider' => 'elavon',
                'session_id' => $sessionId,
                'type' => 'signup',
            ]),
        ]);

        return [
            'status' => true,
            'data' => [
                'cardId' => $cardId,
                'transactionId' => $transactionId !== '' ? $transactionId : null,
            ],
        ];
    }

    /**
     * Merchant-initiated renewal charge.
     *
     * @return array{status:bool,data:array<string,mixed>,transaction_id?:string}
     */
    public function chargeRenewal(): array
    {
        $storedCardId = (string) ($this->subscription->stored_card_id ?? '');
        if ($storedCardId === '') {
            return ['status' => false, 'data' => ['message' => 'No stored card on file.']];
        }

        $amount = round((float) $this->subscription->amount, 2);
        $response = $this->elavon->createSaleTransaction($this->makeMitTransactionBody($amount, $storedCardId));

        if (! $response->isSuccess() || ! $this->transactionIsApproved($response)) {
            $message = $this->extractFailureMessage($response) ?: 'Charge was not approved.';

            $this->subscription->charges()->create([
                'amount' => $amount,
                'currency' => $this->subscription->currency,
                'status' => false,
                'type' => 'renewal',
                'failure_message' => $message,
                'payment_details' => json_encode(['provider' => 'elavon', 'type' => 'renewal']),
            ]);

            return ['status' => false, 'data' => ['message' => $message]];
        }

        $transactionId = (string) $response->getId();

        $this->subscription->charges()->create([
            'amount' => $amount,
            'currency' => $this->subscription->currency,
            'status' => true,
            'type' => 'renewal',
            'elavon_transaction_id' => $transactionId,
            'payment_details' => json_encode(['provider' => 'elavon', 'type' => 'renewal']),
        ]);

        $this->subscription->paid_at = now();
        $this->subscription->status = 'ACTIVE';
        $this->subscription->scheduleNextCharge();

        return [
            'status' => true,
            'transaction_id' => $transactionId,
            'data' => ['transactionId' => $transactionId],
        ];
    }

    /** @return array<string, mixed> */
    protected function makeOrderCreateBody(): array
    {
        return [
            'total' => (object) [
                'amount' => round((float) $this->subscription->amount, 2),
                'currencyCode' => $this->subscription->currency ?: 'NOK',
            ],
            'description' => $this->subscription->description
                ?: sprintf('Subscription from %s — %s', config('app.name'), $this->subscription->id),
            'items' => null,
            'shipTo' => $this->billTo(),
            'shopperEmailAddress' => $this->subscription->customer_email,
            'shopperReference' => (string) ($this->subscription->customer_email ?: $this->subscription->id),
            'customFields' => [
                'vendor_id' => config('app.name'),
                'external_subscription_id' => (string) $this->subscription->id,
                'signup_type' => 'button_subscription',
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function makePaymentSessionCreateBody(OrderResponse $order): array
    {
        return [
            'order' => $order->getId(),
            'billTo' => $this->billTo(),
            'returnUrl' => route('callback.api.elavon.subscription.success'),
            'cancelUrl' => route('callback.api.elavon.subscription.cancel', ['subscription' => $this->subscription->id]),
            'originUrl' => $this->subscription->paymentApi?->domain
                ?? $this->access->company_domain
                ?? config('app.url'),
            'defaultLanguageTag' => 'en-US',
            'customFields' => [
                'vendor_id' => config('app.name'),
                'external_subscription_id' => (string) $this->subscription->id,
            ],
            'doCreateTransaction' => false,
            'doThreeDSecure' => 1,
            'hppType' => 'fullPageRedirect',
        ];
    }

    /** @return array<string, mixed> */
    protected function billTo(): array
    {
        return [
            'fullName' => (string) ($this->subscription->customer_name ?: 'Customer'),
            'company' => '',
            'postalCode' => (string) ($this->subscription->customer_post_code ?: '0000'),
            'street1' => (string) ($this->subscription->customer_address ?: 'N/A'),
            'street2' => '',
            'city' => 'Oslo',
            'countryCode' => 'NOR',
            'primaryPhone' => (string) ($this->subscription->customer_phone ?: '00000000'),
            'email' => (string) ($this->subscription->customer_email ?: 'customer@localhost'),
        ];
    }

    /** @return array{status:bool,data:array<string,mixed>} */
    protected function chargeFirstWithStoredCard(PaymentSessionResponse $session, float $amount, string $storedCardId): array
    {
        $body = ElavonRecurringTransaction::applyFirstSetup(
            $this->makeTransactionCreateBody($session, $amount, $storedCardId)
        );

        $response = $this->elavon->createSaleTransaction($body);
        if (! $response->isSuccess() || ! $this->transactionIsApproved($response)) {
            return [
                'status' => false,
                'data' => ['message' => $this->extractFailureMessage($response) ?: 'First payment was not approved.'],
            ];
        }

        return [
            'status' => true,
            'data' => ['transactionId' => (string) $response->getId()],
        ];
    }

    /** @return array<string, mixed> */
    protected function makeTransactionCreateBody(PaymentSessionResponse $session, float $amount, ?string $storedCardId): array
    {
        $body = [
            'type' => 'sale',
            'total' => (object) [
                'amount' => round($amount, 2),
                'currencyCode' => $this->subscription->currency ?: 'NOK',
            ],
            'doCapture' => true,
            'shipTo' => $this->billTo(),
            'shopperEmailAddress' => $this->subscription->customer_email,
            'doSendReceipt' => false,
            'shopperIpAddress' => request()->ip() ?? '127.0.0.1',
            'shopperReference' => (string) ($this->subscription->customer_email ?: $this->subscription->id),
            'description' => $this->subscription->description
                ?: sprintf('Subscription signup — %s', $this->subscription->id),
            'shopperLanguageTag' => app()->getLocale(),
            'shopperTimeZone' => config('app.timezone'),
            'orderReference' => (string) $this->subscription->id,
        ];

        $orderId = $this->entityId((string) $session->getOrder());
        if ($orderId !== '') {
            $body['order'] = $orderId;
        }

        if ($storedCardId) {
            $body['storedCard'] = $this->resourceUrl(Endpoint::STORED_CARD, $storedCardId);
        } else {
            $body['hostedCard'] = $this->entityId((string) $session->getHostedCard());
        }

        return ElavonRecurringTransaction::appendThreeDSecureFromSession($body, $session);
    }

    /** @return array<string, mixed> */
    protected function makeMitTransactionBody(float $amount, string $storedCardId): array
    {
        $body = [
            'type' => 'sale',
            'total' => [
                'amount' => $amount,
                'currencyCode' => $this->subscription->currency ?: 'NOK',
            ],
            'doCapture' => 1,
            'shipTo' => $this->billTo(),
            'shopperEmailAddress' => $this->subscription->customer_email,
            'doSendReceipt' => null,
            'shopperIpAddress' => '127.0.0.1',
            'shopperReference' => (string) ($this->subscription->shopper_id ?: $this->subscription->customer_email ?: $this->subscription->id),
            'shopperStatement' => [
                'name' => (string) ($this->access->company_name ?: config('app.name')),
                'phone' => (string) ($this->subscription->customer_phone ?: ''),
                'url' => (string) ($this->access->company_domain ?: ''),
            ],
            'description' => sprintf('Subscription renewal — %s', $this->subscription->id),
            'shopperLanguageTag' => app()->getLocale(),
            'shopperTimeZone' => config('app.timezone'),
            'createdBy' => config('app.name'),
            'orderReference' => ElavonRecurringTransaction::shortOrderReference(),
            'storedCard' => $this->resourceUrl(Endpoint::STORED_CARD, $storedCardId),
        ];

        return ElavonRecurringTransaction::applySubsequentMerchantInitiated(
            $body,
            $this->apiBase,
            $this->subscription->initial_transaction_id
                ? (string) $this->subscription->initial_transaction_id
                : null
        );
    }

    /** @return list<string> */
    protected function shopperCandidatesFromSession(PaymentSessionResponse $session): array
    {
        $candidates = [];
        $href = $session->getShopper();
        if ($href) {
            $candidates[] = (string) $href;
            $id = $this->entityId((string) $href);
            if ($id !== '' && $id !== (string) $href) {
                $candidates[] = $id;
            }
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /** @return list<string> */
    protected function createShopperCandidates(): array
    {
        $shopper = $this->elavon->createShopper([
            'fullName' => $this->subscription->customer_name ?: 'Customer',
            'company' => '',
            'primaryAddress' => [
                'street1' => (string) ($this->subscription->customer_address ?: 'N/A'),
                'street2' => '',
                'city' => 'Oslo',
                'region' => 'Oslo',
                'postalCode' => (string) ($this->subscription->customer_post_code ?: '0000'),
                'countryCode' => 'NOR',
            ],
            'primaryPhone' => (string) ($this->subscription->customer_phone ?: '00000000'),
            'email' => (string) ($this->subscription->customer_email ?: 'customer@localhost'),
        ]);

        if (! $shopper->isSuccess()) {
            return [];
        }

        $candidates = [];
        if ($shopper->getHref()) {
            $candidates[] = (string) $shopper->getHref();
        }
        if ($shopper->getId()) {
            $candidates[] = (string) $shopper->getId();
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /** @param  list<string>  $shopperCandidates */
    protected function resolveStoredCardId(PaymentSessionResponse $session, array $shopperCandidates): string
    {
        $href = $session->getStoredCard();
        if ($href) {
            return $this->entityId((string) $href);
        }

        $txHref = $session->getTransaction();
        if ($txHref) {
            $tx = $this->elavon->getTransaction($this->entityId((string) $txHref));
            if ($tx->isSuccess() && $tx->getStoredCard()) {
                return $this->entityId((string) $tx->getStoredCard());
            }
        }

        foreach ($shopperCandidates as $shopperRef) {
            $shopperId = $this->entityId((string) $shopperRef);
            if ($shopperId === '') {
                continue;
            }
            $list = $this->elavon->getShopperStoredCardList($shopperId);
            if (! $list->isSuccess()) {
                continue;
            }
            $items = $list->getItems();
            if (! is_array($items) || $items === []) {
                continue;
            }
            $latest = $items[array_key_last($items)];
            if (is_object($latest) && method_exists($latest, 'getId') && $latest->getId()) {
                return (string) $latest->getId();
            }
        }

        return '';
    }

    protected function resolveHostedCardReference(PaymentSessionResponse $session): string
    {
        $href = $session->getHostedCard();
        if ($href) {
            return (string) $href;
        }

        $txHref = $session->getTransaction();
        if (! $txHref) {
            return '';
        }

        $tx = $this->elavon->getTransaction($this->entityId((string) $txHref));
        if (! $tx->isSuccess() || ! $tx->getHostedCard()) {
            return '';
        }

        return (string) $tx->getHostedCard();
    }

    protected function resolveTransactionId(PaymentSessionResponse $session): string
    {
        $txHref = $session->getTransaction();

        return $txHref ? $this->entityId((string) $txHref) : '';
    }

    /** @param  list<string>  $shopperCandidates */
    protected function createStoredCardIdFromHostedInstrument(array $shopperCandidates, string $hostedCardReference): string
    {
        $hostedCandidates = array_values(array_unique(array_filter([
            $hostedCardReference,
            $this->entityId($hostedCardReference),
        ])));

        foreach ($shopperCandidates as $shopper) {
            foreach ($hostedCandidates as $hosted) {
                /** @var StoredCardResponse $response */
                $response = $this->elavon->createStoredCard([
                    'shopper' => str_contains($shopper, '://') ? $shopper : $this->resourceUrl(Endpoint::SHOPPER, $shopper),
                    'hostedCard' => str_contains($hosted, '://') ? $hosted : $this->resourceUrl(Endpoint::HOSTED_CARD, $hosted),
                ]);
                if ($response->isSuccess()) {
                    return (string) $response->getId();
                }
            }
        }

        return '';
    }

    public function transactionIsApproved(ResponseInterface $transaction): bool
    {
        if (! $transaction->isSuccess()) {
            return false;
        }
        if (! method_exists($transaction, 'getState')) {
            return true;
        }
        $state = $transaction->getState();

        return $state !== null && ($state->isAuthorized() || $state->isCaptured() || $state->isSettled());
    }

    protected function entityId(?string $href): string
    {
        if ($href === null || trim($href) === '') {
            return '';
        }
        $href = trim($href);
        if (! str_contains($href, '/')) {
            return $href;
        }
        $path = parse_url($href, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            $parts = array_values(array_filter(explode('/', $href)));

            return $parts !== [] ? (string) end($parts) : '';
        }
        $parts = array_values(array_filter(explode('/', $path)));

        return $parts !== [] ? (string) end($parts) : '';
    }

    protected function resourceUrl(string $endpoint, string $id): string
    {
        return rtrim($this->apiBase, '/').'/'.$endpoint.'/'.$id;
    }

    protected function extractFailureMessage(ResponseInterface $response): string
    {
        return ElavonRecurringTransaction::extractFailureMessage($response);
    }

    /** @return array{status:bool,code?:int,data:array<string,mixed>} */
    protected function failureFromResponse(ResponseInterface $response): array
    {
        return [
            'status' => false,
            'code' => 400,
            'data' => ['message' => $this->extractFailureMessage($response) ?: 'Elavon request failed.'],
        ];
    }
}
