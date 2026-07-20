<?php

declare(strict_types=1);

use App\Models\Shop;

it('uses the shop tax rate for registration when set', function (): void {
    config(['settings.payment.registration_tax' => 0]);

    $shop = new Shop(['tax' => 25]);

    expect($shop->registrationTax())->toBe(25.0);
});

it('falls back to the global registration tax when shop tax is null', function (): void {
    config(['settings.payment.registration_tax' => 25]);

    $shop = new Shop(['tax' => null]);

    expect($shop->registrationTax())->toBe(25.0);
});

it('allows zero percent shop tax without falling back to global', function (): void {
    config(['settings.payment.registration_tax' => 25]);

    $shop = new Shop(['tax' => 0]);

    expect($shop->registrationTax())->toBe(0.0);
});

it('includes per shop tax in elavon recurring amount calculations', function (): void {
    config(['settings.payment.registration_tax' => 0]);

    $shop = new Shop([
        'tax' => 25,
        'establishment' => 0,
        'establishment_cost' => 500,
        'monthly_cost' => 200,
        'per_user_fee' => 50,
    ]);
    $shop->setRelation('users', collect([]));

    expect($shop->registrationTax())->toBe(25.0)
        ->and($shop->elavonRecurringSubscriptionAmount())->toBe(250.0);
});
