<?php

namespace App\Services\Elavon;

use Illuminate\Support\Carbon;

class ElavonOnboardingPromo
{
    public const FREE_SUBSCRIPTION_UNTIL = '2026-07-20';

    public static function isFreeSubscriptionPeriod(): bool
    {
        return now()->lt(Carbon::parse(self::FREE_SUBSCRIPTION_UNTIL)->startOfDay());
    }

    public static function signupFee(float $fee): float
    {
        return self::isFreeSubscriptionPeriod() ? 0.0 : max(0.0, $fee);
    }
}
