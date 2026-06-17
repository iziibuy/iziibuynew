<?php

declare(strict_types=1);

use App\Models\Shop;
use App\Models\User;
use App\Services\Elavon\ElavonShopSubscriptionBilling;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

it('stores elavon plan and subscription id columns on shops', function (): void {
    expect(Schema::hasColumns('shops', ['elavon_plan_id', 'elavon_subscription_id']))->toBeTrue();
});

it('calculates elavon recurring amount without establishment fee', function (): void {
    config(['settings.payment.registration_tax' => 25]);

    $shop = new Shop([
        'establishment' => 0,
        'establishment_cost' => 500,
        'monthly_cost' => 200,
        'per_user_fee' => 50,
    ]);
    $shop->setRelation('users', collect([]));

    expect($shop->elavonRecurringSubscriptionAmount())->toBe(250.0);
});

it('detects elavon native subscription shops', function (): void {
    $shop = new Shop([
        'subscriptionMethod' => 'elavon',
        'elavon_subscription_id' => 'sub-123',
    ]);

    expect($shop->usesElavonNativeSubscription())->toBeTrue();

    $shop->elavon_subscription_id = null;

    expect($shop->usesElavonNativeSubscription())->toBeFalse();
});

it('knows when to sync elavon recurring plan amounts', function (): void {
    $shop = new Shop([
        'subscriptionMethod' => 'elavon',
        'elavon_plan_id' => 'plan-123',
    ]);

    expect(ElavonShopSubscriptionBilling::shouldSyncRecurringPlan($shop))->toBeTrue();
    expect(ElavonShopSubscriptionBilling::shouldCancelAtElavon($shop))->toBeFalse();
});

it('rejects elavon shop subscription notifications without an id', function (): void {
    $this->postJson(route('callback.elavon.shop.subscription'), [])
        ->assertStatus(400);
});

it('redirects elavon enroll retry to confirm subscription instead of charging card', function (): void {
    $user = User::factory()->create([
        'role_id' => User::ROLES['Vendor'],
    ]);

    $shop = Shop::query()->create([
        'user_id' => $user->id,
        'user_name' => 'elavon-shop-'.uniqid(),
        'subscriptionMethod' => 'elavon',
        'subscription_id' => 'card-abc',
        'status' => 0,
    ]);

    $user->update(['shop_id' => $shop->id]);

    $this->actingAs($user)
        ->get(route('shop.enroll.subscription'))
        ->assertRedirect(route('shop.confirm.subscription', ['subscription_id' => 'card-abc']));
});

it('exposes elavon shop subscription test route in local and testing', function (): void {
    $shop = Shop::query()->create([
        'user_id' => User::factory()->create(['role_id' => User::ROLES['Vendor']])->id,
        'user_name' => 'elavon-test-'.uniqid(),
        'subscriptionMethod' => 'elavon',
    ]);

    $this->get(route('test.elavon.shop.subscription', ['shop' => $shop, 'action' => 'sync-plan']))
        ->assertSuccessful()
        ->assertJsonPath('action', 'sync-plan')
        ->assertJsonPath('synced', true);
});

it('requires subscription id for elavon plan test action', function (): void {
    $shop = Shop::query()->create([
        'user_id' => User::factory()->create(['role_id' => User::ROLES['Vendor']])->id,
        'user_name' => 'elavon-test-'.uniqid(),
        'subscriptionMethod' => 'elavon',
    ]);

    $this->get(route('test.elavon.shop.subscription', ['shop' => $shop, 'action' => 'plan']))
        ->assertStatus(422);
});
