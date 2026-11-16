<?php

declare(strict_types=1);

use App\Elavon\Converge2\Response\StoredCardResponse;
use App\Models\Shop;
use App\Models\User;
use App\Payment\Elavon\ElavonShopSubscription;
use App\Payment\Elavon\ElavonShopSubscriptionFactory;
use App\Services\GatewayCredentialBackup;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

function shopTrialUser(): User
{
    $user = User::factory()->create(['role_id' => User::ROLES['Vendor']]);
    $user->assignRole('vendor');

    return $user;
}

function shopTrialShop(User $user, array $attributes = []): Shop
{
    $shop = Shop::query()->create(array_merge([
        'user_id' => $user->id,
        'user_name' => 'trial-shop-'.uniqid(),
        'subscriptionMethod' => 'quickpay',
        'status' => 0,
        'establishment' => 0,
        'monthly_cost' => 299,
        'establishment_cost' => 1995,
        'per_user_fee' => 99,
        'is_demo' => true,
    ], $attributes));

    $shop->createMetas([
        'company_registration' => '123456789',
        'city' => 'Oslo',
        'street' => 'Testveien 1',
        'post_code' => '0150',
        'contact_email' => $user->email,
        'contact_phone' => '12345678',
    ]);

    $user->update(['shop_id' => $shop->id]);

    return $shop;
}

it('shows the trial button for an eligible shop and hides it while a trial is running', function (): void {
    $user = shopTrialUser();
    $shop = shopTrialShop($user);

    $this->actingAs($user)
        ->get(route('shop.subscription.payment'))
        ->assertOk()
        ->assertSee(route('shop.start-trial'), false);

    $shop->update(['trial_used' => true, 'trial_ends_at' => now()->addDays(3)]);

    $this->actingAs($user->fresh())
        ->get(route('shop.subscription.payment'))
        ->assertOk()
        ->assertDontSee(route('shop.start-trial'), false);
});

it('starts the shop trial and applies configured demo credentials', function (): void {
    config()->set('services.enterprise_elavon.trial_credentials', [
        'merchant_alias' => 'alias-123',
        'public_key' => 'pk_test_123',
        'secret_key' => 'sk_test_123',
    ]);
    $user = shopTrialUser();
    $shop = shopTrialShop($user);

    $this->actingAs($user)
        ->post(route('shop.start-trial'))
        ->assertRedirect(route('shop.complete.signup'));

    $shop = $shop->fresh();

    expect($shop->subscriptionMethod)->toBe(Shop::SUBSCRIPTION_METHOD_ELAVON)
        ->and($shop->trial_used)->toBeTrue()
        ->and($shop->trial_ends_at->isSameDay(now()->addDays(7)))->toBeTrue()
        ->and($shop->onTrial())->toBeTrue()
        ->and($shop->elavon_merchant_alias)->toBe('alias-123')
        ->and($shop->elavon_public_key)->toBe('pk_test_123')
        ->and($shop->elavon_secret_key)->toBe('sk_test_123')
        ->and((int) $shop->getAttributes()['status'])->toBe(0);
});

it('backs up original elavon and surfboard credentials as json before applying trial keys', function (): void {
    config()->set('services.enterprise_elavon.trial_credentials', [
        'merchant_alias' => 'trial-alias',
        'public_key' => 'pk_trial',
        'secret_key' => 'sk_trial',
    ]);
    $user = shopTrialUser();
    $shop = shopTrialShop($user);
    $shop->createMetas([
        'elavon_merchant_alias' => 'live-alias',
        'elavon_public_key' => 'pk_live',
        'elavon_secret_key' => 'sk_live',
        'surfboard_terminalId' => 'term-cit',
        'surfboard_merchantId' => 'merch-1',
        'surfboard_storeId' => 'store-1',
    ]);

    $this->actingAs($user)->post(route('shop.start-trial'))->assertRedirect(route('shop.complete.signup'));

    $shop = $shop->fresh();
    $backup = app(GatewayCredentialBackup::class)->getBackup($shop);

    expect($shop->elavon_merchant_alias)->toBe('trial-alias')
        ->and($shop->elavon_public_key)->toBe('pk_trial')
        ->and($shop->elavon_secret_key)->toBe('sk_trial')
        ->and($shop->surfboard_terminalId)->toBe('term-cit')
        ->and($backup)->toMatchArray([
            'elavon' => [
                'elavon_merchant_alias' => 'live-alias',
                'elavon_public_key' => 'pk_live',
                'elavon_secret_key' => 'sk_live',
            ],
            'surfboard' => [
                'surfboard_terminalId' => 'term-cit',
                'surfboard_merchantId' => 'merch-1',
                'surfboard_storeId' => 'store-1',
            ],
        ])
        ->and($backup['captured_at'] ?? null)->not->toBeEmpty();
});

it('does not overwrite an existing original gateway credentials backup on a later trial', function (): void {
    config()->set('services.enterprise_elavon.trial_credentials', [
        'merchant_alias' => 'trial-alias',
        'public_key' => 'pk_trial',
        'secret_key' => 'sk_trial',
    ]);
    $user = shopTrialUser();
    $shop = shopTrialShop($user);
    $shop->createMetas([
        'elavon_merchant_alias' => 'live-alias',
        'elavon_public_key' => 'pk_live',
        'elavon_secret_key' => 'sk_live',
    ]);

    $this->actingAs($user)->post(route('shop.start-trial'))->assertRedirect(route('shop.complete.signup'));

    $this->travel(8)->days();

    $this->actingAs($user->fresh())
        ->post(route('shop.start-trial'))
        ->assertRedirect(route('shop.complete.signup'));

    $backup = app(GatewayCredentialBackup::class)->getBackup($shop->fresh());

    expect($backup['elavon'] ?? [])->toMatchArray([
        'elavon_merchant_alias' => 'live-alias',
        'elavon_public_key' => 'pk_live',
        'elavon_secret_key' => 'sk_live',
    ]);
});

it('grants dashboard access with a days-left banner during the trial', function (): void {
    $user = shopTrialUser();
    $shop = shopTrialShop($user);

    $this->actingAs($user)->post(route('shop.start-trial'));

    $this->actingAs($user->fresh())
        ->get(route('shop.dashboard'))
        ->assertOk()
        ->assertSee(__('words.trial_days_left', ['days' => 7]), false)
        ->assertSee(route('shop.subscription.payment'), false);
});

it('redirects to the payment page once the trial has ended and allows a new trial', function (): void {
    $user = shopTrialUser();
    $shop = shopTrialShop($user);

    $this->actingAs($user)->post(route('shop.start-trial'));

    $this->actingAs($user->fresh())
        ->post(route('shop.start-trial'))
        ->assertRedirect(route('shop.subscription.payment'))
        ->assertSessionHasErrors();

    $this->travel(8)->days();

    $this->actingAs($user->fresh())
        ->get(route('shop.dashboard'))
        ->assertRedirect(route('shop.subscription.payment'));

    $this->actingAs($user->fresh())
        ->get(route('shop.subscription.payment'))
        ->assertOk()
        ->assertSee(route('shop.start-trial'), false);

    $this->actingAs($user->fresh())
        ->post(route('shop.start-trial'))
        ->assertRedirect(route('shop.complete.signup'));

    expect($shop->fresh()->onTrial())->toBeTrue()
        ->and($shop->fresh()->trialDaysLeft())->toBe(7);
});

it('settles the trial on a paid signup', function (): void {
    Mail::fake();
    $user = shopTrialUser();
    $shop = shopTrialShop($user, [
        'monthly_cost' => 0,
        'establishment_cost' => 0,
        'per_user_fee' => 0,
    ]);

    $this->actingAs($user)->post(route('shop.start-trial'));

    $elavon = Mockery::mock(ElavonShopSubscription::class);
    $elavon->shouldReceive('finalizeHostedSubscriptionFromSession')
        ->once()
        ->andReturnUsing(function () use ($shop): array {
            $shop->update([
                'subscription_id' => 'card-paid',
                'shopperId' => 'shopper-1',
                'elavon_initial_transaction_id' => 'tx-paid',
                'payment_order_id' => 'tx-paid',
                'subscriptionMethod' => Shop::SUBSCRIPTION_METHOD_ELAVON,
            ]);

            return ['status' => true, 'data' => ['cardId' => 'card-paid', 'transactionId' => 'tx-paid']];
        });
    $storedCard = Mockery::mock(StoredCardResponse::class);
    $storedCard->shouldReceive('isSuccess')->andReturn(true);
    $elavon->shouldReceive('getStoredCardResource')->andReturn($storedCard);

    $factory = Mockery::mock(ElavonShopSubscriptionFactory::class);
    $factory->shouldReceive('make')->andReturn($elavon);
    app()->instance(ElavonShopSubscriptionFactory::class, $factory);

    $this->actingAs($user->fresh())
        ->get(route('shop.subscription.elavon.return', ['sessionId' => 'sess-paid']))
        ->assertRedirect(route('shop.complete.signup'));

    $shop->refresh();

    expect((int) $shop->getAttributes()['status'])->toBe(1)
        ->and($shop->trial_charged_at)->not->toBeNull()
        ->and($shop->onTrial())->toBeFalse();
});

it('expires trials without any payment calls', function (): void {
    $shop = shopTrialShop(shopTrialUser(), ['trial_used' => true, 'trial_ends_at' => now()->subHour()]);

    $this->artisan('shop:expire-trials')
        ->expectsOutputToContain('Expired trials awaiting subscription: 1')
        ->assertSuccessful();

    expect((int) $shop->fresh()->getAttributes()['status'])->toBe(0);
});
