<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Payment\Elavon\CheckoutJsTheme;
use App\Payment\Elavon\ElavonPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ShopElavonCheckoutJsController extends Controller
{
    public function show(string $uuid): View|RedirectResponse
    {
        $order = $this->findOrder($uuid);

        if ((int) $order->status === 5 || (bool) $order->payment_status) {
            return redirect()->route('thankyou', [
                'user_name' => $order->shop->user_name,
                'order' => $order,
            ]);
        }

        if ((int) $order->status !== 0) {
            abort(404);
        }

        $shop = $order->shop;

        if (! $shop?->usesElavonCheckoutJs()) {
            abort(404);
        }

        $elavon = new ElavonPayment($order);
        $session = $elavon->ensureCheckoutJsSession();

        if (! ($session['status'] ?? false)) {
            abort(422, $session['message'] ?? 'Unable to prepare CheckoutJS payment session.');
        }

        $order = $order->fresh(['shop']);
        $theme = CheckoutJsTheme::fromShop($order->shop);
        $logo = $order->shop?->logo;
        $publicId = (string) $order->uuid;

        return view('payments.elavon-checkoutjs', [
            'order' => $this->checkoutViewOrder($order),
            'sessionId' => $session['payment_id'] ?? $order->payment_id,
            'hostedFieldsScriptUrl' => $elavon->hostedFieldsScriptUrl(),
            'companyName' => $theme->companyName($order->shop?->company_name ?: (string) config('app.name')),
            'companyLogo' => $theme->logoUrl(is_string($logo) && $logo !== '' ? $logo : null),
            'checkoutTheme' => $theme,
            'completeUrl' => route('elavon.checkoutjs.shop.complete', $publicId),
            'cancelUrl' => route('elavon.checkoutjs.shop.cancel', $publicId),
        ]);
    }

    public function complete(Request $request, string $uuid): JsonResponse
    {
        $order = $this->findOrder($uuid);

        $validated = $request->validate([
            'session_id' => ['required', 'string'],
        ]);

        $result = (new ElavonPayment($order))->finalizeCheckoutJsPayment($validated['session_id']);

        return response()->json([
            'status' => $result['status'],
            'message' => $result['message'],
            'redirect_url' => $result['redirect_url'],
            'transaction_id' => $result['transaction_id'],
        ], $result['status'] ? 200 : 422);
    }

    public function cancel(string $uuid): RedirectResponse
    {
        $order = $this->findOrder($uuid);

        if ((int) $order->status === 0) {
            $order->update(['status' => 3]);
        }

        return redirect()->route('shop.home', $order->shop->user_name);
    }

    protected function findOrder(string $publicId): Order
    {
        if (! Schema::hasColumn('orders', 'uuid')) {
            abort(404);
        }

        return Order::query()
            ->with('shop')
            ->where('uuid', $publicId)
            ->firstOrFail();
    }

    /**
     * Normalize shop Order fields for the shared CheckoutJS Blade view.
     *
     * @return object{
     *     amount: mixed,
     *     currency: mixed,
     *     description: string,
     *     orderId: string,
     *     customer_name: string,
     *     customer_email: mixed,
     *     customer_address: mixed,
     *     customer_post_code: mixed,
     *     city: mixed,
     *     customer_phone: mixed
     * }
     */
    protected function checkoutViewOrder(Order $order): object
    {
        $order->loadMissing('metas');

        $meta = $order->metas
            ->pluck('column_value', 'column_name');

        $firstName = (string) ($meta['first_name'] ?? $order->first_name ?? '');
        $lastName = (string) ($meta['last_name'] ?? $order->last_name ?? '');

        return (object) [
            'amount' => $order->total,
            'currency' => $order->currency ?: 'NOK',
            'description' => sprintf('Order #%s', $order->id),
            'orderId' => (string) $order->id,
            'customer_name' => trim($firstName.' '.$lastName),
            'customer_email' => $meta['email'] ?? $order->email,
            'customer_address' => $meta['address'] ?? $order->address,
            'customer_post_code' => $meta['post_code'] ?? $order->post_code,
            'city' => $meta['city'] ?? $order->city,
            'customer_phone' => $meta['phone'] ?? $order->phone,
        ];
    }
}
