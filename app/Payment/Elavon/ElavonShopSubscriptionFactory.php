<?php

declare(strict_types=1);

namespace App\Payment\Elavon;

use App\Models\Shop;

class ElavonShopSubscriptionFactory
{
    public function make(Shop $shop): ElavonShopSubscription
    {
        return new ElavonShopSubscription($shop);
    }
}
