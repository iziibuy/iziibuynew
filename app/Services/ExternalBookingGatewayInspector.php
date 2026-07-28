<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExternalBooking;
use App\Payment\External\Elavon\ExternalBookingElavonPayment;
use App\Payment\External\Surfboard\ExternalBookingSurfboardApi;
use App\Support\ExternalPaymentAcquirer;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExternalBookingGatewayInspector
{
    /**
     * @return array{
     *     provider: string,
     *     success: bool,
     *     summary: array<string, mixed>,
     *     local: array<string, mixed>,
     *     gateway: array<string, mixed>,
     *     error: ?string
     * }
     */
    public function inspect(ExternalBooking $booking): array
    {
        $booking->loadMissing(['paymentMethodAccess']);

        $local = [
            'id' => $booking->id,
            'ulid' => $booking->ulid,
            'booking_number' => $booking->booking_number,
            'phone_number' => $booking->phone_number,
            'status' => $booking->status,
            'payment_status' => $booking->payment_status,
            'payment_method' => $booking->payment_method,
            'payment_id' => $booking->payment_id,
            'subtotal' => $booking->subtotal,
            'tax' => $booking->tax,
            'total' => $booking->total,
            'currency' => $booking->currency,
            'paid_at' => $booking->paid_at?->toDateTimeString(),
            'plugin' => $booking->paymentMethodAccess?->company_name,
            'plugin_id' => $booking->payment_method_access_id,
            'customer_details' => $booking->customer_details,
            'service_details' => $booking->service_details,
            'elavon_transaction_id' => $booking->metas()
                ->where('column_name', 'elavon_transaction_id')
                ->value('column_value'),
        ];

        $method = ExternalPaymentAcquirer::forBooking($booking);
        $local['resolved_payment_method'] = $method;

        try {
            return match ($method) {
                'surfboard' => $this->inspectSurfboard($booking, $local),
                'elavon' => $this->inspectElavon($booking, $local),
                default => [
                    'provider' => (string) ($booking->payment_method ?: 'unknown'),
                    'success' => false,
                    'summary' => [
                        'message' => 'No live gateway inspector for this payment method.',
                        'stored_payment_method' => $booking->payment_method,
                        'resolved_payment_method' => $method,
                    ],
                    'local' => $local,
                    'gateway' => [],
                    'error' => 'Unsupported payment method for API inspection.',
                ],
            };
        } catch (Throwable $e) {
            Log::warning('External booking gateway inspect failed', [
                'external_booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'provider' => $method,
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
    protected function inspectSurfboard(ExternalBooking $booking, array $local): array
    {
        if (blank($booking->payment_id)) {
            return [
                'provider' => 'surfboard',
                'success' => false,
                'summary' => ['message' => 'No Surfboard payment_id on this booking.'],
                'local' => $local,
                'gateway' => [],
                'error' => 'Missing payment_id.',
            ];
        }

        $api = new ExternalBookingSurfboardApi($booking);
        $status = $api->getOrderStatus();
        $transaction = $api->getTransaction();

        $orderStatus = data_get($status, 'data.orderStatus');

        return [
            'provider' => 'surfboard',
            'success' => true,
            'summary' => [
                'order_status' => $orderStatus,
                'payment_id' => $booking->payment_id,
                'matches_local_paid' => strtoupper((string) $booking->payment_status) === 'PAID'
                    && in_array($orderStatus, ['PAYMENT_COMPLETED', 'PARTIAL_PAYMENT_COMPLETED'], true),
            ],
            'local' => $local,
            'gateway' => [
                'order_status' => $status,
                'order' => $transaction,
            ],
            'error' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $local
     * @return array{provider:string,success:bool,summary:array<string,mixed>,local:array<string,mixed>,gateway:array<string,mixed>,error:?string}
     */
    protected function inspectElavon(ExternalBooking $booking, array $local): array
    {
        $payment = new ExternalBookingElavonPayment($booking);
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
