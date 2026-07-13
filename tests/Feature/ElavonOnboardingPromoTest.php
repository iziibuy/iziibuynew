<?php

declare(strict_types=1);

use App\Models\PaymentMethodAccess;
use App\Models\Shop;
use App\Services\Elavon\ElavonOnboardingPromo;
use Illuminate\Support\Carbon;

it('treats subscriptions as free before 20 july 2026', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-19 12:00:00'));

    expect(ElavonOnboardingPromo::isFreeSubscriptionPeriod())->toBeTrue();

    $shop = new Shop([
        'establishment' => 0,
        'establishment_cost' => 500,
        'monthly_cost' => 200,
    ]);
    $shop->setRelation('users', collect([]));

    expect($shop->subscriptionFee())->toBe(0);

    $paymentMethodAccess = new PaymentMethodAccess([
        'fee' => 1000,
    ]);

    expect($paymentMethodAccess->fee())->toBe(0.0);
});

it('charges normal subscription fees from 20 july 2026', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-20 00:00:00'));

    expect(ElavonOnboardingPromo::isFreeSubscriptionPeriod())->toBeFalse();

    config(['settings.payment.registration_tax' => 25]);

    $shop = new Shop([
        'establishment' => 0,
        'establishment_cost' => 500,
        'monthly_cost' => 200,
        'paid_at' => null,
    ]);
    $shop->setRelation('users', collect([]));

    expect($shop->subscriptionFee())->toBeGreaterThan(0);
});
