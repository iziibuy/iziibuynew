<?php

declare(strict_types=1);

use App\Models\EnterpriseOnboarding;
use App\Models\PaymentMethodAccess;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

it('shows subscribe with new card on shop subscription when a card exists', function (): void {
    $user = User::factory()->create([
        'role_id' => User::ROLES['Vendor'],
    ]);
    $user->assignRole('vendor');

    $shop = Shop::query()->create([
        'user_id' => $user->id,
        'user_name' => 'shop-new-card-'.uniqid(),
        'subscription_id' => 'card-123',
        'subscriptionMethod' => 'elavon',
        'paymentMethod' => 'elavon',
        'status' => 0,
    ]);
    $user->update(['shop_id' => $shop->id]);

    $this->actingAs($user)
        ->get(route('shop.subscription.payment'))
        ->assertOk()
        ->assertSee(__('words.subscribe_with_new_card'), false)
        ->assertSee(route('shop.enroll.subscription', ['type' => 'new_card']), false);
});

it('shows subscribe with new card on enterprise subscription when a stored card exists', function (): void {
    $user = User::factory()->create([
        'role_id' => User::ROLES['Enterprise'],
    ]);
    $user->assignRole('enterprise');

    $enterprise = EnterpriseOnboarding::query()->create([
        'user_id' => $user->id,
        'company_name' => 'Test Enterprise',
        'company_email' => 'enterprise-'.uniqid().'@example.com',
        'company_domain' => 'https://example.com',
        'company_registration' => '123456',
        'company_address' => [
            'city' => 'Oslo',
            'street' => 'Main',
            'zip' => '0001',
        ],
        'shopperId' => 'shopper-abc',
        'subscriptionMethod' => 'elavon',
        'status' => 0,
    ]);

    $subscription = $enterprise->subscription()->create([
        'key' => 'stored-card-xyz',
        'fee' => 100,
        'status' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('enterprise.subscription.payment'))
        ->assertOk()
        ->assertSee(__('words.subscribe_with_new_card'), false)
        ->assertSee('type=new_card', false);
});

it('shows subscribe with new card on plugin subscription when a stored card exists', function (): void {
    $user = User::factory()->create([
        'role_id' => User::ROLES['External'],
    ]);
    $user->assignRole('external');

    $access = PaymentMethodAccess::query()->create([
        'user_id' => $user->id,
        'company_name' => 'Plugin Co',
        'company_email' => 'plugin-'.uniqid().'@example.com',
        'key' => (string) Str::uuid(),
        'fee' => 50,
        'shopperId' => 'shopper-plugin',
        'subscriptionMethod' => 'elavon',
        'status' => 0,
    ]);

    $access->subscription()->create([
        'key' => 'stored-card-plugin',
        'fee' => 50,
        'status' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('external.subscription.payment'))
        ->assertOk()
        ->assertSee(__('words.subscribe_with_new_card'), false)
        ->assertSee('type=new_card', false);
});

it('clears enterprise stored card when starting with new card', function (): void {
    $user = User::factory()->create([
        'role_id' => User::ROLES['Enterprise'],
    ]);
    $user->assignRole('enterprise');

    $enterprise = EnterpriseOnboarding::query()->create([
        'user_id' => $user->id,
        'company_name' => 'Clear Card Enterprise',
        'company_email' => 'clear-'.uniqid().'@example.com',
        'company_domain' => 'https://example.com',
        'company_registration' => '999',
        'company_address' => [
            'city' => 'Oslo',
            'street' => 'Main',
            'zip' => '0001',
        ],
        'shopperId' => 'old-shopper',
        'subscriptionMethod' => 'elavon',
        'status' => 0,
    ]);

    $subscription = $enterprise->subscription()->create([
        'key' => 'old-card',
        'fee' => 100,
        'status' => 1,
        'establishment_status' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('enterprise.start-subscription', [$subscription, 'type' => 'new_card']));

    $subscription->refresh();
    $enterprise->refresh();

    expect($subscription->key)->toBeNull()
        ->and($enterprise->shopperId)->toBeNull()
        ->and((int) $subscription->status)->toBe(0)
        ->and((int) $subscription->establishment_status)->toBe(0);
});
