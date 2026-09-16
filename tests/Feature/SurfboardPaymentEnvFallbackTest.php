<?php

declare(strict_types=1);

use App\Payment\Surfboard\SurfboardPayment;

/**
 * Regression test for a production crash: SurfboardPayment's properties are
 * typed `string` (non-nullable), so assigning env()'s null return straight
 * into them threw a TypeError before any fallback could run. See
 * app/Payment/Surfboard/SurfboardPayment.php.
 */
it('does not crash when the Surfboard env vars are missing', function (): void {
    $vars = [
        'SURFBOARD_API_URL',
        'SURFBOARD_API_KEY',
        'SURFBOARD_API_SECRET',
        'SURFBOARD_PARTNER_ID',
        'SURFBOARD_MERCHANT_ID',
        'SURFBOARD_STORE_ID',
    ];

    $original = [];
    foreach ($vars as $var) {
        $original[$var] = $_ENV[$var] ?? null;
        putenv($var);
        unset($_ENV[$var], $_SERVER[$var]);
    }

    try {
        $payment = new SurfboardPayment;

        expect($payment->apiUrl)->toBe('https://lithium.surfgw.com/api')
            ->and($payment->apiKey)->toBe('')
            ->and($payment->apiSecret)->toBe('')
            ->and($payment->partnerId)->toBe('')
            ->and($payment->merchantId)->toBe('')
            ->and($payment->storeId)->toBe('');
    } finally {
        foreach ($original as $var => $value) {
            if ($value === null) {
                putenv($var);
                unset($_ENV[$var], $_SERVER[$var]);
            } else {
                putenv("{$var}={$value}");
                $_ENV[$var] = $value;
                $_SERVER[$var] = $value;
            }
        }
    }
});

it('lets explicit merchantId and storeId arguments override the env fallback', function (): void {
    $payment = new SurfboardPayment('merchant-123', 'store-456');

    expect($payment->merchantId)->toBe('merchant-123')
        ->and($payment->storeId)->toBe('store-456');
});
