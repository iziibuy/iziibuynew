<?php

declare(strict_types=1);

namespace App\Payment\Elavon;

use App\Elavon\Converge2\Converge2;
use App\Elavon\Converge2\DataObject\Resource\Endpoint;
use App\Elavon\Converge2\Response\PaymentSessionResponse;
use App\Elavon\Converge2\Response\ResponseInterface;
use App\Elavon\Converge2\Response\StoredCardResponse;
use App\Models\Enterprise;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

class ElavonEnterpriseSubscription
{
    protected ElavonEnterpriseHostedSubscription $hosted;

    protected Converge2 $elavon;

    protected string $apiBase;

    public function __construct(protected Enterprise $enterprise)
    {
        $this->hosted = new ElavonEnterpriseHostedSubscription($enterprise);
        $this->elavon = $this->hosted->convergeClient();
        $this->apiBase = config('services.enterprise_elavon.sandbox')
            ? 'https://uat.api.converge.eu.elavonaws.com'
            : 'https://api.converge.eu.elavonaws.com';
    }

    /** @return array{status:bool,code?:int,data:array<string,mixed>} */
    public function getPaymentLink(float $amountNok, string $returnUrl, string $cancelUrl): array
    {
        return $this->hosted->getPaymentLink($amountNok, $returnUrl, $cancelUrl);
    }

    /**
     * Complete HPP return: vault card, charge signup fee, persist billing ids.
     *
     * @return array{status: bool, data: array<string, mixed>}
     */
    public function finalizeHostedSubscriptionFromSession(string $sessionId, Subscription $subscription, float $amountNok): array
    {
        $session = $this->loadPaymentSessionWithRetry($sessionId);
        if (! $session->isSuccess()) {
            return [
                'status' => false,
                'data' => ['message' => 'Could not load payment session.'],
            ];
        }

        $amountNok = round($amountNok, 2);
        $shopperCandidates = $this->shopperReferenceCandidatesFromPaymentSession($session);
        $cardId = $this->resolveStoredCardIdFromPaymentSession($session);

        if ($cardId === '') {
            $cardId = $this->resolveStoredCardIdFromShopperOnSession($session, $shopperCandidates);
        }

        if ($cardId === '') {
            $hostedCardRef = $this->resolveHostedCardReferenceFromPaymentSession($session);
            if ($hostedCardRef !== '' && $shopperCandidates === []) {
                $shopperCandidates = $this->shopperReferenceCandidatesFromNewShopperRecord();
            }
            if ($hostedCardRef !== '' && $shopperCandidates !== []) {
                $cardId = $this->createStoredCardIdFromHostedInstrument($shopperCandidates, $hostedCardRef);
            }
        }

        $transactionId = $this->resolveTransactionIdFromPaymentSession($session);

        if ($transactionId === '' && $amountNok > 0) {
            if ($cardId !== '') {
                $chargeResult = $this->hosted->chargeSignupFeeWithStoredCard($session, $amountNok, $cardId);
            } elseif ($this->hosted->chargesOnServer()) {
                $chargeResult = $this->hosted->chargeSignupFeeFromSession($session, $amountNok);
            } else {
                return [
                    'status' => false,
                    'data' => ['message' => 'Payment was not completed on the hosted page. Please try again.'],
                ];
            }

            if (! $chargeResult['status']) {
                return [
                    'status' => false,
                    'data' => ['message' => $chargeResult['data']['message'] ?? 'Payment failed.'],
                ];
            }

            $transactionId = (string) ($chargeResult['data']['transactionId'] ?? '');
        } elseif ($transactionId !== '' && $amountNok > 0) {
            $transaction = $this->elavon->getTransaction($transactionId);
            if (! $transaction->isSuccess() || ! $this->hosted->transactionIsApproved($transaction)) {
                return [
                    'status' => false,
                    'data' => ['message' => 'Payment was not approved.'],
                ];
            }

            if ($cardId === '') {
                $cardId = $this->resolveStoredCardIdFromShopperOnSession($session, $shopperCandidates);
            }
        }

        if ($cardId === '') {
            Log::warning('Elavon enterprise: card vault failed after HPP return', [
                'enterprise_id' => $this->enterprise->id,
                'session_id' => $sessionId,
            ]);

            return [
                'status' => false,
                'data' => ['message' => 'Card was not saved. Complete payment on the hosted page, or try again.'],
            ];
        }

        $primaryShopper = $shopperCandidates[0] ?? '';
        if ($primaryShopper !== '') {
            $this->enterprise->elavon_shopper_id = $this->convergeEntityIdFromHrefOrId($primaryShopper) ?: $primaryShopper;
        }

        $this->enterprise->subscription_id = $cardId;
        $this->enterprise->payment_provider = 'elavon';
        $this->enterprise->status = true;
        $this->enterprise->paid_at = now();
        $this->enterprise->payment_url = null;
        $this->enterprise->save();

        $subscription->update([
            'key' => $cardId,
            'url' => null,
            'fee' => (int) round($amountNok),
            'status' => 1,
            'establishment_status' => 1,
            'paid_at' => now(),
        ]);

        if ($transactionId !== '') {
            $this->recordCharge($subscription, $amountNok, $transactionId, [
                'type' => 'signup',
                'session_id' => $sessionId,
            ]);
        } else {
            $subscription->charges()->create([
                'amount' => 0,
                'status' => true,
                'elavon_transaction_id' => null,
                'charge_details' => json_encode([
                    'provider' => 'elavon',
                    'type' => 'vault_only_signup',
                    'session_id' => $sessionId,
                ]),
                'payment_details' => json_encode([
                    'type' => 'signup',
                    'session_id' => $sessionId,
                    'vault_only' => true,
                    'enterprise' => [
                        'uid' => $this->enterprise->unqid,
                        'name' => $this->enterprise->enterprise_name,
                        'domain' => $this->enterprise->domain,
                    ],
                ]),
            ]);
        }

        return [
            'status' => true,
            'data' => [
                'cardId' => $cardId,
                'transactionId' => $transactionId !== '' ? $transactionId : null,
            ],
        ];
    }

    /**
     * Merchant-initiated charge using vaulted stored card.
     *
     * @return array{status: bool, data: array<string, mixed>, transaction_id?: string}
     */
    public function charge(float $amountNok, Subscription $subscription, array $paymentDetails = []): array
    {
        $storedCardId = (string) ($this->enterprise->subscription_id ?? '');
        if ($storedCardId === '') {
            return [
                'status' => false,
                'data' => ['message' => 'No stored card on file for this enterprise.'],
            ];
        }

        $amountNok = round($amountNok, 2);

        if ($amountNok <= 0) {
            $this->recordCharge($subscription, 0, '', array_merge($paymentDetails, [
                'type' => 'zero_amount',
                'vault_only' => true,
            ]));

            return [
                'status' => true,
                'transaction_id' => null,
                'data' => [
                    'amount' => 0,
                    'currencyCode' => 'NOK',
                    'state' => 'skipped',
                    'processed' => true,
                ],
            ];
        }

        $response = $this->elavon->createSaleTransaction($this->makeMitTransactionBody($amountNok, $storedCardId));

        if (! $response->isSuccess() || ! $this->hosted->transactionIsApproved($response)) {
            return [
                'status' => false,
                'data' => [
                    'message' => $this->extractFailureMessage($response) ?: 'Charge was not approved.',
                ],
            ];
        }

        $transactionId = (string) $response->getId();
        $this->recordCharge($subscription, $amountNok, $transactionId, $paymentDetails);

        return [
            'status' => true,
            'transaction_id' => $transactionId,
            'data' => $this->normalizeTransactionPayload($response),
        ];
    }

    /** @return array{status: bool, data: array<string, mixed>|object|null} */
    public function getTransaction(string $transactionId): array
    {
        $response = $this->elavon->getTransaction($transactionId);

        return [
            'status' => $response->isSuccess(),
            'data' => $response->isSuccess() ? $this->normalizeTransactionPayload($response) : null,
        ];
    }

    /** @return array<string, mixed> */
    public function getBillingStatus(): array
    {
        return [
            'provider' => 'elavon',
            'enterprise_uid' => $this->enterprise->unqid,
            'active' => (bool) $this->enterprise->status,
            'stored_card_id' => $this->enterprise->subscription_id,
            'shopper_id' => $this->enterprise->elavon_shopper_id,
            'paid_at' => $this->enterprise->paid_at,
            'subscription' => $this->enterprise->subscription,
        ];
    }

    /** @return array<string, mixed> */
    protected function makeMitTransactionBody(float $amountNok, string $storedCardId): array
    {
        $email = $this->enterpriseShopperEmail();

        return [
            'type' => 'sale',
            'total' => [
                'amount' => $amountNok,
                'currencyCode' => 'NOK',
            ],
            'doCapture' => 1,
            'shopperInteraction' => 'ecommerce',
            'shipTo' => [
                'fullName' => $this->enterprise->enterprise_name ?? 'Enterprise',
                'company' => $this->enterprise->enterprise_name ?? '',
                'postalCode' => '0000',
                'street1' => $this->enterprise->domain ?? 'N/A',
                'street2' => '',
                'city' => 'Oslo',
                'countryCode' => 'NOR',
                'primaryPhone' => '00000000',
                'email' => $email,
            ],
            'shopperEmailAddress' => $email,
            'doSendReceipt' => null,
            'shopperIpAddress' => request()->ip() ?? '127.0.0.1',
            'shopperReference' => (string) $this->enterprise->unqid,
            'shopperStatement' => [
                'name' => $this->enterprise->enterprise_name ?? 'Enterprise',
                'phone' => '',
                'url' => $this->enterprise->domain ?? '',
            ],
            'description' => sprintf('Enterprise charge — %s', $this->enterprise->enterprise_name ?? $this->enterprise->unqid),
            'shopperLanguageTag' => app()->getLocale(),
            'shopperTimeZone' => config('app.timezone'),
            'customFields' => [
                'vendor_id' => config('app.name'),
                'enterprise_uid' => (string) $this->enterprise->unqid,
            ],
            'createdBy' => config('app.name'),
            'orderReference' => uniqid('ent_', true),
            'storedCard' => $this->convergeResourceUrl(Endpoint::STORED_CARD, $storedCardId),
        ];
    }

    protected function recordCharge(Subscription $subscription, float $amountNok, string $transactionId, array $paymentDetails): void
    {
        $subscription->charges()->create([
            'amount' => $amountNok,
            'status' => true,
            'elavon_transaction_id' => $transactionId,
            'charge_details' => json_encode(['transaction_id' => $transactionId, 'provider' => 'elavon']),
            'payment_details' => json_encode(array_merge([
                'enterprise' => [
                    'uid' => $this->enterprise->unqid,
                    'name' => $this->enterprise->enterprise_name,
                    'domain' => $this->enterprise->domain,
                ],
            ], $paymentDetails)),
        ]);
    }

    /** @return array<string, mixed> */
    protected function normalizeTransactionPayload(ResponseInterface $response): array
    {
        $state = method_exists($response, 'getState') && $response->getState() !== null
            ? (string) $response->getState()
            : 'unknown';

        $processed = $this->hosted->transactionIsApproved($response);

        return [
            'id' => method_exists($response, 'getId') ? $response->getId() : null,
            'state' => $processed ? 'processed' : $state,
            'provider' => 'elavon',
            'approved' => $processed,
        ];
    }

    protected function enterpriseShopperEmail(): string
    {
        $raw = $this->enterprise->domain ?? '';
        $host = parse_url($raw, PHP_URL_HOST) ?: preg_replace('#^https?://#i', '', rtrim($raw, '/'));
        $host = $host ?: 'localhost';

        return 'enterprise-'.$this->enterprise->unqid.'@'.$host;
    }

    protected function convergeResourceUrl(string $collection, string $idOrHref): string
    {
        $value = trim($idOrHref);
        if ($value === '') {
            return '';
        }
        if (str_contains($value, '://')) {
            return $value;
        }

        return rtrim($this->apiBase, '/').'/'.$collection.'/'.$value;
    }

    protected function loadPaymentSessionWithRetry(string $sessionId): PaymentSessionResponse
    {
        $attempts = 6;
        $delayMicros = 750_000;
        $last = $this->elavon->getPaymentSession($sessionId);

        for ($i = 0; $i < $attempts; $i++) {
            if ($this->paymentSessionReadyForVaulting($last)) {
                return $last;
            }

            if ($i < $attempts - 1) {
                usleep($delayMicros);
                $last = $this->elavon->getPaymentSession($sessionId);
            }
        }

        return $last;
    }

    protected function paymentSessionReadyForVaulting(PaymentSessionResponse $session): bool
    {
        if (! $session->isSuccess()) {
            return false;
        }

        if ($session->getStoredCard() || $session->getHostedCard()) {
            return true;
        }

        $txHref = $session->getTransaction();
        if (! $txHref) {
            return false;
        }

        $tx = $this->elavon->getTransaction($this->convergeEntityIdFromHrefOrId((string) $txHref));

        if (! $tx->isSuccess()) {
            return false;
        }

        return (bool) ($tx->getStoredCard() || $tx->getHostedCard());
    }

    protected function resolveTransactionIdFromPaymentSession(PaymentSessionResponse $session): string
    {
        $txHref = $session->getTransaction();
        if ($txHref) {
            return $this->convergeEntityIdFromHrefOrId((string) $txHref);
        }

        return '';
    }

    protected function resolveStoredCardIdFromPaymentSession(PaymentSessionResponse $session): string
    {
        $href = $session->getStoredCard();
        if ($href) {
            return $this->convergeEntityIdFromHrefOrId((string) $href);
        }

        $txHref = $session->getTransaction();
        if (! $txHref) {
            return '';
        }

        $tx = $this->elavon->getTransaction($this->convergeEntityIdFromHrefOrId((string) $txHref));
        if (! $tx->isSuccess()) {
            return '';
        }

        $stored = $tx->getStoredCard();

        return $stored ? $this->convergeEntityIdFromHrefOrId((string) $stored) : '';
    }

    /**
     * @param  list<string>  $shopperCandidates
     */
    protected function resolveStoredCardIdFromShopperOnSession(PaymentSessionResponse $session, array $shopperCandidates): string
    {
        $href = $session->getStoredCard();
        if ($href) {
            return $this->convergeEntityIdFromHrefOrId((string) $href);
        }

        foreach ($shopperCandidates as $shopperRef) {
            $shopperId = $this->convergeEntityIdFromHrefOrId((string) $shopperRef);
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
            if (is_object($latest) && method_exists($latest, 'getId')) {
                $id = $latest->getId();
                if ($id) {
                    return (string) $id;
                }
            }
        }

        return '';
    }

    /** @return list<string> */
    protected function shopperReferenceCandidatesFromPaymentSession(PaymentSessionResponse $session): array
    {
        $candidates = [];

        $href = $session->getShopper();
        if ($href) {
            $h = (string) $href;
            $candidates[] = $h;
            $id = $this->convergeEntityIdFromHrefOrId($h);
            if ($id !== '' && $id !== $h) {
                $candidates[] = $id;
            }
        }

        if (filled($this->enterprise->elavon_shopper_id)) {
            $candidates[] = (string) $this->enterprise->elavon_shopper_id;
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /** @return list<string> */
    protected function shopperReferenceCandidatesFromNewShopperRecord(): array
    {
        $shopper = $this->elavon->createShopper([
            'fullName' => $this->enterprise->enterprise_name ?? 'Enterprise',
            'company' => $this->enterprise->enterprise_name ?? '',
            'primaryAddress' => [
                'street1' => $this->enterprise->domain ?? 'N/A',
                'street2' => '',
                'city' => 'Oslo',
                'region' => 'Oslo',
                'postalCode' => '0000',
                'countryCode' => 'NOR',
            ],
            'primaryPhone' => '00000000',
            'email' => $this->enterpriseShopperEmail(),
        ]);

        if (! $shopper->isSuccess()) {
            return [];
        }

        $candidates = [];
        $href = $shopper->getHref();
        if ($href) {
            $candidates[] = (string) $href;
        }
        $id = $shopper->getId();
        if ($id) {
            $candidates[] = (string) $id;
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    protected function resolveHostedCardReferenceFromPaymentSession(PaymentSessionResponse $session): string
    {
        $href = $session->getHostedCard();
        if ($href) {
            return (string) $href;
        }

        $txHref = $session->getTransaction();
        if (! $txHref) {
            return '';
        }

        $tx = $this->elavon->getTransaction($this->convergeEntityIdFromHrefOrId((string) $txHref));
        if (! $tx->isSuccess()) {
            return '';
        }

        $hosted = $tx->getHostedCard();

        return $hosted ? (string) $hosted : '';
    }

    /**
     * @param  list<string>  $shopperCandidates
     */
    protected function createStoredCardIdFromHostedInstrument(array $shopperCandidates, string $hostedCardReference): string
    {
        $hostedCandidates = array_values(array_unique(array_filter([
            $hostedCardReference,
            $this->convergeEntityIdFromHrefOrId($hostedCardReference),
        ])));

        $shopperPayloads = [];
        foreach ($shopperCandidates as $c) {
            foreach ($this->convergeShopperPayloadCandidates((string) $c) as $p) {
                $shopperPayloads[] = $p;
            }
        }

        $hostedPayloads = [];
        foreach ($hostedCandidates as $c) {
            foreach ($this->convergeHostedCardPayloadCandidates((string) $c) as $p) {
                $hostedPayloads[] = $p;
            }
        }

        foreach (array_unique($shopperPayloads) as $shopper) {
            foreach (array_unique($hostedPayloads) as $hosted) {
                /** @var StoredCardResponse $response */
                $response = $this->elavon->createStoredCard([
                    'shopper' => $shopper,
                    'hostedCard' => $hosted,
                ]);

                if ($response->isSuccess()) {
                    return (string) $response->getId();
                }
            }
        }

        return '';
    }

    /** @return list<string> */
    protected function convergeShopperPayloadCandidates(string $ref): array
    {
        $ref = trim($ref);
        if ($ref === '') {
            return [];
        }
        if (str_contains($ref, '://')) {
            return array_values(array_unique(array_filter([$ref, $this->parseUrl($ref)])));
        }

        return array_values(array_unique(array_filter([
            $ref,
            $this->convergeResourceUrl(Endpoint::SHOPPER, $ref),
        ])));
    }

    /** @return list<string> */
    protected function convergeHostedCardPayloadCandidates(string $ref): array
    {
        $ref = trim($ref);
        if ($ref === '') {
            return [];
        }
        if (str_contains($ref, '://')) {
            return array_values(array_unique(array_filter([$ref, $this->parseUrl($ref)])));
        }

        return array_values(array_unique(array_filter([
            $ref,
            $this->convergeResourceUrl(Endpoint::HOSTED_CARD, $ref),
        ])));
    }

    protected function parseUrl(?string $url): string
    {
        if ($url === null || $url === '') {
            return '';
        }

        $path = parse_url($url, PHP_URL_PATH);
        if ($path === null || $path === '') {
            return '';
        }

        $parts = explode('/', $path);

        return (string) end($parts);
    }

    protected function convergeEntityIdFromHrefOrId(?string $hrefOrId): string
    {
        if ($hrefOrId === null || trim((string) $hrefOrId) === '') {
            return '';
        }

        $v = trim((string) $hrefOrId);

        if (str_contains($v, '://')) {
            $parsed = $this->parseUrl($v);

            return $parsed !== '' ? $parsed : $v;
        }

        if (str_contains($v, '/')) {
            $parsed = $this->parseUrl($v);
            if ($parsed !== '') {
                return $parsed;
            }
            $parts = array_values(array_filter(explode('/', $v)));

            return $parts !== [] ? (string) end($parts) : $v;
        }

        return $v;
    }

    protected function extractFailureMessage(ResponseInterface $response): string
    {
        $parts = [];

        if ($response->hasFailures()) {
            foreach ($response->getFailures() as $failure) {
                $description = method_exists($failure, 'getDescription') ? (string) $failure->getDescription() : '';
                if ($description !== '') {
                    $parts[] = $description;
                }
            }
        }

        return $parts !== [] ? implode(' | ', $parts) : '';
    }
}
