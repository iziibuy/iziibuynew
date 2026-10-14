<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Elavon\Converge2\Converge2;
use App\Models\Charge;
use App\Models\Enterprise;
use App\Models\PaymentMethodAccess;
use App\Models\SubscriptionCharge;
use App\Payment\Elavon\ElavonEnterpriseHostedSubscription;
use App\Payment\Elavon\ElavonExternalHostedSubscription;
use App\Payment\Elavon\ElavonShopHostedSubscription;
use Illuminate\Database\Eloquent\Model;

/**
 * Knows which Elavon transaction and merchant credentials belong to a platform charge.
 */
class ChargeElavonGateway
{
    public static function looksLikeTransactionId(?string $transactionId): bool
    {
        return filled($transactionId) && preg_match('/^[a-z0-9]{20,40}$/', (string) $transactionId) === 1;
    }

    public function transactionId(Model $charge): ?string
    {
        $transactionId = match (true) {
            $charge instanceof Charge => $charge->shop?->subscriptionMethod === 'elavon' ? (string) $charge->order_id : null,
            $charge instanceof SubscriptionCharge => (string) $charge->elavon_transaction_id,
            default => null,
        };

        return self::looksLikeTransactionId($transactionId) ? $transactionId : null;
    }

    public function client(Model $charge): ?Converge2
    {
        if ($charge instanceof Charge) {
            return $charge->shop !== null ? (new ElavonShopHostedSubscription($charge->shop))->convergeClient() : null;
        }

        if ($charge instanceof SubscriptionCharge) {
            $subscribable = $charge->subscription?->subscribable;

            return match (true) {
                $subscribable instanceof PaymentMethodAccess => (new ElavonExternalHostedSubscription($subscribable))->convergeClient(),
                $subscribable instanceof Enterprise => (new ElavonEnterpriseHostedSubscription($subscribable))->convergeClient(),
                default => null,
            };
        }

        return null;
    }

    public function storedCardId(Model $charge): ?string
    {
        $storedCardId = match (true) {
            $charge instanceof Charge => filled($charge->shop?->subscription_id)
                ? (string) $charge->shop->subscription_id
                : null,
            $charge instanceof SubscriptionCharge => filled($charge->subscription?->key)
                ? (string) $charge->subscription->key
                : null,
            default => null,
        };

        return filled($storedCardId) ? $storedCardId : null;
    }
}
