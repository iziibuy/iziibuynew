<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExternalOrder;
use App\Models\PaymentApi;
use App\Models\PaymentMethodAccess;
use App\Payment\Elavon\ApiElavonPayment;
use App\Payment\Surfboard\SurfboardOrderApi;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IziipayController extends Controller
{
    public function createPayment(PaymentMethodAccess $paymentMethodAccess, Request $request)
    {
        // Log::info($request->all());
        $api = $paymentMethodAccess->paymentapis()->where('key', $request->source_key)->first();
        if (! $api) {
            return response()->json([
                'message' => 'source key not found',
                'status' => false,
            ], 404);
        }
        if ($api->is_subscription) {
            return response()->json([
                'message' => 'This source key is a subscription button. Use the create-subscription endpoint instead.',
                'status' => false,
            ], 400);
        }
        $request->validate([
            'name' => ['required'],
            'email' => ['required', 'email'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'organization_number' => ['nullable', 'string', 'max:255'],
        ]);

        $profileUpdates = array_filter([
            'company_name' => $request->input('company_name'),
            'company_registration' => $request->input('organization_number'),
        ], fn ($value): bool => filled($value));

        if ($profileUpdates !== []) {
            $paymentMethodAccess->update($profileUpdates);
        }

        $order = ExternalOrder::create([
            'uuid' => Str::ulid(),
            'payment_method_access_id' => $paymentMethodAccess->id,
            'api_id' => $api->id,
            'customer_name' => $request->name,
            'customer_email' => $request->email,
            'customer_phone' => $request->phone,
            'customer_country' => $request->country,
            'customer_address' => $request->address,
            'customer_post_code' => $request->post_code,
            'taxValue' => $request->taxValue,
            'taxTotal' => $request->taxTotal,
            'orderId' => $request->orderId,
            'description' => $request->description,
            'source_url' => $api->domain,
            'success_redirect_url' => $api->success_redirect_url,
            'failed_redirect_url' => $api->failed_redirect_url,
            'amount' => $request->amount,
            'currency' => $request->currency,
        ]);
        if ($paymentMethodAccess->paymentMethod == 'surfboard') {

            // if (empty($paymentMethodAccess->surfboard_merchantId) ||  empty($paymentMethodAccess->surfboard_storeId) || empty($paymentMethodAccess->surfboard_terminalId)) {
            //     return response()->json([
            //         'message' => 'Api key not found. Please contact support',
            //         'status' => false
            //     ], 400);
            // }
            $payment = (new SurfboardOrderApi($order))->getPaymentLink();
            if ($payment['status']) {
                $order->update([
                    'payment_id' => $payment['data']['payment_id'],
                    'payment_url' => $payment['data']['url'],
                    'payment_method' => 'surfboard',
                ]);

                return response()->json([
                    'url' => $order->payment_url,
                    'order' => $order,
                ]);
            } else {
                return response()->json([
                    'message' => $payment['data']['message'],
                    'status' => false,
                ], 400);
            }
        } else {
            $payment = (new ApiElavonPayment($order))->getPaymentLink();
            if (isset($payment['data']['payment_id'])) {

                $order->update([
                    'payment_id' => $payment['data']['payment_id'],
                    'payment_url' => $payment['data']['url'],
                    'payment_method' => 'elavon',
                ]);

                return response()->json([
                    'url' => $order->payment_url,
                    'order' => $order,
                ]);
            } else {
                return response()->json([
                    'message' => $payment['data']['message'],
                    'status' => false,
                ], 400);
            }
        }
    }

    public function createPaymentLink(PaymentMethodAccess $paymentMethodAccess, Request $request)
    {
        $request->validate([
            'order_id' => ['required', 'integer'],
        ]);

        $order = ExternalOrder::where('id', $request->order_id)->where('payment_method_access_id', $paymentMethodAccess->id)->first();
        if (! $order) {
            return response()->json([
                'message' => 'No order found. Please send a valid order_id',
            ]);
        }
        if ($order->status == 'COMPLETED') {
            return response()->json([
                'message' => 'Order already paid',
            ]);
        }

        if ($paymentMethodAccess->paymentMethod == 'surfboard') {

            // if (empty($paymentMethodAccess->surfboard_merchantId) ||  empty($paymentMethodAccess->surfboard_storeId) || empty($paymentMethodAccess->surfboard_terminalId)) {
            //     return response()->json([
            //         'message' => 'Api key not found. Please contact support',
            //         'status' => false
            //     ], 400);
            // }
            $payment = (new SurfboardOrderApi($order))->getPaymentLink();
            if ($payment['status']) {
                $order->update([
                    'payment_id' => $payment['data']['payment_id'],
                    'payment_url' => $payment['data']['url'],
                ]);

                return response()->json([
                    'url' => $order->payment_url,
                    'order' => $order,
                ]);
            } else {
                return response()->json([
                    'message' => $payment['data']['message'],
                    'status' => false,
                ], 400);
            }
        } else {
            $payment = (new ApiElavonPayment($order))->getPaymentLink();
            if (isset($payment['data']['payment_id'])) {

                $order->update([
                    'payment_id' => $payment['data']['payment_id'],
                    'payment_url' => $payment['data']['url'],
                ]);

                return response()->json([
                    'url' => $order->payment_url,
                    'order' => $order,
                ]);
            } else {
                return response()->json([
                    'message' => $payment['data']['message'],
                    'status' => false,
                ], 400);
            }
        }
    }

    public function cancel_surfboard_payment(PaymentMethodAccess $paymentMethodAccess, Request $request)
    {
        try {
            $request->validate([
                'order_id' => ['required', 'integer'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'code' => 400,
                'message' => 'Validation failed: order_id is required and must be an integer',
                'errors' => $e->errors(),
            ], 400);
        }

        $order = ExternalOrder::where('id', $request->order_id)
            ->where('payment_method_access_id', $paymentMethodAccess->id)
            ->first();

        if (! $order) {
            return response()->json([
                'status' => false,
                'code' => 404,
                'message' => 'Order not found. The order_id '.$request->order_id.' does not exist or does not belong to your payment method access.',
                'order_id' => $request->order_id,
            ], 404);
        }

        if ($order->payment_method !== 'surfboard') {
            return response()->json([
                'status' => false,
                'code' => 400,
                'message' => 'Cancel method is only supported for Surfboard payment method. This order uses: '.strtoupper($order->payment_method),
                'order_id' => $order->id,
                'payment_method' => $order->payment_method,
            ], 400);
        }

        // Check if order is already canceled in database
        if (strtoupper($order->status) === 'CANCELED') {
            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Order #'.$order->id.' is already canceled in the system. No action needed.',
                'order_id' => $order->id,
                'order_status' => $order->status,
                'payment_id' => $order->payment_id,
            ], 200);
        }

        // Check if payment_id exists (required for API call)
        if (! $order->payment_id) {
            return response()->json([
                'status' => false,
                'code' => 400,
                'message' => 'Cannot cancel order: Payment ID is missing. This order may not have been processed through the payment gateway yet.',
                'order_id' => $order->id,
                'order_status' => $order->status,
            ], 400);
        }

        $payment_data = (new SurfboardOrderApi($order))->cancelOrder();

        // Ensure response is array
        if (! is_array($payment_data)) {
            $payment_data = (array) $payment_data;
        }

        $status = Arr::get($payment_data, 'status', false);
        $dataMessage = Arr::get($payment_data, 'data') ?? Arr::get($payment_data, 'message');
        $code = Arr::get($payment_data, 'code', 500);

        // Normalize message to string
        if (is_array($dataMessage)) {
            $dataMessage = json_encode($dataMessage);
        } elseif (! is_string($dataMessage) && $dataMessage !== null) {
            $dataMessage = (string) $dataMessage;
        }

        // If Surfboard says payment already cancelled (status false but code 200 means already canceled)
        if ($status === false && $code == 200) {
            // Order is already canceled in API, update database to match
            $order->update([
                'status' => 'CANCELED',
            ]);

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Order #'.$order->id.' was already canceled in payment gateway. Database has been updated to reflect this status.',
                'order_id' => $order->id,
                'order_status' => $order->status,
                'payment_id' => $order->payment_id,
                'gateway_message' => $dataMessage,
            ], 200);
        }

        // Check for cancellation-related messages
        if ($status === false && is_string($dataMessage) && (
            stripos($dataMessage, 'CANCELLED') !== false ||
            stripos($dataMessage, 'CANCELED') !== false ||
            stripos($dataMessage, 'PAYMENT_CANCELLED') !== false ||
            stripos($dataMessage, 'already') !== false ||
            stripos($dataMessage, 'not found') !== false
        )) {
            // Order is already canceled in API, update database to match
            $order->update([
                'status' => 'CANCELED',
            ]);

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Order #'.$order->id.' was already canceled in payment gateway. Database has been updated to reflect this status.',
                'order_id' => $order->id,
                'order_status' => $order->status,
                'payment_id' => $order->payment_id,
                'gateway_message' => $dataMessage,
            ], 200);
        }

        // Successful cancellation
        if ($status === true) {
            $order->update([
                'status' => 'CANCELED',
            ]);

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Order #'.$order->id.' has been successfully canceled.',
                'order_id' => $order->id,
                'order_status' => $order->status,
                'payment_id' => $order->payment_id,
                'data' => Arr::get($payment_data, 'data', null),
            ], 200);
        }

        // Any other failure
        $httpCode = ($code >= 400 && $code < 600) ? $code : 400;

        return response()->json([
            'status' => false,
            'code' => $code,
            'message' => 'Failed to cancel order #'.$order->id.' in payment gateway. '.($dataMessage ?? 'Unknown error occurred'),
            'order_id' => $order->id,
            'order_status' => $order->status,
            'payment_id' => $order->payment_id,
            'gateway_error' => $dataMessage,
        ], $httpCode);
    }

    public function verify_surfboard_payment(PaymentMethodAccess $paymentMethodAccess, Request $request)
    {
        $request->validate([
            'order_id' => ['required', 'integer'],
        ]);

        $order = ExternalOrder::where('id', $request->order_id)->where('payment_method_access_id', $paymentMethodAccess->id)->first();
        if (! $order) {
            return response()->json([
                'message' => 'No order found. Please send a valid order_id',
            ]);
        }
        if ($order->payment_method == 'surfboard') {
            $payment_method = PaymentApi::where('id', $order->api_id)->first();
            $payment_data = (new SurfboardOrderApi($order))->getOrderStatus();
            $orderStatus = Arr::get($payment_data, 'data.orderStatus');

            if (in_array($orderStatus, ['PAYMENT_COMPLETED', 'PARTIAL_PAYMENT_COMPLETED'], true) && $order->status != 'COMPLETED') {
                $order->update([
                    'status' => 'COMPLETED',
                    'paid_at' => now(),
                ]);
            }

            return [
                'source_key' => $payment_method->key,
                'order_id' => $order->id,
                'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                'payment_info' => response()->json($payment_data),
                'order_status' => $order->status,
                'paid_at' => $order->paid_at,
            ];
        } else {
            return response()->json([
                'message' => 'This method is not supported for this payment method',
            ], 400);
        }
    }
}
