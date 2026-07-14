<?php

declare(strict_types=1);

use App\Models\PaymentMethodAccess;
use App\Models\Shop;
use App\Services\Elavon\ElavonOnboardingPromo;
use App\Services\Elavon\ElavonRecurringTransaction;
use App\Services\Elavon\ElavonVaultOnlyPaymentSession;
use Illuminate\Support\Carbon;

it('uses zero promo signup fee before 20 july 2026', function (): void {
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

it('uses vault-only signup when promo fee is zero', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-19 12:00:00'));

    expect(ElavonOnboardingPromo::usesVaultOnlySignup(0))->toBeTrue()
        ->and(ElavonOnboardingPromo::shouldShowHppPlaceholderNotice(0))->toBeTrue()
        ->and(ElavonOnboardingPromo::shouldShowHppPlaceholderNotice(100))->toBeFalse()
        ->and(ElavonVaultOnlyPaymentSession::isVaultOnlyAmount(0))->toBeTrue()
        ->and(ElavonVaultOnlyPaymentSession::isVaultOnlyAmount(100))->toBeFalse()
        ->and(ElavonOnboardingPromo::hppOrderAmount(0))->toBe(1.0)
        ->and(ElavonOnboardingPromo::hppOrderAmount(100))->toBe(100.0);
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

it('detects elavon app managed subscription shops', function (): void {
    $shop = new Shop([
        'subscriptionMethod' => 'elavon',
        'subscription_id' => 'card-123',
        'elavon_subscription_id' => null,
    ]);

    expect($shop->usesElavonAppManagedSubscription())->toBeTrue();

    $shop->elavon_subscription_id = 'sub-native';

    expect($shop->usesElavonAppManagedSubscription())->toBeFalse();
});

it('builds first and subsequent recurring transaction payloads', function (): void {
    $base = [
        'type' => 'sale',
        'total' => ['amount' => 100, 'currencyCode' => 'NOK'],
    ];

    $first = ElavonRecurringTransaction::applyFirstSetup($base);

    expect($first['recurringType'])->toBe('first')
        ->and($first['shopperInteraction'])->toBe('ecommerce');

    $subsequent = ElavonRecurringTransaction::applySubsequentMerchantInitiated(
        $base,
        'https://api.converge.eu.elavonaws.com',
        'tx-setup-123'
    );

    expect($subsequent['recurringType'])->toBe('subsequent')
        ->and($subsequent['shopperInteraction'])->toBe('mailOrder')
        ->and($subsequent['previousRecurringTransaction'])->toBe('https://api.converge.eu.elavonaws.com/transactions/tx-setup-123');
});
