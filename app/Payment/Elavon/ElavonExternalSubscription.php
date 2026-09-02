<?php

declare(strict_types=1);

namespace App\Payment\Elavon;

use App\Elavon\Converge2\Converge2;
use App\Elavon\Converge2\DataObject\Resource\Endpoint;
use App\Elavon\Converge2\Response\PaymentSessionResponse;
use App\Elavon\Converge2\Response\ResponseInterface;
use App\Elavon\Converge2\Response\StoredCardResponse;
use App\Models\PaymentMethodAccess;
use App\Models\Subscription;
use App\Services\Elavon\ElavonRecurringTransaction;
use Illuminate\Support\Facades\Log;

class ElavonExternalSubscription
{
    protected ElavonExternalHostedSubscription $hosted;

    protected Converge2 $elavon;

    protected string $apiBase;

    public function __construct(protected PaymentMethodAccess $access)
    {
        $this->access->loadMissing('user');
        $this->hosted = new ElavonExternalHostedSubscription($access);
        $this->elavon = $this->hosted->convergeClient();
        $this->apiBase = $this->access->usesElavonSandbox()
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
            Log::warning('Elavon external: card vault failed after HPP return', [
                'payment_method_access_id' => $this->access->id,
                'session_id' => $sessionId,
            ]);

            return [
                'status' => false,
                'data' => ['message' => 'Card was not saved. Complete payment on the hosted page, or try again.'],
            ];
        }

        $primaryShopper = $shopperCandidates[0] ?? '';
        if ($primaryShopper !== '') {
            $this->access->shopperId = $this->convergeEntityIdFromHrefOrId($primaryShopper) ?: $primaryShopper;
        }

        $this->access->subscriptionMethod = PaymentMethodAccess::SUBSCRIPTION_METHOD_ELAVON;
        $this->access->status = true;
        $this->access->last_paid_at = now();
        $this->access->save();

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
                'recurring_setup' => true,
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
                    'payment_method_access' => [
                        'id' => $this->access->id,
                        'company' => $this->access->company_name,
                        'domain' => $this->access->company_domain,
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
        $storedCardId = (string) ($subscription->key ?? '');
        if ($storedCardId === '') {
            return [
                'status' => false,
                'data' => ['message' => 'No stored card on file for this account.'],
            ];
        }

        $amountNok = round($amountNok, 2);
        $response = $this->elavon->createSaleTransaction($this->makeMitTransactionBody($amountNok, $storedCardId, $subscription));

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
            'company' => $this->access->company_name,
            'active' => (bool) $this->access->status,
            'stored_card_id' => $this->access->subscription?->key,
            'shopper_id' => $this->access->shopperId,
            'last_paid_at' => $this->access->last_paid_at,
            'subscription' => $this->access->subscription,
        ];
    }

    /** @return array<string, mixed> */
    protected function makeMitTransactionBody(float $amountNok, string $storedCardId, ?Subscription $subscription = null): array
    {
        $email = $this->accessShopperEmail();
        $addr = $this->access->company_address;
        $user = $this->access->user;

        $body = [
            'type' => 'sale',
            'total' => [
                'amount' => $amountNok,
                'currencyCode' => 'NOK',
            ],
            'doCapture' => 1,
            'shipTo' => [
                'fullName' => trim(($user?->name ?? '').' '.($user?->last_name ?? '')) ?: ($this->access->company_name ?? 'External'),
                'company' => $this->access->company_name ?? '',
                'postalCode' => (string) ($addr->zip ?? '0000'),
                'street1' => (string) ($addr->street ?? 'N/A'),
                'street2' => '',
                'city' => (string) ($addr->city ?? 'Oslo'),
                'countryCode' => 'NOR',
                'primaryPhone' => (string) ($addr->contact_number ?? $user?->phone ?? '00000000'),
                'email' => $email,
            ],
            'shopperEmailAddress' => $email,
            'doSendReceipt' => null,
            'shopperIpAddress' => request()->ip() ?? '127.0.0.1',
            'shopperReference' => (string) $this->access->id,
            'shopperStatement' => [
                'name' => $this->access->company_name ?? 'External',
                'phone' => (string) ($addr->contact_number ?? ''),
                'url' => $this->access->company_domain ?? '',
            ],
            'description' => sprintf('External charge — %s', $this->access->company_name ?? $this->access->id),
            'shopperLanguageTag' => app()->getLocale(),
            'shopperTimeZone' => config('app.timezone'),
            'customFields' => [
                'vendor_id' => config('app.name'),
                'payment_method_access_id' => (string) $this->access->id,
            ],
            'createdBy' => config('app.name'),
            'orderReference' => ElavonRecurringTransaction::shortOrderReference(),
            'storedCard' => $this->convergeResourceUrl(Endpoint::STORED_CARD, $storedCardId),
        ];

        return ElavonRecurringTransaction::applySubsequentMerchantInitiated(
            $body,
            $this->apiBase,
            $this->resolveInitialRecurringTransactionId($subscription)
        );
    }

    protected function resolveInitialRecurringTransactionId(?Subscription $subscription): ?string
    {
        if ($subscription === null) {
            $subscription = $this->access->subscription;
        }

        if ($subscription === null) {
            return null;
        }

        $initialCharge = $subscription->charges()
            ->whereNotNull('elavon_transaction_id')
            ->orderBy('id')
            ->first();

        $transactionId = $initialCharge?->elavon_transaction_id;

        return filled($transactionId) ? (string) $transactionId : null;
    }

    protected function recordCharge(Subscription $subscription, float $amountNok, string $transactionId, array $paymentDetails): void
    {
        $subscription->charges()->create([
            'amount' => $amountNok,
            'status' => true,
            'elavon_transaction_id' => $transactionId,
            'charge_details' => json_encode(['transaction_id' => $transactionId, 'provider' => 'elavon']),
            'payment_details' => json_encode(array_merge([
                'payment_method_access' => [
                    'id' => $this->access->id,
                    'company' => $this->access->company_name,
                    'domain' => $this->access->company_domain,
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

    protected function accessShopperEmail(): string
    {
        return (string) ($this->access->company_email ?: $this->access->user?->email ?: 'external@localhost');
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

        if (filled($this->access->shopperId)) {
            $candidates[] = (string) $this->access->shopperId;
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /** @return list<string> */
    protected function shopperReferenceCandidatesFromNewShopperRecord(): array
    {
        $addr = $this->access->company_address;
        $shopper = $this->elavon->createShopper([
            'fullName' => $this->access->company_name ?? 'External',
            'company' => $this->access->company_name ?? '',
            'primaryAddress' => [
                'street1' => (string) ($addr->street ?? 'N/A'),
                'street2' => '',
                'city' => (string) ($addr->city ?? 'Oslo'),
                'region' => (string) ($addr->city ?? 'Oslo'),
                'postalCode' => (string) ($addr->zip ?? '0000'),
                'countryCode' => 'NOR',
            ],
            'primaryPhone' => (string) ($addr->contact_number ?? $this->access->user?->phone ?? '00000000'),
            'email' => $this->accessShopperEmail(),
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
        return ElavonRecurringTransaction::extractFailureMessage($response);
    }
}
