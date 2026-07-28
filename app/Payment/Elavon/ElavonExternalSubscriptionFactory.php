<?php

declare(strict_types=1);

namespace App\Payment\Elavon;

use App\Models\PaymentMethodAccess;

class ElavonExternalSubscriptionFactory
{
    public function make(PaymentMethodAccess $access): ElavonExternalSubscription
    {
        return new ElavonExternalSubscription($access);
    }
}
