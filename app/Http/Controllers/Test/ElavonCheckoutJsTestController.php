<?php

namespace App\Http\Controllers\Test;

use App\Http\Controllers\Controller;
use App\Models\ExternalOrder;
use App\Models\PaymentApi;
use App\Models\PaymentMethodAccess;
use App\Models\User;
use App\Payment\Elavon\ApiElavonPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ElavonCheckoutJsTestController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureAllowed();

        $plugins = PaymentMethodAccess::query()
            ->where('paymentMethod', 'elavon')
            ->latest('id')
            ->get()
            ->filter(fn (PaymentMethodAccess $access): bool => filled($access->elavon_merchant_alias)
                && filled($access->elavon_public_key)
                && filled($access->elavon_secret_key))
            ->values();

        $sessionOrder = $this->sessionOrder();

        return view('test.elavon-checkoutjs', [
            'plugins' => $plugins,
            'sessionOrder' => $sessionOrder,
            'selectedPluginId' => (int) $request->query('plugin', $sessionOrder?->payment_method_access_id ?? $plugins->first()?->id),
            'amount' => $request->query('amount', '10.00'),
            'currency' => strtoupper((string) $request->query('currency', 'NOK')),
            'originUrl' => rtrim((string) (config('services.enterprise_elavon.hpp_origin_url') ?: config('app.url')), '/'),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $this->ensureAllowed();

        $validated = $request->validate([
            'payment_method_access_id' => ['required', 'integer', 'exists:payment_method_accesses,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
        ]);

        $access = PaymentMethodAccess::query()->findOrFail($validated['payment_method_access_id']);

        if ($access->paymentMethod !== 'elavon') {
            return back()->withErrors('Selected plugin is not configured for Elavon.');
        }

        if (blank($access->elavon_merchant_alias) || blank($access->elavon_public_key) || blank($access->elavon_secret_key)) {
            return back()->withErrors('Selected plugin is missing Elavon API keys.');
        }

        $api = $this->resolveCheckoutJsApi($access);
        $publicId = (string) Str::ulid();
        $orderId = 'CHKJS-TEST-'.strtoupper(Str::random(6));

        $attributes = [
            'payment_method_access_id' => $access->id,
            'api_id' => $api->id,
            'customer_name' => $validated['customer_name'] ?: 'CheckoutJS Tester',
            'customer_email' => $validated['customer_email'] ?: 'checkoutjs-test@example.com',
            'customer_phone' => '00000000',
            'customer_address' => 'Test Street 1',
            'customer_post_code' => '0150',
            'source_url' => $api->domain,
            'success_redirect_url' => route('test.elavon.checkoutjs.success'),
            'failed_redirect_url' => route('test.elavon.checkoutjs.failed'),
            'amount' => $validated['amount'],
            'currency' => strtoupper($validated['currency']),
            'status' => 'PENDING',
            'payment_method' => 'elavon',
            'orderId' => $orderId,
            'description' => 'CheckoutJS temporary test payment',
        ];

        if (Schema::hasColumn('external_orders', 'uuid')) {
            $attributes['uuid'] = $publicId;
        }

        if (Schema::hasColumn('external_orders', 'ulid')) {
            $attributes['ulid'] = $publicId;
        }

        $order = ExternalOrder::query()->create($attributes);

        $payment = (new ApiElavonPayment($order))->getPaymentLink();

        if (! ($payment['status'] ?? false) || blank($payment['data']['payment_id'] ?? null)) {
            $order->update(['status' => 'FAILED']);

            return back()->withErrors($payment['data']['message'] ?? 'Unable to create Elavon CheckoutJS session.');
        }

        $order->update([
            'payment_id' => $payment['data']['payment_id'],
            'payment_url' => $payment['data']['url'],
            'payment_method' => 'elavon',
        ]);

        session([
            'elavon_checkoutjs_test' => [
                'order_id' => $order->id,
                'public_id' => $publicId,
                'order_number' => $orderId,
                'payment_id' => $payment['data']['payment_id'],
                'pay_url' => $payment['data']['url'],
                'plugin_id' => $access->id,
                'amount' => $order->amount,
                'currency' => $order->currency,
                'created_at' => now()->toDateTimeString(),
            ],
        ]);

        return redirect()->away($payment['data']['url']);
    }

    public function success(Request $request): View
    {
        $this->ensureAllowed();

        return view('test.elavon-checkoutjs-result', [
            'outcome' => 'success',
            'query' => $request->query(),
            'sessionOrder' => $this->sessionOrder(),
            'sessionPayload' => session('elavon_checkoutjs_test'),
        ]);
    }

    public function failed(Request $request): View
    {
        $this->ensureAllowed();

        return view('test.elavon-checkoutjs-result', [
            'outcome' => 'failed',
            'query' => $request->query(),
            'sessionOrder' => $this->sessionOrder(),
            'sessionPayload' => session('elavon_checkoutjs_test'),
        ]);
    }

    protected function ensureAllowed(): void
    {
        if (app()->environment('local', 'testing', 'staging') || (bool) config('app.debug')) {
            return;
        }

        $user = auth()->user();

        if ($user instanceof User && $user->isAdmin()) {
            return;
        }

        abort(404);
    }

    protected function resolveCheckoutJsApi(PaymentMethodAccess $access): PaymentApi
    {
        $existing = PaymentApi::query()
            ->where('payment_method_access_id', $access->id)
            ->where('elavon_link_mode', PaymentApi::ELAVON_LINK_MODE_CHECKOUTJS)
            ->where('is_subscription', false)
            ->latest('id')
            ->first();

        if ($existing) {
            $existing->update([
                'domain' => rtrim((string) config('app.url'), '/'),
                'success_redirect_url' => route('test.elavon.checkoutjs.success'),
                'failed_redirect_url' => route('test.elavon.checkoutjs.failed'),
                'status' => true,
            ]);

            return $existing->fresh();
        }

        return PaymentApi::query()->create([
            'payment_method_access_id' => $access->id,
            'key' => PaymentApi::generateKey(),
            'domain' => rtrim((string) config('app.url'), '/'),
            'success_redirect_url' => route('test.elavon.checkoutjs.success'),
            'failed_redirect_url' => route('test.elavon.checkoutjs.failed'),
            'status' => true,
            'is_subscription' => false,
            'elavon_link_mode' => PaymentApi::ELAVON_LINK_MODE_CHECKOUTJS,
        ]);
    }

    protected function sessionOrder(): ?ExternalOrder
    {
        $payload = session('elavon_checkoutjs_test');

        if (! is_array($payload) || blank($payload['order_id'] ?? null)) {
            return null;
        }

        return ExternalOrder::query()
            ->with(['paymentMethodAccess', 'paymentApi'])
            ->find($payload['order_id']);
    }
}
