<?php

namespace App\Services\Elavon;

use App\Models\Shop;
use App\Payment\Elavon\ElavonShopSubscription;
use Illuminate\Support\Facades\Log;

class ElavonShopSubscriptionBilling
{
    public static function shouldSyncRecurringPlan(Shop $shop): bool
    {
        return $shop->subscriptionMethod === 'elavon' && filled($shop->elavon_plan_id);
    }

    public static function shouldCancelAtElavon(Shop $shop): bool
    {
        return $shop->subscriptionMethod === 'elavon' && filled($shop->elavon_subscription_id);
    }

    public static function syncRecurringPlan(Shop $shop): bool
    {
        if (! self::shouldSyncRecurringPlan($shop)) {
            return true;
        }

        $result = (new ElavonShopSubscription($shop))->syncRecurringPlan();
        if (! $result['status']) {
            Log::warning('Elavon shop subscription: recurring plan sync failed', [
                'shop_id' => $shop->id,
                'message' => $result['data']['message'] ?? null,
            ]);
        }

        return $result['status'];
    }

    public static function cancel(Shop $shop): bool
    {
        if (! self::shouldCancelAtElavon($shop)) {
            return true;
        }

        $result = (new ElavonShopSubscription($shop))->cancelElavonBilling();
        if (! $result['status']) {
            Log::warning('Elavon shop subscription: cancel failed', [
                'shop_id' => $shop->id,
                'message' => $result['data']['message'] ?? null,
            ]);
        }

        return $result['status'];
    }
}
