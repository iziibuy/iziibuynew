<?php

declare(strict_types=1);

namespace App\Services\Elavon;

class ElavonVaultOnlyPaymentSession
{
    public static function isVaultOnlyAmount(float $amountNok): bool
    {
        return round($amountNok, 2) <= 0;
    }

    /**
     * @param  array<string, mixed>  $sessionBody
     * @return array<string, mixed>
     */
    public static function augmentPaymentSessionBody(array $sessionBody, string $shopperReference): array
    {
        return array_merge($sessionBody, [
            'shopper' => $shopperReference,
            'doCreateTransaction' => false,
            'doThreeDSecure' => ($sessionBody['doThreeDSecure'] ?? 0) ? 1 : 0,
            'useStoredPaymentMethod' => true,
            'isTotalAdjustable' => false,
            'hppType' => 'fullPageRedirect',
        ]);
    }
}
