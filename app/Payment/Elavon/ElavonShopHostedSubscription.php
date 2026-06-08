<?php

namespace App\Payment\Elavon;

use App\Elavon\Converge2\Client\ClientConfig;
use App\Elavon\Converge2\Converge2;
use App\Elavon\Converge2\Response\OrderResponse;
use App\Elavon\Converge2\Response\ResponseInterface;
use App\Models\Shop;
use Illuminate\Support\Facades\Log;

class ElavonShopHostedSubscription
{
    public string $endpoint;

    protected Converge2 $elavon;

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

        $this->elavon = new Converge2($this->config());
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

    /**
     * @return array<string, mixed>
     */
    protected function billTo(): array
    {
        return [
            'fullName' => $this->shop->user->fullName,
            'company' => (string) ($this->shop->company_name ?? ''),
            'postalCode' => (string) ($this->shop->post_code ?? ''),
            'street1' => (string) ($this->shop->street ?? ''),
            'street2' => '',
            'city' => (string) ($this->shop->city ?? ''),
            'countryCode' => 'NOR',
            'primaryPhone' => (string) ($this->shop->contact_phone ?: $this->shop->user->phone ?? ''),
            'email' => $this->shopperEmail(),
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
            'shipTo' => [
                'fullName' => $this->shop->user->fullName,
                'company' => (string) ($this->shop->company_name ?? ''),
                'postalCode' => (string) ($this->shop->post_code ?? ''),
                'street1' => (string) ($this->shop->street ?? ''),
                'street2' => '',
                'city' => (string) ($this->shop->city ?? ''),
                'countryCode' => 'NOR',
                'primaryPhone' => (string) ($this->shop->contact_phone ?: $this->shop->user->phone ?? ''),
                'email' => $this->shopperEmail(),
            ],
            'shopperEmailAddress' => $this->shopperEmail(),
            'shopperReference' => (string) $this->shop->id,
            'customFields' => [
                'vendor_id' => env('APP_NAME'),
                'vendor_app_name' => env('APP_NAME'),
                'vendor_app_version' => '1.0.0',
                'php_version' => phpversion(),
            ],
        ];
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
            'originUrl' => url('/'),
            'defaultLanguageTag' => 'en-US',
            'customFields' => [
                'vendor_id' => env('APP_NAME'),
                'vendor_app_name' => env('APP_NAME'),
                'vendor_app_version' => '1.0.0',
                'php_version' => phpversion(),
            ],
            // Vault + 3DS on HPP; capture the subscription in-app (confirmSubscription → chargeViaCard).
            // If true, Converge charges here and confirmSubscription charges again — duplicate sale / declines.
            'doCreateTransaction' => false,
            'doThreeDSecure' => 1,
            'hppType' => 'fullPageRedirect',
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

        $order_create_response = $this->elavon->createOrder($this->makeOrderCreateBody($amountNok));
        if (! $order_create_response->isSuccess()) {
            return $this->failureFromResponse($order_create_response, 'order');
        }

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
            'origin_url' => url('/'),
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
