<?php

declare(strict_types=1);

use App\Models\CheckoutPaymentOption;
use App\Models\Shop;
use App\Models\User;
use App\Services\Checkout\CheckoutPaymentOptionCatalog;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

it('has a checkout payment options table', function (): void {
    expect(Schema::hasTable('checkout_payment_options'))->toBeTrue();
});

it('uses config catalog by default and lets db rows override by key', function (): void {
    $catalog = app(CheckoutPaymentOptionCatalog::class);

    expect($catalog->active())->toHaveKey('visa')
        ->and($catalog->active()['vipps']['acquirers'])->toContain('surfboard');

    CheckoutPaymentOption::query()->create([
        'key' => 'visa',
        'label' => 'Visa DB',
        'icon' => 'visa',
        'acquirers' => ['elavon'],
        'sort' => 1,
        'is_active' => true,
    ]);

    $merged = app(CheckoutPaymentOptionCatalog::class)->all();

    expect($merged['visa']['label'])->toBe('Visa DB')
        ->and($merged['visa']['acquirers'])->toBe(['elavon']);
});

it('returns flat enabled checkout options for a shop', function (): void {
    $user = User::factory()->create(['role_id' => User::ROLES['Vendor']]);

    $shop = Shop::query()->create([
        'user_id' => $user->id,
        'user_name' => 'checkout-options-'.uniqid(),
        'subscription_id' => 'sub-'.uniqid(),
        'subscriptionMethod' => 'elavon',
        'paymentMethod' => 'elavon,surfboard',
        'status' => 1,
    ]);

    $shop->createMetas([
        'checkout_payment_options' => [
            'visa' => ['enabled' => true, 'acquirer' => 'elavon'],
            'vipps' => ['enabled' => true, 'acquirer' => 'surfboard'],
            'amex' => ['enabled' => false, 'acquirer' => 'elavon'],
        ],
    ]);

    $shop = $shop->fresh();

    $options = $shop->checkout_payment_methods();

    expect($options)->toHaveKeys(['visa', 'vipps'])
        ->and($options)->not->toHaveKey('amex')
        ->and($options['visa']['acquirer'])->toBe('elavon')
        ->and($options['vipps']['acquirer'])->toBe('surfboard')
        ->and($shop->acquirerForCheckoutOption('visa'))->toBe('elavon')
        ->and($shop->selectedCheckoutAcquirers())->toEqualCanonicalizing(['elavon', 'surfboard']);
});

it('shows checkout options even when platform subscription is not elavon', function (): void {
    $user = User::factory()->create(['role_id' => User::ROLES['Vendor']]);

    $shop = Shop::query()->create([
        'user_id' => $user->id,
        'user_name' => 'checkout-quickpay-sub-'.uniqid(),
        'subscription_id' => 'sub-'.uniqid(),
        'subscriptionMethod' => 'quickpay',
        'paymentMethod' => 'elavon,surfboard',
        'status' => 1,
    ]);

    expect($shop->requiresElavonResubscription())->toBeTrue()
        ->and($shop->checkout_payment_methods())->toHaveKey('visa')
        ->and($shop->checkout_payment_methods())->toHaveKey('vipps');
});

it('falls back to gateways when saved checkout options are all disabled', function (): void {
    $user = User::factory()->create(['role_id' => User::ROLES['Vendor']]);

    $shop = Shop::query()->create([
        'user_id' => $user->id,
        'user_name' => 'checkout-empty-meta-'.uniqid(),
        'subscription_id' => 'sub-'.uniqid(),
        'subscriptionMethod' => 'elavon',
        'paymentMethod' => 'elavon',
        'status' => 1,
    ]);

    $shop->createMetas([
        'checkout_payment_options' => [
            'visa' => ['enabled' => false, 'acquirer' => 'elavon'],
        ],
    ]);

    expect($shop->fresh()->checkout_payment_methods())->toHaveKey('visa');
});

it('saves checkout payment options from admin shop update', function (): void {
    $admin = User::factory()->create([
        'role_id' => User::ROLES['Admin'],
        'password' => bcrypt('password'),
    ]);
    $admin->assignRole('admin');

    $owner = User::factory()->create(['role_id' => User::ROLES['Vendor']]);
    $shop = Shop::query()->create([
        'user_id' => $owner->id,
        'user_name' => 'checkout-admin-'.uniqid(),
        'subscription_id' => 'sub-'.uniqid(),
        'subscriptionMethod' => 'elavon',
        'paymentMethod' => 'elavon',
        'status' => 1,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.profile.update', $shop), [
            'user_name' => $shop->user_name,
            'default_currency' => 'NOK',
            'currencies' => ['NOK'],
            'checkout_payment_options' => [
                'visa' => ['enabled' => '1', 'acquirer' => 'surfboard'],
                'vipps' => ['enabled' => '1', 'acquirer' => 'surfboard'],
            ],
            'meta' => [
                'site_mode' => 'live',
            ],
        ])
        ->assertRedirect();

    $shop->refresh();

    expect($shop->paymentMethod)->toContain('surfboard')
        ->and($shop->acquirerForCheckoutOption('visa'))->toBe('surfboard')
        ->and($shop->acquirerForCheckoutOption('vipps'))->toBe('surfboard');
});
