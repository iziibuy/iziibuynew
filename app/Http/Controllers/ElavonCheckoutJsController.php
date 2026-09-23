<?php

namespace App\Http\Controllers;

use App\Models\ExternalOrder;
use App\Payment\Elavon\ApiElavonPayment;
use App\Payment\Elavon\CheckoutJsTheme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ElavonCheckoutJsController extends Controller
{
    public function show(string $uuid): View|RedirectResponse
    {
        $order = $this->findOrder($uuid);

        if (strtoupper((string) $order->status) === 'COMPLETED') {
            return redirect()->away(
                $order->success_redirect_url.'?order='.$order->orderId.'&payment_id='.$order->payment_id
            );
        }

        if (! in_array(strtoupper((string) $order->status), ['PENDING', 'FAILED'], true)) {
            abort(404);
        }

        $api = $order->paymentApi;

        if (! $api?->usesElavonCheckoutJs()) {
            abort(404);
        }

        $elavon = new ApiElavonPayment($order);
        $session = $elavon->ensureCheckoutJsSession();

        if (! ($session['status'] ?? false)) {
            abort(422, $session['message'] ?? 'Unable to prepare CheckoutJS payment session.');
        }

        $order = $order->fresh();
        $access = $order->paymentMethodAccess;
        $publicId = $this->orderPublicId($order);
        $pluginLogo = $access?->logo;
        $theme = CheckoutJsTheme::fromApi($order->paymentApi);

        return view('payments.elavon-checkoutjs', [
            'order' => $order,
            'sessionId' => $session['payment_id'] ?? $order->payment_id,
            'hostedFieldsScriptUrl' => $elavon->hostedFieldsScriptUrl(),
            'companyName' => $theme->companyName($access?->company_name ?: (string) config('app.name')),
            'companyLogo' => $theme->logoUrl(is_string($pluginLogo) && $pluginLogo !== '' ? $pluginLogo : null),
            'checkoutTheme' => $theme,
            'completeUrl' => route('elavon.checkoutjs.complete', $publicId),
            'cancelUrl' => route('elavon.checkoutjs.cancel', $publicId),
        ]);
    }

    public function complete(Request $request, string $uuid): JsonResponse
    {
        $order = $this->findOrder($uuid);

        $validated = $request->validate([
            'session_id' => ['required', 'string'],
        ]);

        $result = (new ApiElavonPayment($order))->finalizeCheckoutJsPayment($validated['session_id']);

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

        if (strtoupper((string) $order->status) === 'PENDING') {
            $order->update(['status' => 'CANCELED']);
        }

        $query = http_build_query(array_filter([
            'order' => $order->orderId,
            'payment_id' => $order->payment_id,
            'status' => 'canceled',
        ]));

        return redirect()->away($order->failed_redirect_url.($query ? '?'.$query : ''));
    }

    protected function findOrder(string $publicId): ExternalOrder
    {
        $query = ExternalOrder::query()->with(['paymentMethodAccess', 'paymentApi']);

        if (Schema::hasColumn('external_orders', 'uuid')) {
            $order = (clone $query)->where('uuid', $publicId)->first();
            if ($order) {
                return $order;
            }
        }

        if (Schema::hasColumn('external_orders', 'ulid')) {
            $order = (clone $query)->where('ulid', $publicId)->first();
            if ($order) {
                return $order;
            }
        }

        abort(404);
    }

    protected function orderPublicId(ExternalOrder $order): string
    {
        return (string) ($order->uuid ?? $order->ulid ?? $order->id);
    }
}
