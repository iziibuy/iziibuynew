<?php

declare(strict_types=1);

namespace App\Payment\Elavon;

use App\Models\PaymentMethodAccess;
use App\Models\Shop;

class CheckoutJsWallets
{
    /**
     * Wallet buttons enabled for a shop's CheckoutJS payment page.
     *
     * @return array{googlePay: bool, applePay: bool, applePayDomain: string}
     */
    public static function forShop(?Shop $shop): array
    {
        return self::build((bool) $shop?->wallet_google_pay, (bool) $shop?->wallet_apple_pay);
    }

    /**
     * Wallet buttons enabled for a plugin's CheckoutJS payment page.
     *
     * @return array{googlePay: bool, applePay: bool, applePayDomain: string}
     */
    public static function forPaymentMethodAccess(?PaymentMethodAccess $access): array
    {
        return self::build((bool) $access?->wallet_google_pay, (bool) $access?->wallet_apple_pay);
    }

    /**
     * @return array{googlePay: bool, applePay: bool, applePayDomain: string}
     */
    protected static function build(bool $googlePay, bool $applePay): array
    {
        return [
            'googlePay' => $googlePay,
            'applePay' => $applePay,
            'applePayDomain' => (string) (config('services.enterprise_elavon.checkoutjs_apple_pay_domain') ?: request()->getHost()),
        ];
    }
}
