<?php

namespace App\Payment\Elavon;

use App\Elavon\Converge2\Client\ClientConfig;
use App\Elavon\Converge2\Converge2;
use App\Elavon\Converge2\DataObject\Resource\Endpoint;
use App\Elavon\Converge2\Response\OrderResponse;
use App\Elavon\Converge2\Response\PaymentSessionResponse;
use App\Elavon\Converge2\Response\ResponseInterface;
use App\Models\Shop;
use Illuminate\Support\Facades\Log;

class ElavonShopHostedSubscription
{
    public string $endpoint;

    protected Converge2 $elavon;

    protected string $apiBase;

    /**
     * @var array{mercahantAlias:string,publicKey:string,secretKey:string,sandbox:bool}
     */
    protected array $keys;

    public function __construct(protected Shop $shop)
    {
        $this->keys = $this->resolveKeys();
        $this->endpoint = $this->keys['sandbox']
            ? 'https://uat.hpp.converge.eu.elavonaws.com'
            : 'https://hpp.eu.convergepay.com';
        $this->apiBase = $this->keys['sandbox']
            ? 'https://uat.api.converge.eu.elavonaws.com'
            : 'https://api.converge.eu.elavonaws.com';

        $this->elavon = new Converge2($this->config());
    }

    /**
     * Subscriptions must vault the card on HPP first; charging on HPP consumes the hosted card.
     */
    public function createsTransactionOnHostedPage(): bool
    {
        return false;
    }

    /**
     * @return array{mercahantAlias:string,publicKey:string,secretKey:string,sandbox:bool}
     */
    protected function resolveKeys(): array
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
        $config->setMerchantAlias($this->keys['mercahantAlias']);
        $config->setPublicKey($this->keys['publicKey']);
        $config->setSecretKey($this->keys['secretKey']);
        if ($this->keys['sandbox']) {
            $config->setSandboxMode();
        }

        return $config;
    }

    protected function shopperEmail(): string
    {
        return (string) ($this->shop->contact_email ?: $this->shop->user->email);
    }

    protected function shopperFullName(): string
    {
        $name = trim((string) ($this->shop->user->fullName ?? ''));

        return $name !== '' ? $name : 'Shop Owner';
    }

    /**
     * @return array<string, mixed>
     */
    protected function billTo(): array
    {
        return [
            'fullName' => $this->shopperFullName(),
            'company' => (string) ($this->shop->company_name ?? ''),
            'postalCode' => (string) ($this->shop->post_code ?: '0000'),
            'street1' => (string) ($this->shop->street ?: 'N/A'),
            'street2' => '',
            'city' => (string) ($this->shop->city ?: 'Oslo'),
            'countryCode' => 'NOR',
            'primaryPhone' => (string) ($this->shop->contact_phone ?: $this->shop->user->phone ?? '00000000'),
            'email' => $this->shopperEmail(),
        ];
    }

    protected function threeDSecureEnabled(): bool
    {
        // Vault-only HPP (charge on return) requires 3DS — same as ApiElavonPayment.
        if ($this->chargesOnServer()) {
            return true;
        }

        if ($this->keys['sandbox'] && (bool) config('services.enterprise_elavon.disable_hpp_3ds', false)) {
            return false;
        }

        return true;
    }

    protected function hppOriginUrl(): string
    {
        $configured = config('services.enterprise_elavon.hpp_origin_url');
        if (is_string($configured) && trim($configured) !== '') {
            return rtrim(trim($configured), '/');
        }

        return rtrim((string) config('app.url'), '/');
    }

    public function chargesOnServer(): bool
    {
        return ! $this->createsTransactionOnHostedPage();
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

    /**
     * @return array<string, mixed>
     */
    protected function getShopperCreateBody(): array
    {
        return [
            'fullName' => $this->shopperFullName(),
            'company' => (string) ($this->shop->company_name ?? ''),
            'primaryAddress' => [
                'street1' => (string) ($this->shop->street ?: 'N/A'),
                'street2' => '',
                'city' => (string) ($this->shop->city ?: 'Oslo'),
                'region' => (string) ($this->shop->city ?: 'Oslo'),
                'postalCode' => (string) ($this->shop->post_code ?: '0000'),
                'countryCode' => 'NOR',
            ],
            'primaryPhone' => (string) ($this->shop->contact_phone ?: $this->shop->user->phone ?? '00000000'),
            'email' => $this->shopperEmail(),
        ];
    }

    protected function resolveShopperReferenceForSession(): ?string
    {
        if (filled($this->shop->shopperId)) {
            return $this->convergeResourceUrl(Endpoint::SHOPPER, (string) $this->shop->shopperId);
        }

        $shopper = $this->elavon->createShopper($this->getShopperCreateBody());
        if (! $shopper->isSuccess()) {
            Log::warning('Elavon shop subscription HPP: could not create shopper before payment session', [
                'shop_id' => $this->shop->id,
            ]);

            return null;
        }

        $shopperId = $shopper->getId();
        if ($shopperId) {
            $this->shop->shopperId = $shopperId;
            $this->shop->save();
        }

        $href = $shopper->getHref();

        return $href ? (string) $href : ($shopperId ? $this->convergeResourceUrl(Endpoint::SHOPPER, $shopperId) : null);
    }

    /**
     * @return array<string, mixed>
     */
    protected function makePaymentSessionCreateBody(OrderResponse $response, string $returnUrl, string $cancelUrl): array
    {
        return [
            'order' => $response->getId(),
            'billTo' => $this->billTo(),
            'returnUrl' => $returnUrl,
            'cancelUrl' => $cancelUrl,
            'originUrl' => $this->hppOriginUrl(),
            'defaultLanguageTag' => 'en-US',
            'customFields' => [
                'vendor_id' => config('app.name'),
                'vendor_app_name' => config('app.name'),
                'vendor_app_version' => '1.0.0',
                'shop_id' => (string) $this->shop->id,
            ],
            'doCreateTransaction' => $this->createsTransactionOnHostedPage(),
            'doThreeDSecure' => $this->threeDSecureEnabled() ? 1 : 0,
            'hppType' => 'fullPageRedirect',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function makeOrderCreateBody(float $amountNok): array
    {
        return [
            'total' => (object) [
                'amount' => $amountNok,
                'currencyCode' => 'NOK',
            ],
            'description' => sprintf('Shop subscription — %s', $this->shop->user_name),
            'items' => null,
            'shipTo' => $this->billTo(),
            'shopperEmailAddress' => $this->shopperEmail(),
            'shopperReference' => (string) $this->shop->id,
            'customFields' => [
                'vendor_id' => config('app.name'),
                'vendor_app_name' => config('app.name'),
                'vendor_app_version' => '1.0.0',
                'shop_id' => (string) $this->shop->id,
            ],
        ];
    }

    /**
     * @return array{status: bool, code?: int, data: array<string, mixed>}
     */
    public function getPaymentLink(float $amountNok, string $returnUrl, string $cancelUrl): array
    {
        if ($this->keys['mercahantAlias'] === '' || $this->keys['publicKey'] === '' || $this->keys['secretKey'] === '') {
            Log::warning('Elavon shop subscription HPP: missing Converge2 credentials (shop keys or services.enterprise_elavon).');

            return [
                'status' => false,
                'code' => 400,
                'data' => ['message' => 'Elavon subscription payment is not configured.'],
            ];
        }

        $amountNok = round($amountNok, 2);

        $order_create_response = $this->elavon->createOrder($this->makeOrderCreateBody($amountNok));
        if (! $order_create_response->isSuccess()) {
            return $this->failureFromResponse($order_create_response, 'order');
        }

        $this->resolveShopperReferenceForSession();

        $session_body = $this->makePaymentSessionCreateBody($order_create_response, $returnUrl, $cancelUrl);

        Log::info('Elavon shop subscription HPP: creating payment session', [
            'shop_id' => $this->shop->id,
            'amount' => $amountNok,
            'sandbox' => $this->keys['sandbox'],
            'charge_on_server' => $this->chargesOnServer(),
            'creates_transaction_on_hpp' => $this->createsTransactionOnHostedPage(),
            'three_d_secure' => $this->threeDSecureEnabled(),
            'origin_url' => $this->hppOriginUrl(),
            'return_url' => $returnUrl,
        ]);

        $payment_session_create_response = $this->elavon->createPaymentSession($session_body);

        if ($payment_session_create_response->isSuccess()) {
            $sessionId = $payment_session_create_response->getId();

            return [
                'status' => true,
                'code' => 200,
                'data' => [
                    'payment_id' => $sessionId,
                    'url' => $this->endpoint.'/?merchantAlias='.$this->keys['mercahantAlias'].'&publicApiKey='.$this->keys['publicKey'].'&sessionId='.$sessionId,
                ],
            ];
        }

        return $this->failureFromResponse($payment_session_create_response, 'payment_session');
    }

    /**
     * Charge the signup fee using a vaulted stored card (preferred after HPP vault).
     *
     * @return array{status: bool, data: array<string, mixed>}
     */
    public function chargeSignupFeeWithStoredCard(PaymentSessionResponse $session, float $amountNok, string $storedCardId): array
    {
        $response = $this->elavon->createSaleTransaction(
            $this->makeTransactionCreateBody($session, $amountNok, $storedCardId)
        );

        if (! $response->isSuccess() || ! $this->transactionIsApproved($response)) {
            $message = $this->extractFailureMessage($response);

            Log::warning('Elavon shop subscription: stored-card signup charge failed', [
                'shop_id' => $this->shop->id,
                'session_id' => $session->getId(),
                'stored_card_id' => $storedCardId,
                'message' => $message,
                'response_body' => $response->getRawResponseBody(),
            ]);

            return [
                'status' => false,
                'data' => ['message' => $message !== '' ? $message : 'Payment was not approved.'],
            ];
        }

        return [
            'status' => true,
            'data' => ['transactionId' => $response->getId()],
        ];
    }

    /**
     * Charge the signup fee from a completed HPP session (ApiElavon pattern).
     *
     * @return array{status: bool, data: array<string, mixed>}
     */
    public function chargeSignupFeeFromSession(PaymentSessionResponse $session, float $amountNok): array
    {
        $existingTransactionId = $this->entityIdFromHref($session->getTransaction());
        if ($existingTransactionId !== '') {
            $transaction = $this->elavon->getTransaction($existingTransactionId);
            if ($transaction->isSuccess() && $this->transactionIsApproved($transaction)) {
                return [
                    'status' => true,
                    'data' => ['transactionId' => $existingTransactionId],
                ];
            }
        }

        $hostedCard = $session->getHostedCard();
        if (! $hostedCard) {
            Log::warning('Elavon shop subscription: no hostedCard on session for server-side charge', [
                'shop_id' => $this->shop->id,
                'session_id' => $session->getId(),
            ]);

            return [
                'status' => false,
                'data' => ['message' => 'Payment was not completed on the hosted page. Please try again.'],
            ];
        }

        $response = $this->elavon->createSaleTransaction(
            $this->makeTransactionCreateBody($session, $amountNok, null)
        );

        if (! $response->isSuccess() || ! $this->transactionIsApproved($response)) {
            $message = $this->extractFailureMessage($response);

            Log::warning('Elavon shop subscription: server-side signup charge failed', [
                'shop_id' => $this->shop->id,
                'session_id' => $session->getId(),
                'message' => $message,
                'response_body' => $response->getRawResponseBody(),
            ]);

            return [
                'status' => false,
                'data' => ['message' => $message !== '' ? $message : 'Payment was not approved.'],
            ];
        }

        return [
            'status' => true,
            'data' => ['transactionId' => $response->getId()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function makeTransactionCreateBody(PaymentSessionResponse $session, float $amountNok, ?string $storedCardId): array
    {
        $body = [
            'type' => 'sale',
            'total' => (object) [
                'amount' => $amountNok,
                'currencyCode' => 'NOK',
            ],
            'doCapture' => true,
            'shopperInteraction' => 'ecommerce',
            'shipTo' => $this->billTo(),
            'shopperEmailAddress' => $this->shopperEmail(),
            'doSendReceipt' => false,
            'shopperIpAddress' => request()->ip() ?? '127.0.0.1',
            'shopperReference' => (string) $this->shop->id,
            'shopperStatement' => [
                'name' => $this->shopperFullName(),
                'phone' => (string) ($this->shop->contact_phone ?: $this->shop->user->phone ?? ''),
                'url' => '',
            ],
            'description' => sprintf('Shop subscription — %s', $this->shop->user_name),
            'shopperLanguageTag' => app()->getLocale(),
            'shopperTimeZone' => config('app.timezone'),
            'customFields' => [
                'vendor_id' => config('app.name'),
                'shop_id' => (string) $this->shop->id,
            ],
            'createdBy' => config('app.name'),
            'orderReference' => (string) $this->shop->id,
            'order' => $this->entityIdFromHref($session->getOrder()),
        ];

        if ($storedCardId !== null && $storedCardId !== '') {
            $body['storedCard'] = $this->convergeResourceUrl(Endpoint::STORED_CARD, $storedCardId);
        } else {
            $body['hostedCard'] = $this->entityIdFromHref((string) $session->getHostedCard());
        }

        $threeDS = $session->getThreeDSecure();
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

    public function transactionIsApproved(ResponseInterface $transaction): bool
    {
        if (! $transaction->isSuccess()) {
            return false;
        }

        if (! method_exists($transaction, 'getState')) {
            return true;
        }

        $state = $transaction->getState();
        if ($state === null) {
            return false;
        }

        return $state->isAuthorized() || $state->isCaptured() || $state->isSettled();
    }

    protected function entityIdFromHref(?string $href): string
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

    /**
     * @return array{status: bool, code: int, data: array{message: string}}
     */
    protected function failureFromResponse(ResponseInterface $response, string $stage): array
    {
        $message = $this->extractFailureMessage($response);

        Log::warning('Elavon shop subscription HPP failed', [
            'stage' => $stage,
            'shop_id' => $this->shop->id,
            'credential_source' => $this->credentialSource(),
            'sandbox' => $this->keys['sandbox'],
            'origin_url' => rtrim((string) config('app.url'), '/'),
            'status_code' => $response->getRawResponseStatusCode(),
            'message' => $message,
            'response_body' => $response->getRawResponseBody(),
        ]);

        return [
            'status' => false,
            'code' => $response->getRawResponseStatusCode() ?: 500,
            'data' => ['message' => $message],
        ];
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

        if ($parts === []) {
            $shortError = $response->getShortErrorMessage();
            if (is_string($shortError) && $shortError !== '') {
                $parts[] = $shortError;
            }
        }

        if ($parts === []) {
            $rawError = $response->getRawErrorMessage();
            if (is_string($rawError) && $rawError !== '') {
                $parts[] = $rawError;
            }
        }

        return $parts !== [] ? implode(' | ', $parts) : 'Elavon payment session failed.';
    }

    protected function credentialSource(): string
    {
        $merchant = (string) ($this->shop->elavon_merchant_alias ?? '');
        $public = (string) ($this->shop->elavon_public_key ?? '');
        $secret = (string) ($this->shop->elavon_secret_key ?? '');

        return $merchant !== '' && $public !== '' && $secret !== '' ? 'shop' : 'platform';
    }

    public function convergeClient(): Converge2
    {
        return $this->elavon;
    }
}
