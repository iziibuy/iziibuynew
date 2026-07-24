<?php

declare(strict_types=1);

use App\Models\Shipping;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

it('returns a validation error instead of 404 for invalid checkout login', function (): void {
    $owner = User::factory()->create(['role_id' => User::ROLES['Vendor']]);

    $shop = Shop::query()->create([
        'user_id' => $owner->id,
        'user_name' => 'checkout-login-'.uniqid(),
        'subscription_id' => 'sub-'.uniqid(),
        'subscriptionMethod' => 'elavon',
        'paymentMethod' => 'surfboard',
        'status' => 1,
    ]);
    $shop->createMetas(['shipping_force_register' => 'Yes']);

    $shipping = Shipping::query()->create([
        'shop_id' => $shop->id,
        'shipping_method' => 'Standard',
        'shipping_cost' => 0,
    ]);

    $this->from(route('shop.home', $shop->user_name))
        ->post(route('checkout.store', ['user_name' => $shop->user_name, 'direct' => 1]), [
            'shipping' => $shipping->id,
            'payment' => 'visa',
            'terms' => 'terms',
            'user' => [
                'login' => [
                    'email' => 'missing-user-'.uniqid().'@example.com',
                    'password' => 'secret',
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors();
});

it('ignores empty login payload during checkout store', function (): void {
    $owner = User::factory()->create(['role_id' => User::ROLES['Vendor']]);

    $shop = Shop::query()->create([
        'user_id' => $owner->id,
        'user_name' => 'checkout-empty-login-'.uniqid(),
        'subscription_id' => 'sub-'.uniqid(),
        'subscriptionMethod' => 'elavon',
        'paymentMethod' => 'surfboard',
        'status' => 1,
    ]);
    $shop->createMetas(['shipping_force_register' => 'Yes']);

    $shipping = Shipping::query()->create([
        'shop_id' => $shop->id,
        'shipping_method' => 'Standard',
        'shipping_cost' => 0,
    ]);

    $this->from(route('shop.home', $shop->user_name))
        ->post(route('checkout.store', ['user_name' => $shop->user_name, 'direct' => 1]), [
            'shipping' => $shipping->id,
            'payment' => 'visa',
            'terms' => 'terms',
            'user' => [
                'login' => [
                    'email' => '',
                    'password' => '',
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors();
});
