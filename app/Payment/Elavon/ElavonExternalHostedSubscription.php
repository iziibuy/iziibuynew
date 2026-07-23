<?php

declare(strict_types=1);

namespace App\Payment\Elavon;

use App\Elavon\Converge2\Client\ClientConfig;
use App\Elavon\Converge2\Converge2;
use App\Elavon\Converge2\DataObject\Resource\Endpoint;
use App\Elavon\Converge2\Response\OrderResponse;
use App\Elavon\Converge2\Response\PaymentSessionResponse;
use App\Elavon\Converge2\Response\ResponseInterface;
use App\Models\PaymentMethodAccess;
use App\Services\Elavon\ElavonOnboardingPromo;
use App\Services\Elavon\ElavonRecurringTransaction;
use App\Services\Elavon\ElavonVaultOnlyPaymentSession;
use App\Services\Elavon\PlatformElavonCredentials;
use Illuminate\Support\Facades\Log;

class ElavonExternalHostedSubscription
{
    public string $endpoint;

    protected Converge2 $elavon;

    protected string $apiBase;

    /** @var array{mercahantAlias:string,publicKey:string,secretKey:string,sandbox:bool} */
    protected array $keys;

    public function __construct(protected PaymentMethodAccess $access)
    {
        $this->access->loadMissing('user');
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
        return false;
    }

    /** @return array{mercahantAlias:string,publicKey:string,secretKey:string,sandbox:bool} */
    protected function resolveKeys(): array
    {
        return PlatformElavonCredentials::forPaymentMethodAccess($this->access);
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
        return (string) ($this->access->company_email ?: $this->access->user?->email ?: 'external@localhost');
    }

    protected function shopperPhone(): string
    {
        $addr = $this->access->company_address;

        return (string) ($addr->contact_number ?? $this->access->user?->phone ?? '00000000');
    }

    /** @return array<string, mixed> */
    protected function billTo(): array
    {
        $addr = $this->access->company_address;
        $user = $this->access->user;

        return [
            'fullName' => trim(($user?->name ?? '').' '.($user?->last_name ?? '')) ?: ($this->access->company_name ?? 'External'),
            'company' => $this->access->company_name ?? '',
            'postalCode' => (string) ($addr->zip ?? '0000'),
            'street1' => (string) ($addr->street ?? 'N/A'),
            'street2' => '',
            'city' => (string) ($addr->city ?? 'Oslo'),
            'countryCode' => 'NOR',
            'primaryPhone' => $this->shopperPhone(),
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
        $addr = $this->access->company_address;

        return [
            'fullName' => $this->billTo()['fullName'],
            'company' => $this->access->company_name ?? '',
            'primaryAddress' => [
                'street1' => (string) ($addr->street ?? 'N/A'),
                'street2' => '',
                'city' => (string) ($addr->city ?? 'Oslo'),
                'region' => (string) ($addr->city ?? 'Oslo'),
                'postalCode' => (string) ($addr->zip ?? '0000'),
                'countryCode' => 'NOR',
            ],
            'primaryPhone' => $this->shopperPhone(),
            'email' => $this->shopperEmail(),
        ];
    }

    protected function resolveShopperReferenceForSession(): ?string
    {
        if (filled($this->access->shopperId)) {
            return $this->convergeResourceUrl(Endpoint::SHOPPER, (string) $this->access->shopperId);
        }

        $shopper = $this->elavon->createShopper($this->getShopperCreateBody());
        if (! $shopper->isSuccess()) {
            Log::warning('Elavon external HPP: could not create shopper', [
                'payment_method_access_id' => $this->access->id,
            ]);

            return null;
        }

        $shopperId = $shopper->getId();
        if ($shopperId) {
            $this->access->shopperId = $shopperId;
            $this->access->save();
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
                'payment_method_access_id' => (string) $this->access->id,
            ],
            'doCreateTransaction' => $this->createsTransactionOnHostedPage(),
            'doThreeDSecure' => $this->threeDSecureEnabled() ? 1 : 0,
            'hppType' => 'fullPageRedirect',
        ];
    }

    /** @return array<string, mixed> */
    protected function makeOrderCreateBody(float $amountNok, bool $vaultOnly = false): array
    {
        $description = $vaultOnly
            ? sprintf('Card registration — %s', $this->access->company_name ?? $this->access->id)
            : sprintf('External subscription — %s', $this->access->company_name ?? $this->access->id);

        return [
            'total' => (object) [
                'amount' => round($amountNok, 2),
                'currencyCode' => 'NOK',
            ],
            'description' => $description,
            'items' => null,
            'shipTo' => $this->billTo(),
            'shopperEmailAddress' => $this->shopperEmail(),
            'shopperReference' => (string) $this->access->id,
            'customFields' => [
                'vendor_id' => config('app.name'),
                'payment_method_access_id' => (string) $this->access->id,
                'signup_type' => $vaultOnly ? 'vault_only' : 'subscription',
            ],
        ];
    }

    /** @return array{status:bool,code?:int,data:array<string,mixed>} */
    public function getPaymentLink(float $amountNok, string $returnUrl, string $cancelUrl): array
    {
        if ($this->keys['mercahantAlias'] === '' || $this->keys['publicKey'] === '' || $this->keys['secretKey'] === '') {
            return [
                'status' => false,
                'code' => 400,
                'data' => ['message' => 'Elavon subscription payment is not configured.'],
            ];
        }

        $vaultOnly = ElavonVaultOnlyPaymentSession::isVaultOnlyAmount($amountNok);
        $orderAmountNok = ElavonOnboardingPromo::hppOrderAmount($amountNok);

        $order_create_response = $this->elavon->createOrder($this->makeOrderCreateBody($orderAmountNok, $vaultOnly));
        if (! $order_create_response->isSuccess()) {
            return $this->failureFromResponse($order_create_response);
        }

        $shopperReference = $this->resolveShopperReferenceForSession();
        if ($vaultOnly && ($shopperReference === null || $shopperReference === '')) {
            return [
                'status' => false,
                'code' => 400,
                'data' => ['message' => 'Could not prepare card registration. Please try again.'],
            ];
        }

        $sessionBody = $this->makePaymentSessionCreateBody($order_create_response, $returnUrl, $cancelUrl);
        if ($vaultOnly) {
            $sessionBody = ElavonVaultOnlyPaymentSession::augmentPaymentSessionBody(
                $sessionBody,
                (string) $shopperReference
            );
        }

        $payment_session_create_response = $this->elavon->createPaymentSession($sessionBody);

        if ($payment_session_create_response->isSuccess()) {
            $sessionId = $payment_session_create_response->getId();

            Log::info('Elavon external HPP: created payment session', [
                'payment_method_access_id' => $this->access->id,
                'session_id' => $sessionId,
                'vault_only' => $vaultOnly,
                'order_amount' => $orderAmountNok,
            ]);

            return [
                'status' => true,
                'code' => 200,
                'data' => [
                    'payment_id' => $sessionId,
                    'url' => $this->endpoint.'/?merchantAlias='.$this->keys['mercahantAlias'].'&publicApiKey='.$this->keys['publicKey'].'&sessionId='.$sessionId,
                    'vault_only' => $vaultOnly,
                ],
            ];
        }

        return $this->failureFromResponse($payment_session_create_response);
    }

    /** @return array{status: bool, data: array<string, mixed>} */
    public function chargeSignupFeeWithStoredCard(PaymentSessionResponse $session, float $amountNok, string $storedCardId): array
    {
        $body = ElavonRecurringTransaction::applyFirstSetup(
            $this->makeTransactionCreateBody($session, $amountNok, $storedCardId)
        );

        $response = $this->elavon->createSaleTransaction($body);

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
            ElavonRecurringTransaction::applyFirstSetup(
                $this->makeTransactionCreateBody($session, $amountNok, null)
            )
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
    public function verifyStoredCardForSubscriptionSetup(PaymentSessionResponse $session, string $storedCardId): array
    {
        return ElavonRecurringTransaction::verifyStoredCardForSubscriptionSetup(
            $this->elavon,
            $session,
            $this->makeTransactionCreateBody($session, 0.0, $storedCardId),
            fn (ResponseInterface $transaction): bool => $this->transactionIsApproved($transaction)
        );
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
            'shipTo' => $this->billTo(),
            'shopperEmailAddress' => $this->shopperEmail(),
            'doSendReceipt' => false,
            'shopperIpAddress' => request()->ip() ?? '127.0.0.1',
            'shopperReference' => (string) $this->access->id,
            'description' => sprintf('External subscription — %s', $this->access->company_name ?? $this->access->id),
            'shopperLanguageTag' => app()->getLocale(),
            'shopperTimeZone' => config('app.timezone'),
        ];

        $orderId = $this->entityIdFromHref($session->getOrder());
        if ($orderId !== '') {
            $body['order'] = $orderId;
        }

        if ($storedCardId !== null && $storedCardId !== '') {
            $body['storedCard'] = $this->convergeResourceUrl(Endpoint::STORED_CARD, $storedCardId);
        } else {
            $body['hostedCard'] = $this->entityIdFromHref((string) $session->getHostedCard());
        }

        return ElavonRecurringTransaction::appendThreeDSecureFromSession($body, $session);
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
    protected function failureFromResponse(ResponseInterface $response): array
    {
        return [
            'status' => false,
            'code' => $response->getRawResponseStatusCode() ?: 500,
            'data' => ['message' => $this->extractFailureMessage($response)],
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

        return $parts !== [] ? implode(' | ', $parts) : 'Elavon payment session failed.';
    }

    public function convergeClient(): Converge2
    {
        return $this->elavon;
    }
}
