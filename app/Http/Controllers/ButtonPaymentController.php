<?php

namespace App\Http\Controllers;

use App\Models\ExternalOrder;
use App\Models\PaymentApi;
use App\Payment\Surfboard\SurfboardOrderApi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ButtonPaymentController extends Controller
{
    public function index()
    {
        $paymentMethodAccess = auth()->user()->paymentMethodAccess;
        $apis = PaymentApi::where('payment_method_access_id', $paymentMethodAccess->id)->get();

        return view('dashboard.external.button.index', compact('apis'));
    }

    public function edit(PaymentApi $paymentApi)
    {

        if ($paymentApi->payment_method_access_id != auth()->user()->paymentMethodAccess->id) {
            abort(403);
        }

        return view('dashboard.external.button.edit', compact('paymentApi'));
    }

    public function create()
    {
        return view('dashboard.external.button.add');
    }

    public function store(Request $request)
    {
        $request->validate([
            'success' => 'required',
            'failed' => 'required',
            'domain' => 'required',
            'cancel_callback_url' => 'nullable|url',
        ]);

        PaymentApi::create([
            'status' => 1,
            'payment_method_access_id' => auth()->user()->paymentMethodAccess->id,
            'key' => Str::ulid(),
            'domain' => $request->domain,
            'success_redirect_url' => $request->success,
            'failed_redirect_url' => $request->failed,
            'cancel_callback_url' => $request->cancel_callback_url,
        ]);

        return redirect()->route('external.buttonPayment')->with('success', 'Payment api created');
    }

    public function update(PaymentApi $paymentApi, Request $request)
    {

        if ($paymentApi->payment_method_access_id != auth()->user()->paymentMethodAccess->id) {
            abort(403);
        }
        $request->validate([
            'success' => 'required',
            'failed' => 'required',
            'domain' => 'required',
            'cancel_callback_url' => 'nullable|url',
        ]);

        $paymentApi->update([
            'domain' => $request->domain,
            'success_redirect_url' => $request->success,
            'failed_redirect_url' => $request->failed,
            'cancel_callback_url' => $request->cancel_callback_url,
        ]);

        return redirect()->route('external.buttonPayment')->with('success', 'Payment api updated');
    }

    public function view(PaymentApi $paymentApi, Request $request)
    {
        if ($paymentApi->payment_method_access_id != auth()->user()->paymentMethodAccess->id) {
            abort(403);
        }

        $filters = $request->only([
            'search',
            'paid_from',
            'paid_to',
            'status',
        ]);

        $ordersQuery = ExternalOrder::where('api_id', $paymentApi->id)
            ->when($filters['search'] ?? null, function ($query, $value) {
                $query->where(function ($query) use ($value) {
                    $query->where('customer_name', 'like', "%$value%")
                        ->orWhere('customer_email', 'like', "%$value%")
                        ->orWhere('customer_phone', 'like', "%$value%")
                        ->orWhere('customer_company', 'like', "%$value%")
                        ->orWhere('customer_country', 'like', "%$value%")
                        ->orWhere('customer_address', 'like', "%$value%")
                        ->orWhere('customer_post_code', 'like', "%$value%")
                        ->orWhere('payment_id', 'like', "%$value%")
                        ->orWhere('description', 'like', "%$value%")
                        ->orWhere('id', $value)
                        ->orWhere('orderId', 'like', "%$value%");
                });
            })
            ->when($filters['paid_from'] ?? null, function ($query, $value) {
                $query->where('paid_at', '>=', Carbon::parse($value)->startOfDay());
            })
            ->when($filters['paid_to'] ?? null, function ($query, $value) {
                $query->where('paid_at', '<=', Carbon::parse($value)->endOfDay());
            })
            ->when($filters['status'] ?? null, function ($query, $value) {
                $query->where('status', $value);
            })
            ->latest();

        $orders = $ordersQuery->paginate(20)->withQueryString();

        return view('dashboard.external.button.view', compact('paymentApi', 'orders', 'filters'));
    }

    public function docs(PaymentApi $paymentApi)
    {
        if ($paymentApi->payment_method_access_id != auth()->user()->paymentMethodAccess->id) {
            abort(403);
        }

        $paymentMethodAccess = auth()->user()->paymentMethodAccess;

        return view('dashboard.external.button.docs', compact('paymentApi', 'paymentMethodAccess'));
    }

    public function cancelOrder(PaymentApi $paymentApi, $order)
    {
        // Verify payment API belongs to the authenticated user
        if ($paymentApi->payment_method_access_id != auth()->user()->paymentMethodAccess->id) {
            abort(403);
        }

        // Find the order and verify it belongs to this payment API
        $externalOrder = ExternalOrder::where('id', $order)
            ->where('api_id', $paymentApi->id)
            ->first();

        if (! $externalOrder) {
            return redirect()->route('external.buttonPayment.view', $paymentApi)
                ->with('error', 'Order not found.');
        }

        // Check if order status is PENDING
        if (strtoupper($externalOrder->status) !== 'PENDING') {
            return redirect()->route('external.buttonPayment.view', $paymentApi)
                ->with('error', 'Only pending orders can be canceled.');
        }

        try {
            // Cancel the order based on payment method
            if ($externalOrder->payment_method == 'surfboard') {
                $externalOrder->update([
                    'status' => 'CANCELED',
                ]);
                if ($paymentApi->cancel_callback_url) {
                    $callbackPayload = [
                        'order_id' => $externalOrder->id,
                        'status' => 'CANCELED',
                        'message' => 'Order canceled successfully',
                    ];

                    Log::info('Sending cancel callback request.', [
                        'url' => $paymentApi->cancel_callback_url,
                        'payload' => $callbackPayload,
                        'external_order_id' => $externalOrder->id,
                    ]);

                    Http::timeout(10)->get($paymentApi->cancel_callback_url, $callbackPayload);

                }
                $payment_data = (new SurfboardOrderApi($externalOrder))->cancelOrder();

                if ($payment_data['status']) {

                    return redirect()->route('external.buttonPayment.view', $paymentApi)
                        ->with('success', 'Order canceled successfully.');
                } else {
                    return redirect()->route('external.buttonPayment.view', $paymentApi)
                        ->with('error', 'Failed to cancel order: '.($payment_data['data'] ?? 'Unknown error'));
                }
            } else {
                // For other payment methods, just update the status
                $externalOrder->update([
                    'status' => 'CANCELED',
                ]);

                return redirect()->route('external.buttonPayment.view', $paymentApi)
                    ->with('success', 'Order canceled successfully.');
            }
        } catch (\Exception $e) {
            return redirect()->route('external.buttonPayment.view', $paymentApi)
                ->with('error', 'An error occurred while canceling the order: '.$e->getMessage());
        }
    }

    public function cancelCallback(Request $request)
    {
        $orderId = $request->query('order_id');

        if (! $orderId) {
            return response()->json([
                'status' => false,
                'message' => 'order_id parameter is required',
            ], 400);
        }

        // Find the order by ID
        $externalOrder = ExternalOrder::find($orderId);

        if (! $externalOrder) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found',
            ], 404);
        }

        // Get the payment API
        $paymentApi = PaymentApi::find($externalOrder->api_id);

        if (! $paymentApi) {
            return response()->json([
                'status' => false,
                'message' => 'Payment API not found',
            ], 404);
        }

        // Check if order status is PENDING
        if (strtoupper($externalOrder->status) !== 'PENDING') {
            return response()->json([
                'status' => false,
                'message' => 'Only pending orders can be canceled. Current status: '.$externalOrder->status,
            ], 400);
        }

        try {
            // Cancel the order based on payment method
            if ($externalOrder->payment_method == 'surfboard') {
                $payment_data = (new SurfboardOrderApi($externalOrder))->cancelOrder();

                if ($payment_data['status']) {
                    $externalOrder->update([
                        'status' => 'CANCELED',
                    ]);

                    // Call the user's cancel callback URL if provided
                    if ($paymentApi->cancel_callback_url) {
                        try {
                            Http::timeout(10)->get($paymentApi->cancel_callback_url, [
                                'order_id' => $externalOrder->id,
                                'status' => 'CANCELED',
                                'message' => 'Order canceled successfully',
                            ]);
                        } catch (\Exception $e) {
                            // Log the error but don't fail the cancellation
                            Log::warning('Failed to call cancel callback URL: '.$e->getMessage());
                        }
                    }

                    return response()->json([
                        'status' => true,
                        'message' => 'Order canceled successfully',
                        'order_id' => $externalOrder->id,
                        'order_status' => 'CANCELED',
                    ], 200);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Failed to cancel order: '.($payment_data['data'] ?? 'Unknown error'),
                    ], 500);
                }
            } else {
                // For other payment methods, just update the status
                $externalOrder->update([
                    'status' => 'CANCELED',
                ]);

                // Call the user's cancel callback URL if provided
                if ($paymentApi->cancel_callback_url) {
                    try {
                        Http::timeout(10)->get($paymentApi->cancel_callback_url, [
                            'order_id' => $externalOrder->id,
                            'status' => 'CANCELED',
                            'message' => 'Order canceled successfully',
                        ]);
                    } catch (\Exception $e) {
                        // Log the error but don't fail the cancellation
                        Log::warning('Failed to call cancel callback URL: '.$e->getMessage());
                    }
                }

                return response()->json([
                    'status' => true,
                    'message' => 'Order canceled successfully',
                    'order_id' => $externalOrder->id,
                    'order_status' => 'CANCELED',
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while canceling the order: '.$e->getMessage(),
            ], 500);
        }
    }
}
