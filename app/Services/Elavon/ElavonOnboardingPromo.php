<?php

namespace App\Services\Elavon;

use Illuminate\Support\Carbon;

class ElavonOnboardingPromo
{
    public const FREE_SUBSCRIPTION_FROM = '2026-07-19';

    public const FREE_SUBSCRIPTION_UNTIL = '2026-07-26';

    public const PROMO_SIGNUP_FEE = 0.0;

    /** Converge orders require amount > 0; used only to fix the HPP total (no charge on return). */
    public const HPP_PLACEHOLDER_ORDER_AMOUNT = 1.0;

    public static function isFreeSubscriptionPeriod(): bool
    {
        $now = now();

        return $now->gte(Carbon::parse(self::FREE_SUBSCRIPTION_FROM)->startOfDay())
            && $now->lte(Carbon::parse(self::FREE_SUBSCRIPTION_UNTIL)->endOfDay());
    }

    public static function signupFee(float $fee): float
    {
        return self::isFreeSubscriptionPeriod() ? self::PROMO_SIGNUP_FEE : max(0.0, $fee);
    }

    public static function usesVaultOnlySignup(float $amountNok): bool
    {
        return self::isFreeSubscriptionPeriod() && round($amountNok, 2) <= 0;
    }

    public static function shouldShowHppPlaceholderNotice(float $signupAmountNok): bool
    {
        return self::usesVaultOnlySignup($signupAmountNok);
    }

    public static function hppOrderAmount(float $signupAmountNok): float
    {
        if (round($signupAmountNok, 2) <= 0) {
            return self::HPP_PLACEHOLDER_ORDER_AMOUNT;
        }

        return max(round($signupAmountNok, 2), self::HPP_PLACEHOLDER_ORDER_AMOUNT);
    }
}
