<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExternalOrder;
use App\Payment\Elavon\ApiElavonPayment;
use App\Payment\Surfboard\SurfboardOrderApi;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExternalOrderGatewayInspector
{
    /**
     * Fetch live gateway details for admin analysis.
     *
     * @return array{
     *     provider: string,
     *     success: bool,
     *     summary: array<string, mixed>,
     *     local: array<string, mixed>,
     *     gateway: array<string, mixed>,
     *     error: ?string
     * }
     */
    public function inspect(ExternalOrder $order): array
    {
        $order->loadMissing(['paymentMethodAccess', 'paymentApi']);

        $local = [
            'id' => $order->id,
            'uuid' => $order->uuid,
            'status' => $order->status,
            'payment_method' => $order->payment_method,
            'payment_id' => $order->payment_id,
            'amount' => $order->amount,
            'currency' => $order->currency,
            'orderId' => $order->orderId,
            'response' => $order->response,
            'paid_at' => $order->paid_at?->toDateTimeString(),
            'plugin' => $order->paymentMethodAccess?->company_name,
            'plugin_id' => $order->payment_method_access_id,
            'api_domain' => $order->paymentApi?->domain,
            'api_id' => $order->api_id,
        ];

        $method = strtolower((string) $order->payment_method);

        try {
            return match ($method) {
                'surfboard' => $this->inspectSurfboard($order, $local),
                'elavon' => $this->inspectElavon($order, $local),
                default => [
                    'provider' => $method !== '' ? $method : 'unknown',
                    'success' => false,
                    'summary' => [
                        'message' => 'No live gateway inspector for this payment method.',
                    ],
                    'local' => $local,
                    'gateway' => [],
                    'error' => 'Unsupported payment method for API inspection.',
                ],
            };
        } catch (Throwable $e) {
            Log::warning('External order gateway inspect failed', [
                'external_order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'provider' => $method !== '' ? $method : 'unknown',
                'success' => false,
                'summary' => ['message' => 'Gateway request failed.'],
                'local' => $local,
                'gateway' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $local
     * @return array{provider:string,success:bool,summary:array<string,mixed>,local:array<string,mixed>,gateway:array<string,mixed>,error:?string}
     */
    protected function inspectSurfboard(ExternalOrder $order, array $local): array
    {
        if (blank($order->payment_id)) {
            return [
                'provider' => 'surfboard',
                'success' => false,
                'summary' => ['message' => 'No Surfboard payment_id on this order.'],
                'local' => $local,
                'gateway' => [],
                'error' => 'Missing payment_id.',
            ];
        }

        $api = new SurfboardOrderApi($order);
        $status = $api->getOrderStatus();
        $payment = null;

        $paymentId = data_get($status, 'data.paymentId')
            ?? data_get($status, 'data.payments.0.paymentId')
            ?? $order->surfboard_transaction_id
            ?? null;

        if (filled($paymentId)) {
            $payment = $api->getPayment((string) $paymentId);
        }

        $orderStatus = data_get($status, 'data.orderStatus');

        return [
            'provider' => 'surfboard',
            'success' => true,
            'summary' => [
                'order_status' => $orderStatus,
                'payment_id' => $paymentId,
                'matches_local' => strtoupper((string) $order->status) === 'COMPLETED'
                    && in_array($orderStatus, ['PAYMENT_COMPLETED', 'PARTIAL_PAYMENT_COMPLETED'], true),
            ],
            'local' => $local,
            'gateway' => [
                'order_status' => $status,
                'payment' => $payment,
            ],
            'error' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $local
     * @return array{provider:string,success:bool,summary:array<string,mixed>,local:array<string,mixed>,gateway:array<string,mixed>,error:?string}
     */
    protected function inspectElavon(ExternalOrder $order, array $local): array
    {
        $payment = new ApiElavonPayment($order);
        $gateway = $payment->inspectGateway();

        return [
            'provider' => 'elavon',
            'success' => (bool) ($gateway['success'] ?? false),
            'summary' => $gateway['summary'] ?? [],
            'local' => $local,
            'gateway' => $gateway['raw'] ?? [],
            'error' => $gateway['error'] ?? null,
        ];
    }
}
