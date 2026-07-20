<?php

declare(strict_types=1);

use App\Models\Shop;
use App\Services\Elavon\PlatformElavonCredentials;

it('uses production platform elavon credentials for live shop subscriptions', function (): void {
    config([
        'services.enterprise_elavon.credentials.production' => [
            'merchant_alias' => 'prod-alias',
            'public_key' => 'prod-public',
            'secret_key' => 'prod-secret',
        ],
        'services.enterprise_elavon.credentials.sandbox' => [
            'merchant_alias' => 'sandbox-alias',
            'public_key' => 'sandbox-public',
            'secret_key' => 'sandbox-secret',
        ],
    ]);

    $shop = new Shop(['is_demo' => false]);

    expect(PlatformElavonCredentials::forShop($shop))->toMatchArray([
        'mercahantAlias' => 'prod-alias',
        'publicKey' => 'prod-public',
        'secretKey' => 'prod-secret',
        'sandbox' => false,
    ]);
});

it('uses sandbox platform elavon credentials for demo shop subscriptions', function (): void {
    config([
        'services.enterprise_elavon.credentials.production' => [
            'merchant_alias' => 'prod-alias',
            'public_key' => 'prod-public',
            'secret_key' => 'prod-secret',
        ],
        'services.enterprise_elavon.credentials.sandbox' => [
            'merchant_alias' => 'sandbox-alias',
            'public_key' => 'sandbox-public',
            'secret_key' => 'sandbox-secret',
        ],
    ]);

    $shop = new Shop(['is_demo' => true]);

    expect(PlatformElavonCredentials::forShop($shop))->toMatchArray([
        'mercahantAlias' => 'sandbox-alias',
        'publicKey' => 'sandbox-public',
        'secretKey' => 'sandbox-secret',
        'sandbox' => true,
    ]);
});
