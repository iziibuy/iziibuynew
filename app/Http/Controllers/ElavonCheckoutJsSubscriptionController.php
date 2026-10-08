<?php

namespace App\Http\Controllers;

use App\Models\ExternalSubscription;
use App\Payment\Elavon\ApiElavonButtonSubscription;
use App\Payment\Elavon\CheckoutJsTheme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ElavonCheckoutJsSubscriptionController extends Controller
{
    public function show(string $uuid): View|RedirectResponse
    {
        $subscription = $this->findSubscription($uuid);

        if ($subscription->isActive()) {
            return redirect()->away($this->redirectUrl($subscription, 'active'));
        }

        if (! in_array(strtoupper((string) $subscription->status), ['PENDING', 'FAILED'], true)) {
            abort(404);
        }

        $elavon = $this->gateway($subscription);

        if (! $elavon->usesCheckoutJs()) {
            abort(404);
        }

        $session = $elavon->ensureCheckoutJsSession();

        if (! ($session['status'] ?? false)) {
            abort(422, $session['message'] ?? 'Unable to prepare CheckoutJS payment session.');
        }

        $subscription->refresh();
        $access = $subscription->paymentMethodAccess;
        $pluginLogo = $access?->logo;
        $theme = CheckoutJsTheme::fromApi($subscription->paymentApi);

        return view('payments.elavon-checkoutjs', [
            'order' => (object) [
                'amount' => $subscription->amount,
                'currency' => $subscription->currency ?: 'NOK',
                'description' => $subscription->description,
                'orderId' => $subscription->orderId,
                'customer_name' => (string) $subscription->customer_name,
                'customer_email' => $subscription->customer_email,
                'customer_address' => $subscription->customer_address,
                'customer_post_code' => $subscription->customer_post_code,
                'city' => null,
                'customer_phone' => $subscription->customer_phone,
            ],
            'sessionId' => $session['payment_id'] ?? $subscription->payment_id,
            'hostedFieldsScriptUrl' => $elavon->hostedFieldsScriptUrl(),
            'companyName' => $theme->companyName($access?->company_name ?: (string) config('app.name')),
            'companyLogo' => $theme->logoUrl(is_string($pluginLogo) && $pluginLogo !== '' ? $pluginLogo : null),
            'checkoutTheme' => $theme,
            'subscriptionIntervalDays' => max(1, (int) $subscription->interval_days),
            'completeUrl' => route('elavon.checkoutjs.subscription.complete', $subscription->uuid),
            'cancelUrl' => route('elavon.checkoutjs.subscription.cancel', $subscription->uuid),
        ]);
    }

    public function complete(Request $request, string $uuid): JsonResponse
    {
        $subscription = $this->findSubscription($uuid);

        $validated = $request->validate([
            'session_id' => ['required', 'string'],
        ]);

        try {
            $result = $this->gateway($subscription)->finalizeCheckoutJsSubscription($validated['session_id']);
        } catch (\Throwable $e) {
            Log::error('Elavon CheckoutJS subscription signup failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            $subscription->update(['payment_id' => null]);

            $result = ['status' => false, 'message' => 'Card could not be saved. Please try again.'];
        }

        return response()->json([
            'status' => $result['status'],
            'message' => $result['message'],
            'redirect_url' => $result['status'] ? $this->redirectUrl($subscription, 'active') : null,
        ], $result['status'] ? 200 : 422);
    }

    public function cancel(string $uuid): RedirectResponse
    {
        $subscription = $this->findSubscription($uuid);

        if (in_array(strtoupper((string) $subscription->status), ['PENDING', 'FAILED'], true)) {
            $subscription->update([
                'status' => 'CANCELED',
                'canceled_at' => now(),
                'next_charge_at' => null,
            ]);
        }

        return redirect()->away($this->redirectUrl($subscription, 'canceled'));
    }

    protected function findSubscription(string $uuid): ExternalSubscription
    {
        return ExternalSubscription::query()
            ->with(['paymentMethodAccess', 'paymentApi'])
            ->where('uuid', $uuid)
            ->where('payment_method', 'elavon')
            ->firstOrFail();
    }

    protected function gateway(ExternalSubscription $subscription): ApiElavonButtonSubscription
    {
        return app(ApiElavonButtonSubscription::class, ['subscription' => $subscription]);
    }

    protected function redirectUrl(ExternalSubscription $subscription, string $status): string
    {
        $api = $subscription->paymentApi;
        $base = $status === 'active'
            ? ($api?->success_redirect_url ?? '/')
            : ($api?->failed_redirect_url ?? '/');

        return $base.'?subscription='.$subscription->id.'&order='.$subscription->orderId.'&status='.$status;
    }
}
