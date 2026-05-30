<?php

namespace App\Services\Surfboard;

use App\Payment\Surfboard\SurfboardPayment;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class SurfboardApiClient
{
    public function __construct(
        protected SurfboardPayment $surfboard,
    ) {}

    /**
     * @return array{http_status: int, body: array<string, mixed>|null}
     */
    public function getPayment(string $paymentId): array
    {
        return $this->get("/payments/{$paymentId}");
    }

    /**
     * @return array{http_status: int, body: array<string, mixed>|null}
     */
    public function getOrder(string $orderId): array
    {
        return $this->get("/orders/{$orderId}");
    }

    /**
     * @return array{http_status: int, body: array<string, mixed>|null}
     */
    public function getOrderStatus(string $orderId): array
    {
        return $this->get("/orders/{$orderId}/status");
    }

    /**
     * @return array{http_status: int, body: array<string, mixed>|null}
     */
    protected function get(string $path): array
    {
        if ($this->surfboard->merchantId === '' || $this->surfboard->merchantId === '0') {
            throw new InvalidArgumentException('Surfboard merchant ID is required.');
        }

        $url = rtrim($this->surfboard->apiUrl, '/').$path;

        $response = Http::withHeaders([
            'API-KEY' => $this->surfboard->apiKey,
            'API-SECRET' => $this->surfboard->apiSecret,
            'MERCHANT-ID' => $this->surfboard->merchantId,
            'Content-Type' => 'application/json',
        ])->get($url);

        return [
            'http_status' => $response->status(),
            'body' => $this->decodeResponse($response),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function decodeResponse(Response $response): ?array
    {
        $json = $response->json();

        return is_array($json) ? $json : null;
    }
}
