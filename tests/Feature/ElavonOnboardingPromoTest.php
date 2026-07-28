<?php

declare(strict_types=1);

use App\Models\PaymentMethodAccess;
use App\Models\Shop;
use App\Services\Elavon\ElavonOnboardingPromo;
use App\Services\Elavon\ElavonRecurringTransaction;
use App\Services\Elavon\ElavonVaultOnlyPaymentSession;
use Illuminate\Support\Carbon;

it('uses zero promo signup fee between 19 and 26 july 2026', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-19 12:00:00'));

    expect(ElavonOnboardingPromo::isFreeSubscriptionPeriod())->toBeTrue();

    Carbon::setTestNow(Carbon::parse('2026-07-26 23:59:59'));

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

it('charges normal subscription fees outside the promo period', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-18 23:59:59'));

    expect(ElavonOnboardingPromo::isFreeSubscriptionPeriod())->toBeFalse();

    Carbon::setTestNow(Carbon::parse('2026-07-27 00:00:00'));

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
        'recurringType' => 'first',
        'previousRecurringTransaction' => 'https://example.test/transactions/old',
    ];

    $first = ElavonRecurringTransaction::applyFirstSetup($base);

    expect($first['credentialOnFileType'])->toBe('subscription')
        ->and($first['shopperInteraction'])->toBe('ecommerce')
        ->and($first)->not->toHaveKey('recurringType')
        ->and($first)->not->toHaveKey('previousRecurringTransaction');

    $subsequent = ElavonRecurringTransaction::applySubsequentMerchantInitiated(
        $base,
        'https://api.converge.eu.elavonaws.com',
        'tx-setup-123'
    );

    expect($subsequent['credentialOnFileType'])->toBe('subscription')
        ->and($subsequent['shopperInteraction'])->toBe('merchantInitiated')
        ->and($subsequent)->not->toHaveKey('recurringType')
        ->and($subsequent)->not->toHaveKey('previousRecurringTransaction');
});
