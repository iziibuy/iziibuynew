<?php

namespace App\Payment\Elavon;

use App\Elavon\Converge2\Client\ClientConfig;
use App\Elavon\Converge2\Converge2;
use App\Elavon\Converge2\DataObject\Resource\Endpoint;
use App\Elavon\Converge2\Response\PaymentSessionResponse;
use App\Elavon\Converge2\Response\PlanResponse;
use App\Elavon\Converge2\Response\ShopperResponse;
use App\Elavon\Converge2\Response\StoredCardResponse;
use App\Models\Shop;
use Illuminate\Support\Facades\Log;
use Iziibuy;

class ElavonShopSubscription
{
    protected Converge2 $elavon;

    protected Shop $shop;

    /**
     * @var array{mercahantAlias: string, publicKey: string, secretKey: string, sandbox: bool}
     */
    protected array $convergeKeys;

    /**
     * Converge2 REST base URL (matches TaxiOnline / Elavon docs for resource hrefs).
     */
    protected string $apiBase;

    public function __construct(Shop $shop)
    {
        $this->shop = $shop;
        $this->convergeKeys = $this->resolveConvergeKeys();
        $this->apiBase = $this->convergeKeys['sandbox']
            ? 'https://uat.api.converge.eu.elavonaws.com'
            : 'https://api.converge.eu.elavonaws.com';
        $this->elavon = new Converge2($this->config());
    }

    /**
     * Full resource URL for API payloads (e.g. storedCard on sale), or passthrough if already absolute.
     */
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

    /**
     * @return array{mercahantAlias: string, publicKey: string, secretKey: string, sandbox: bool}
     */
    protected function resolveConvergeKeys(): array
    {
        $merchant = (string) ($this->shop->elavon_merchant_alias ?? '');
        $public = (string) ($this->shop->elavon_public_key ?? '');
        $secret = (string) ($this->shop->elavon_secret_key ?? '');

        if ($merchant !== '' && $public !== '' && $secret !== '') {
            return [
                'mercahantAlias' => str_replace(' ', '', $merchant),
                'publicKey' => str_replace(' ', '', $public),
                'secretKey' => str_replace(' ', '', $secret),
                'sandbox' => ($this->shop->site_mode ?? '') === 'test',
            ];
        }

        $merchant = (string) config('services.enterprise_elavon.merchant_alias', '');
        $public = (string) config('services.enterprise_elavon.public_key', '');
        $secret = (string) config('services.enterprise_elavon.secret_key', '');
        $sandbox = (bool) config('services.enterprise_elavon.sandbox');

        return [
            'mercahantAlias' => str_replace(' ', '', $merchant),
            'publicKey' => str_replace(' ', '', $public),
            'secretKey' => str_replace(' ', '', $secret),
            'sandbox' => $sandbox,
        ];
    }

    protected function config(): ClientConfig
    {
        $config = new ClientConfig;
        $config->setMerchantAlias($this->convergeKeys['mercahantAlias']);
        $config->setPublicKey($this->convergeKeys['publicKey']);
        $config->setSecretKey($this->convergeKeys['secretKey']);
        if ($this->convergeKeys['sandbox']) {
            $config->setSandboxMode();
        }

        return $config;
    }

    protected function getShopperCreateReqBody(): array
    {
        return [
            'fullName' => $this->shop->user->fullName,
            'company' => $this->shop->company_name,
            'primaryAddress' => [
                'street1' => $this->shop->street,
                'street2' => '',
                'city' => $this->shop->city,
                'region' => $this->shop->city,
                'postalCode' => $this->shop->post_code,
                'countryCode' => 'NOR',
            ],
            'primaryPhone' => $this->shop->contact_phone,
            'email' => $this->shop->contact_email ?: $this->shop->user->email,
        ];
    }

    protected function getStoreCardReqBody(): array
    {
        $shopper = $this->makeNewShopper();

        return [
            'shopper' => $shopper->getId(),
            'card' => [
                'holderName' => $this->shop->user->fullName,
                'number' => $this->shop->card_number,
                'expirationMonth' => $this->shop->expiration_month,
                'expirationYear' => $this->shop->expiration_year,
                'securityCode' => $this->shop->ccv,
                'billTo' => [
                    'fullName' => $this->shop->user->fullName,
                    'street1' => $this->shop->street,
                    'street2' => '',
                    'city' => $this->shop->city,
                    'region' => $this->shop->city,
                    'postalCode' => $this->shop->post_code,
                    'countryCode' => 'NOR',
                    'primaryPhone' => $this->shop->contact_phone,
                ],
            ],
        ];
    }

    protected function getTransactionCreateReqBody(float $amount): array
    {
        return [
            'type' => 'sale',
            'total' => [
                'amount' => $amount,
                'currencyCode' => 'NOK',
            ],
            'doCapture' => 1,
            'shopperInteraction' => 'ecommerce',
            'shipTo' => [
                'fullName' => $this->shop->user->fullName,
                'company' => $this->shop->company_name,
                'postalCode' => $this->shop->post_code,
                'street1' => $this->shop->street,
                'street2' => '',
                'city' => $this->shop->city,
                'countryCode' => 'NOR',
                'primaryPhone' => $this->shop->contact_phone,
                'email' => $this->shop->contact_email ?: $this->shop->user->email,
            ],
            'shopperEmailAddress' => $this->shop->user->email,
            'doSendReceipt' => null,
            'shopperIpAddress' => request()->ip() ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            'shopperReference' => $this->shop->id,
            'shopperStatement' => [
                'name' => $this->shop->user->fullName,
                'phone' => $this->shop->contact_phone,
                'url' => null,
            ],
            'description' => sprintf('Charged via card for shop : %s', $this->shop->user_name),
            'shopperLanguageTag' => app()->getLocale(),
            'shopperTimeZone' => config('app.timezone'),
            'customFields' => [
                'vendor_id' => env('APP_NAME'),
                'vendor_app_name' => env('APP_NAME'),
                'vendor_app_version' => '1.0.0',
                'php_version' => phpversion(),
            ],
            'createdBy' => env('APP_NAME'),
            'orderReference' => uniqid(),
            'storedCard' => $this->convergeResourceUrl(Endpoint::STORED_CARD, (string) $this->shop->subscription_id),
        ];
    }

    protected function makeNewShopper(): ShopperResponse
    {
        return $this->elavon->createShopper($this->getShopperCreateReqBody());
    }

    protected function storeACard()
    {
        return $this->elavon->createStoredCard($this->getStoreCardReqBody());
    }

    protected function hasPlainCardDetailsForVaulting(): bool
    {
        return filled($this->shop->card_number)
            && filled($this->shop->expiration_month)
            && filled($this->shop->expiration_year)
            && filled($this->shop->ccv);
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

    /**
     * Resolve a Converge resource id from either a full href or a bare id.
     * Some responses return plain UUIDs (no URL); parse_url() would yield nothing.
     */
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

    /**
     * @return array{status: bool, code?: int, data: array<string, mixed>}
     */
    public function createSubscription(): array
    {
        if ($this->hasPlainCardDetailsForVaulting()) {
            $response = $this->storeACard();

            if ($response->isSuccess()) {
                return [
                    'status' => true,
                    'code' => 200,
                    'data' => [
                        'shopperId' => $this->convergeEntityIdFromHrefOrId($response->getShopper()),
                        'cardId' => $response->getId(),
                    ],
                ];
            }
            $message = '';
            foreach ($response->getData()->failures as $failure) {
                $message .= ' | '.$failure->getDescription();
            }

            return [
                'status' => false,
                'code' => $response->getData()->status,
                'data' => [
                    'message' => $message,
                ],
            ];
        }

        $hosted = new ElavonShopHostedSubscription($this->shop);
        $amount = (float) Iziibuy::round_num($this->shop->subscriptionFee());
        $result = $hosted->getPaymentLink(
            $amount,
            route('shop.subscription.elavon.return', absolute: true),
            route('shop.subscription.payment', absolute: true),
        );

        if (! $result['status']) {
            return [
                'status' => false,
                'code' => $result['code'] ?? 500,
                'data' => $result['data'],
            ];
        }

        return [
            'status' => true,
            'code' => 200,
            'data' => [
                'requires_hpp' => true,
                'url' => $result['data']['url'],
                'payment_id' => $result['data']['payment_id'],
            ],
        ];
    }

    /**
     * After hosted checkout, persist stored-card and shopper ids on the shop.
     *
     * @return array{status: bool, data: array<string, mixed>}
     */
    public function finalizeHostedSubscriptionFromSession(string $sessionId): array
    {
        $session = $this->loadPaymentSessionWithRetry($sessionId);
        if (! $session->isSuccess()) {
            return [
                'status' => false,
                'data' => ['message' => 'Could not load payment session.'],
            ];
        }

        $hosted = new ElavonShopHostedSubscription($this->shop);
        $amount = (float) Iziibuy::round_num($this->shop->subscriptionFee());

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

        if ($transactionId === '') {
            if ($cardId !== '') {
                $chargeResult = $hosted->chargeSignupFeeWithStoredCard($session, $amount, $cardId);
            } elseif ($hosted->chargesOnServer()) {
                $chargeResult = $hosted->chargeSignupFeeFromSession($session, $amount);
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
        } else {
            $transaction = $this->elavon->getTransaction($transactionId);
            if (! $transaction->isSuccess() || ! $hosted->transactionIsApproved($transaction)) {
                $message = 'Payment was not approved.';
                if ($transaction->isSuccess() && $transaction->hasFailures()) {
                    foreach ($transaction->getFailures() as $failure) {
                        $description = method_exists($failure, 'getDescription') ? (string) $failure->getDescription() : '';
                        if ($description !== '') {
                            $message = $description;
                            break;
                        }
                    }
                }

                return [
                    'status' => false,
                    'data' => ['message' => $message],
                ];
            }

            if ($cardId === '') {
                $cardId = $this->resolveStoredCardIdFromShopperOnSession($session, $shopperCandidates);
            }
        }

        if ($cardId === '') {
            Log::warning('Elavon shop subscription: card vault failed after HPP return', [
                'shop_id' => $this->shop->id,
                'session_id' => $sessionId,
                'transaction_id' => $transactionId !== '' ? $transactionId : null,
                'hosted_card' => $session->getHostedCard(),
                'stored_card' => $session->getStoredCard(),
            ]);

            return [
                'status' => false,
                'data' => ['message' => 'Card was not saved. Complete payment on the hosted page, or try again.'],
            ];
        }

        if ($transactionId !== '') {
            $this->shop->payment_order_id = $transactionId;
        }

        $this->shop->subscription_id = $cardId;
        $primaryShopper = $shopperCandidates[0] ?? '';
        if ($primaryShopper !== '') {
            $this->shop->shopperId = $this->convergeEntityIdFromHrefOrId($primaryShopper) ?: $primaryShopper;
        }
        $this->shop->payment_url = null;
        $this->shop->save();

        return [
            'status' => true,
            'data' => [
                'cardId' => $cardId,
                'transactionId' => $transactionId !== '' ? $transactionId : null,
            ],
        ];
    }

    protected function resolveTransactionIdFromPaymentSession(PaymentSessionResponse $session): string
    {
        $txHref = $session->getTransaction();
        if ($txHref) {
            return $this->convergeEntityIdFromHrefOrId((string) $txHref);
        }

        return '';
    }

    /**
     * Hosted checkout can take a short time to attach transaction / card links.
     */
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

    /**
     * True when the session (or its linked transaction) already exposes a stored or hosted card.
     * Stopping on "transaction only" is too early: Converge often links the transaction before card refs exist.
     */
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
        if (! $stored) {
            return '';
        }

        return $this->convergeEntityIdFromHrefOrId((string) $stored);
    }

    /**
     * @return list<string>
     */
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

        $txHref = $session->getTransaction();
        if ($txHref) {
            $tx = $this->elavon->getTransaction($this->convergeEntityIdFromHrefOrId((string) $txHref));
            if ($tx->isSuccess()) {
                $shopper = $tx->getShopper();
                if ($shopper) {
                    $h = (string) $shopper;
                    $candidates[] = $h;
                    $id = $this->convergeEntityIdFromHrefOrId($h);
                    if ($id !== '' && $id !== $h) {
                        $candidates[] = $id;
                    }
                }
            }
        }

        if (filled($this->shop->shopperId)) {
            $candidates[] = (string) $this->shop->shopperId;
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * Converge often returns `shopper: null` on the payment session after HPP even when `hostedCard` is set.
     * `createStoredCard` still requires a shopper reference, so create one from the shop profile.
     *
     * @return list<string>
     */
    protected function shopperReferenceCandidatesFromNewShopperRecord(): array
    {
        $shopper = $this->makeNewShopper();
        if (! $shopper->isSuccess()) {
            Log::warning('Elavon shop subscription: createShopper failed (no shopper on session for hosted-card vault)', [
                'shop_id' => $this->shop->id,
            ]);

            return [];
        }

        $candidates = [];
        $href = $shopper->getHref();
        if ($href) {
            $h = (string) $href;
            $candidates[] = $h;
            $id = $this->convergeEntityIdFromHrefOrId($h);
            if ($id !== '' && $id !== $h) {
                $candidates[] = $id;
            }
        }
        $id = $shopper->getId();
        if ($id) {
            $candidates[] = (string) $id;
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * Hosted checkout usually exposes a hostedCard (not storedCard) until vaulted.
     */
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
        if (! $hosted) {
            return '';
        }

        return (string) $hosted;
    }

    /**
     * Create a stored-card token from a hosted-card reference (post-HPP).
     */
    protected function createStoredCardIdFromHostedInstrument(array $shopperCandidates, string $hostedCardReference): string
    {
        $parsedHosted = $this->convergeEntityIdFromHrefOrId($hostedCardReference);
        $hostedCandidates = array_values(array_unique(array_filter([
            $hostedCardReference,
            $parsedHosted,
        ])));

        $shopperPayloads = [];
        foreach ($shopperCandidates as $c) {
            foreach ($this->convergeShopperPayloadCandidates((string) $c) as $p) {
                $shopperPayloads[] = $p;
            }
        }
        $shopperPayloads = array_values(array_unique(array_filter($shopperPayloads)));

        $hostedPayloads = [];
        foreach ($hostedCandidates as $c) {
            foreach ($this->convergeHostedCardPayloadCandidates((string) $c) as $p) {
                $hostedPayloads[] = $p;
            }
        }
        $hostedPayloads = array_values(array_unique(array_filter($hostedPayloads)));

        foreach ($shopperPayloads as $shopper) {
            foreach ($hostedPayloads as $hosted) {
                /** @var StoredCardResponse $response */
                $response = $this->elavon->createStoredCard([
                    'shopper' => $shopper,
                    'hostedCard' => $hosted,
                ]);

                if ($response->isSuccess()) {
                    return $response->getId();
                }

                $data = $response->getData();
                $failures = (is_object($data) && isset($data->failures)) ? $data->failures : [];
                $message = '';
                if (is_iterable($failures)) {
                    foreach ($failures as $failure) {
                        $message .= ' | '.(is_object($failure) && method_exists($failure, 'getDescription') ? $failure->getDescription() : '');
                    }
                }
                Log::warning('Elavon shop subscription: createStoredCard from hostedCard failed', [
                    'shop_id' => $this->shop->id,
                    'shopper' => $shopper,
                    'hostedCard' => $hosted,
                    'message' => trim($message, ' |'),
                ]);
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
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

    /**
     * @return list<string>
     */
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

    /**
     * @return array{status: bool, code?: int, data: array<string, mixed>}
     */
    public function chargeViaCard(float $amount): array
    {
        $response = $this->elavon->createSaleTransaction($this->getTransactionCreateReqBody($amount));

        if ($response->isSuccess()) {
            return [
                'status' => true,
                'code' => 200,
                'data' => [
                    'status' => $response->getState()->isAuthorized() || $response->getState()->isCaptured(),
                    'id' => $response->getId(),
                ],
            ];
        }
        $message = '';
        foreach ($response->getData()->failures as $failure) {
            $message .= ' | '.$failure->getDescription();
        }

        return [
            'status' => false,
            'code' => $response->getData()->status,
            'data' => [
                'message' => $message,
            ],
        ];
    }

    public function getStoredCardResource()
    {
        return $this->elavon->getStoredCard($this->shop->subscription_id);
    }

    /**
     * @deprecated Use getStoredCardResource() for vaulted cards or planSubscription()->getConvergeSubscription() for billing.
     */
    public function getSubscription()
    {
        return $this->getStoredCardResource();
    }

    public function planSubscription(): ElavonShopPlanSubscription
    {
        return new ElavonShopPlanSubscription($this->shop, $this->elavon, $this->apiBase);
    }

    /**
     * @return array{status: bool, code?: int, data: array<string, mixed>}
     */
    public function ensurePlanAndSubscription(): array
    {
        return $this->planSubscription()->ensurePlanAndSubscription();
    }

    public function waitForActiveSubscription(int $maxAttempts = 15, int $delayMicros = 2_000_000): bool
    {
        return $this->planSubscription()->waitForActiveSubscription($maxAttempts, $delayMicros);
    }

    /**
     * @return array{status: bool, code?: int, data: array<string, mixed>}
     */
    public function syncRecurringPlan(): array
    {
        return $this->planSubscription()->syncRecurringPlanAmount();
    }

    /**
     * @return array{status: bool, code?: int, data: array<string, mixed>}
     */
    public function cancelElavonBilling(): array
    {
        return $this->planSubscription()->cancelConvergeSubscription();
    }

    public function getConvergePlan(): ?PlanResponse
    {
        if (! filled($this->shop->elavon_plan_id)) {
            return null;
        }

        return $this->elavon->getPlan($this->shop->elavon_plan_id);
    }
}
