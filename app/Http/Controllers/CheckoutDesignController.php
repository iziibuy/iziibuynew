<?php

namespace App\Http\Controllers;

use App\Models\PaymentApi;
use App\Models\Shop;
use App\Payment\Elavon\CheckoutJsTheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutDesignController extends Controller
{
    public function editShop(): View
    {
        $shop = $this->currentShop();

        return view('dashboard.checkout-design.editor', [
            'layout' => 'dashboard.shop',
            'checkoutTheme' => $shop->checkoutJsTheme(),
            'appearance' => $shop->checkoutjs_appearance ?? [],
            'companyNamePlaceholder' => $shop->company_name,
            'logoUrl' => $shop->checkoutJsTheme()->logoUrl(),
            'usesCheckoutJs' => $shop->usesElavonCheckoutJs(),
            'previewUrl' => route('shop.checkoutDesign.preview'),
            'saveUrl' => route('shop.checkoutDesign.update'),
            'backUrl' => route('shop.store.profile'),
        ]);
    }

    public function previewShop(): View
    {
        $shop = $this->currentShop();

        return $this->preview(
            $shop->checkoutJsTheme(),
            $shop->company_name ?: (string) config('app.name'),
            null,
        );
    }

    public function updateShop(Request $request): RedirectResponse
    {
        $shop = $this->currentShop();

        $request->validate(CheckoutJsTheme::validationRules());

        $shop->update([
            'checkoutjs_appearance' => $shop->checkoutJsTheme()->persist(
                $request->only(CheckoutJsTheme::inputKeys()),
                $request->file('checkout_logo'),
                $request->boolean('checkout_remove_logo'),
            ),
        ]);

        return redirect()->route('shop.checkoutDesign.edit')->with('success', __('Checkout page saved'));
    }

    public function editButton(PaymentApi $paymentApi): View
    {
        $this->authorizeButton($paymentApi);

        $theme = $paymentApi->checkoutJsTheme();
        $access = $paymentApi->paymentMethodAccess;

        return view('dashboard.checkout-design.editor', [
            'layout' => 'dashboard.external',
            'checkoutTheme' => $theme,
            'appearance' => $paymentApi->checkoutjs_appearance ?? [],
            'companyNamePlaceholder' => $access?->company_name,
            'logoUrl' => $theme->logoUrl($this->pluginLogo($paymentApi)),
            'usesCheckoutJs' => $paymentApi->usesElavonCheckoutJs(),
            'previewUrl' => route('external.checkoutDesign.preview', $paymentApi),
            'saveUrl' => route('external.checkoutDesign.update', $paymentApi),
            'backUrl' => route('external.buttonPayment.edit', $paymentApi),
        ]);
    }

    public function previewButton(PaymentApi $paymentApi): View
    {
        $this->authorizeButton($paymentApi);

        return $this->preview(
            $paymentApi->checkoutJsTheme(),
            $paymentApi->paymentMethodAccess?->company_name ?: (string) config('app.name'),
            $this->pluginLogo($paymentApi),
        );
    }

    public function updateButton(Request $request, PaymentApi $paymentApi): RedirectResponse
    {
        $this->authorizeButton($paymentApi);

        $request->validate(CheckoutJsTheme::validationRules());

        $paymentApi->update([
            'checkoutjs_appearance' => $paymentApi->checkoutJsTheme()->persist(
                $request->only(CheckoutJsTheme::inputKeys()),
                $request->file('checkout_logo'),
                $request->boolean('checkout_remove_logo'),
            ),
        ]);

        return redirect()
            ->route('external.checkoutDesign.edit', $paymentApi)
            ->with('success', __('Checkout page saved'));
    }

    protected function preview(CheckoutJsTheme $theme, string $companyNameFallback, ?string $pluginLogo): View
    {
        return view('payments.elavon-checkoutjs', [
            'previewMode' => true,
            'order' => (object) [
                'amount' => 1249.00,
                'currency' => 'NOK',
                'description' => __('Sample order'),
                'orderId' => 'PREVIEW-1001',
                'customer_name' => 'Jane Doe',
                'customer_email' => 'jane@example.com',
                'customer_address' => 'Main Street 1',
                'customer_post_code' => '0150',
                'city' => 'Oslo',
                'customer_phone' => '12345678',
            ],
            'sessionId' => '',
            'hostedFieldsScriptUrl' => '',
            'companyName' => $theme->companyName($companyNameFallback),
            'companyNameFallback' => $companyNameFallback,
            'companyLogo' => $theme->logoUrl($pluginLogo),
            'checkoutTheme' => $theme,
            'completeUrl' => '#',
            'cancelUrl' => '#',
        ]);
    }

    protected function currentShop(): Shop
    {
        $shop = auth()->user()->shop;

        abort_unless($shop instanceof Shop, 404);

        return $shop;
    }

    protected function authorizeButton(PaymentApi $paymentApi): void
    {
        abort_unless(
            (int) $paymentApi->payment_method_access_id === (int) auth()->user()->paymentMethodAccess?->id,
            403,
        );

        $paymentApi->loadMissing('paymentMethodAccess');
    }

    protected function pluginLogo(PaymentApi $paymentApi): ?string
    {
        $logo = $paymentApi->paymentMethodAccess?->logo;

        return is_string($logo) && $logo !== '' ? $logo : null;
    }
}
