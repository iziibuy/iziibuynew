<?php

declare(strict_types=1);

namespace App\Payment\Elavon;

use App\Elavon\Converge2\Client\ClientConfig;
use App\Elavon\Converge2\Converge2;
use App\Elavon\Converge2\DataObject\Resource\Endpoint;
use App\Elavon\Converge2\Response\OrderResponse;
use App\Elavon\Converge2\Response\PaymentSessionResponse;
use App\Elavon\Converge2\Response\ResponseInterface;
use App\Models\Enterprise;
use Illuminate\Support\Facades\Log;

class ElavonEnterpriseHostedSubscription
{
    public string $endpoint;

    protected Converge2 $elavon;

    protected string $apiBase;

    /** @var array{mercahantAlias:string,publicKey:string,secretKey:string,sandbox:bool} */
    protected array $keys;

    public function __construct(protected Enterprise $enterprise)
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

    public function createsTransactionOnHostedPage(): bool
    {
        if ((bool) config('services.enterprise_elavon.charge_on_server', false)) {
            return false;
        }

        return false;
    }

    /** @return array{mercahantAlias:string,publicKey:string,secretKey:string,sandbox:bool} */
    protected function resolveKeys(): array
    {
        return [
            'mercahantAlias' => str_replace(' ', '', (string) config('services.enterprise_elavon.merchant_alias', '')),
            'publicKey' => str_replace(' ', '', (string) config('services.enterprise_elavon.public_key', '')),
            'secretKey' => str_replace(' ', '', (string) config('services.enterprise_elavon.secret_key', '')),
            'sandbox' => (bool) config('services.enterprise_elavon.sandbox'),
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
        $raw = $this->enterprise->domain ?? '';
        $host = parse_url($raw, PHP_URL_HOST) ?: preg_replace('#^https?://#i', '', rtrim($raw, '/'));
        $host = $host ?: 'localhost';

        return 'enterprise-'.$this->enterprise->unqid.'@'.$host;
    }

    /** @return array<string, mixed> */
    protected function billTo(): array
    {
        return [
            'fullName' => $this->enterprise->enterprise_name ?? 'Enterprise',
            'company' => $this->enterprise->enterprise_name ?? '',
            'postalCode' => '0000',
            'street1' => $this->enterprise->domain ?? 'N/A',
            'street2' => '',
            'city' => 'Oslo',
            'countryCode' => 'NOR',
            'primaryPhone' => '00000000',
            'email' => $this->shopperEmail(),
        ];
    }

    protected function threeDSecureEnabled(): bool
    {
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

    /** @return array<string, mixed> */
    protected function getShopperCreateBody(): array
    {
        return [
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
            'email' => $this->shopperEmail(),
        ];
    }

    protected function resolveShopperReferenceForSession(): ?string
    {
        if (filled($this->enterprise->elavon_shopper_id)) {
            return $this->convergeResourceUrl(Endpoint::SHOPPER, (string) $this->enterprise->elavon_shopper_id);
        }

        $shopper = $this->elavon->createShopper($this->getShopperCreateBody());
        if (! $shopper->isSuccess()) {
            Log::warning('Elavon enterprise HPP: could not create shopper before payment session', [
                'enterprise_id' => $this->enterprise->id,
            ]);

            return null;
        }

        $shopperId = $shopper->getId();
        if ($shopperId) {
            $this->enterprise->elavon_shopper_id = $shopperId;
            $this->enterprise->save();
        }

        $href = $shopper->getHref();

        return $href ? (string) $href : ($shopperId ? $this->convergeResourceUrl(Endpoint::SHOPPER, $shopperId) : null);
    }

    /** @return array<string, mixed> */
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
                'enterprise_uid' => (string) $this->enterprise->unqid,
            ],
            'doCreateTransaction' => $this->createsTransactionOnHostedPage(),
            'doThreeDSecure' => $this->threeDSecureEnabled() ? 1 : 0,
            'hppType' => 'fullPageRedirect',
        ];
    }

    /** @return array<string, mixed> */
    protected function makeOrderCreateBody(float $amountNok): array
    {
        return [
            'total' => (object) [
                'amount' => round($amountNok, 2),
                'currencyCode' => 'NOK',
            ],
            'description' => sprintf('Enterprise subscription — %s', $this->enterprise->enterprise_name ?? $this->enterprise->unqid),
            'items' => null,
            'shipTo' => $this->billTo(),
            'shopperEmailAddress' => $this->shopperEmail(),
            'shopperReference' => (string) $this->enterprise->unqid,
            'customFields' => [
                'vendor_id' => config('app.name'),
                'vendor_app_name' => config('app.name'),
                'vendor_app_version' => '1.0.0',
                'enterprise_uid' => (string) $this->enterprise->unqid,
            ],
        ];
    }

    /** @return array{status:bool,code?:int,data:array<string,mixed>} */
    public function getPaymentLink(float $amountNok, string $returnUrl, string $cancelUrl): array
    {
        if ($this->keys['mercahantAlias'] === '' || $this->keys['publicKey'] === '' || $this->keys['secretKey'] === '') {
            Log::warning('Elavon enterprise: missing platform credentials (services.enterprise_elavon).');

            return [
                'status' => false,
                'code' => 400,
                'data' => ['message' => 'Elavon enterprise payment is not configured.'],
            ];
        }

        $amountNok = round($amountNok, 2);

        $order_create_response = $this->elavon->createOrder($this->makeOrderCreateBody($amountNok));
        if (! $order_create_response->isSuccess()) {
            return $this->failureFromResponse($order_create_response, 'order');
        }

        $this->resolveShopperReferenceForSession();

        $session_body = $this->makePaymentSessionCreateBody($order_create_response, $returnUrl, $cancelUrl);
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

    /** @return array{status: bool, data: array<string, mixed>} */
    public function chargeSignupFeeWithStoredCard(PaymentSessionResponse $session, float $amountNok, string $storedCardId): array
    {
        $response = $this->elavon->createSaleTransaction(
            $this->makeTransactionCreateBody($session, $amountNok, $storedCardId)
        );

        if (! $response->isSuccess() || ! $this->transactionIsApproved($response)) {
            return [
                'status' => false,
                'data' => ['message' => $this->extractFailureMessage($response) ?: 'Payment was not approved.'],
            ];
        }

        return [
            'status' => true,
            'data' => ['transactionId' => $response->getId()],
        ];
    }

    /** @return array{status: bool, data: array<string, mixed>} */
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

        if (! $session->getHostedCard()) {
            return [
                'status' => false,
                'data' => ['message' => 'Payment was not completed on the hosted page. Please try again.'],
            ];
        }

        $response = $this->elavon->createSaleTransaction(
            $this->makeTransactionCreateBody($session, $amountNok, null)
        );

        if (! $response->isSuccess() || ! $this->transactionIsApproved($response)) {
            return [
                'status' => false,
                'data' => ['message' => $this->extractFailureMessage($response) ?: 'Payment was not approved.'],
            ];
        }

        return [
            'status' => true,
            'data' => ['transactionId' => $response->getId()],
        ];
    }

    /** @return array<string, mixed> */
    protected function makeTransactionCreateBody(PaymentSessionResponse $session, float $amountNok, ?string $storedCardId): array
    {
        $body = [
            'type' => 'sale',
            'total' => (object) [
                'amount' => round($amountNok, 2),
                'currencyCode' => 'NOK',
            ],
            'doCapture' => true,
            'shopperInteraction' => 'ecommerce',
            'shipTo' => $this->billTo(),
            'shopperEmailAddress' => $this->shopperEmail(),
            'doSendReceipt' => false,
            'shopperIpAddress' => request()->ip() ?? '127.0.0.1',
            'shopperReference' => (string) $this->enterprise->unqid,
            'shopperStatement' => [
                'name' => $this->enterprise->enterprise_name ?? 'Enterprise',
                'phone' => '',
                'url' => $this->enterprise->domain ?? '',
            ],
            'description' => sprintf('Enterprise subscription — %s', $this->enterprise->enterprise_name ?? $this->enterprise->unqid),
            'shopperLanguageTag' => app()->getLocale(),
            'shopperTimeZone' => config('app.timezone'),
            'customFields' => [
                'vendor_id' => config('app.name'),
                'enterprise_uid' => (string) $this->enterprise->unqid,
            ],
            'createdBy' => config('app.name'),
            'orderReference' => (string) $this->enterprise->unqid,
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

    /** @return array{status: bool, code: int, data: array{message: string}} */
    protected function failureFromResponse(ResponseInterface $response, string $stage): array
    {
        $message = $this->extractFailureMessage($response);

        Log::warning('Elavon enterprise HPP failed', [
            'stage' => $stage,
            'enterprise_id' => $this->enterprise->id,
            'message' => $message,
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

        return $parts !== [] ? implode(' | ', $parts) : 'Elavon payment session failed.';
    }

    public function convergeClient(): Converge2
    {
        return $this->elavon;
    }
}
