<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExternalSubscription;
use App\Models\PaymentMethodAccess;
use App\Payment\Elavon\ApiElavonButtonSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IziipaySubscriptionController extends Controller
{
    public function createSubscription(PaymentMethodAccess $paymentMethodAccess, Request $request)
    {
        $api = $paymentMethodAccess->paymentapis()
            ->where('key', $request->source_key)
            ->where('is_subscription', true)
            ->first();

        if (! $api) {
            return response()->json([
                'message' => 'Subscription source key not found',
                'status' => false,
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string'],
            'email' => ['required', 'email'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'max:10'],
            'interval_days' => ['required', 'integer', 'min:1'],
            'phone' => ['nullable', 'string'],
            'country' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'post_code' => ['nullable', 'string'],
            'taxValue' => ['nullable', 'string'],
            'taxTotal' => ['nullable', 'string'],
            'orderId' => ['nullable'],
            'description' => ['nullable', 'string'],
            'preferred_acquirer' => ['nullable', 'string', 'in:elavon,surfboard'],
        ]);

        $acquirer = $this->resolveAcquirer($paymentMethodAccess, $validated['preferred_acquirer'] ?? null);

        if ($acquirer === 'surfboard') {
            return response()->json([
                'message' => 'Surfboard subscription payments are coming soon. Use Elavon for now.',
                'status' => false,
            ], 400);
        }

        $subscription = ExternalSubscription::create([
            'uuid' => (string) Str::ulid(),
            'payment_method_access_id' => $paymentMethodAccess->id,
            'api_id' => $api->id,
            'customer_name' => $validated['name'],
            'customer_email' => $validated['email'],
            'customer_phone' => $validated['phone'] ?? null,
            'customer_country' => $validated['country'] ?? null,
            'customer_address' => $validated['address'] ?? null,
            'customer_post_code' => $validated['post_code'] ?? null,
            'taxValue' => $validated['taxValue'] ?? null,
            'taxTotal' => $validated['taxTotal'] ?? null,
            'orderId' => $validated['orderId'] ?? null,
            'description' => $validated['description'] ?? null,
            'amount' => $validated['amount'],
            'currency' => strtoupper($validated['currency']),
            'interval_days' => (int) $validated['interval_days'],
            'status' => 'PENDING',
            'payment_method' => 'elavon',
        ]);

        $payment = (new ApiElavonButtonSubscription($subscription))->getPaymentLink();

        if (! ($payment['status'] ?? false) || ! isset($payment['data']['payment_id'])) {
            $subscription->update(['status' => 'FAILED']);

            return response()->json([
                'message' => $payment['data']['message'] ?? 'Could not create payment link',
                'status' => false,
            ], 400);
        }

        $subscription->update([
            'payment_id' => $payment['data']['payment_id'],
            'payment_url' => $payment['data']['url'],
        ]);

        return response()->json([
            'url' => $subscription->payment_url,
            'subscription' => $subscription->fresh(),
        ]);
    }

    public function cancelSubscription(PaymentMethodAccess $paymentMethodAccess, Request $request)
    {
        $validated = $request->validate([
            'subscription_id' => ['required', 'integer'],
        ]);

        $subscription = ExternalSubscription::query()
            ->where('id', $validated['subscription_id'])
            ->where('payment_method_access_id', $paymentMethodAccess->id)
            ->first();

        if (! $subscription) {
            return response()->json([
                'status' => false,
                'message' => 'Subscription not found.',
            ], 404);
        }

        if (strtoupper((string) $subscription->status) === 'CANCELED') {
            return response()->json([
                'status' => true,
                'message' => 'Subscription already canceled.',
                'subscription' => $subscription,
            ]);
        }

        $subscription->update([
            'status' => 'CANCELED',
            'canceled_at' => now(),
            'next_charge_at' => null,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Subscription canceled.',
            'subscription' => $subscription->fresh(),
        ]);
    }

    protected function resolveAcquirer(PaymentMethodAccess $access, ?string $preferred): string
    {
        $enabled = collect(explode(',', (string) $access->paymentMethod))
            ->map(fn (string $value): string => strtolower(trim($value)))
            ->filter()
            ->values();

        if ($preferred && $enabled->contains(strtolower($preferred))) {
            return strtolower($preferred);
        }

        if ($enabled->contains('elavon')) {
            return 'elavon';
        }

        if ($enabled->contains('surfboard')) {
            return 'surfboard';
        }

        return 'elavon';
    }
}
