<?php

declare(strict_types=1);

use App\Elavon\Converge2\Response\StoredCardResponse;
use App\Mail\ShopInvoice;
use App\Models\Shop;
use App\Models\User;
use App\Payment\Elavon\ElavonShopSubscription;
use App\Payment\Elavon\ElavonShopSubscriptionFactory;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

it('activates the shop after elavon hpp return even if stored card lookup fails', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'role_id' => User::ROLES['Vendor'],
    ]);
    $user->assignRole('vendor');

    $shop = Shop::query()->create([
        'user_id' => $user->id,
        'user_name' => 'shop-hpp-return-'.uniqid(),
        'subscriptionMethod' => 'elavon',
        'paymentMethod' => 'elavon',
        'status' => 0,
        'establishment' => 0,
        'payment_order_id' => 'session-abc',
        'monthly_cost' => 0,
        'establishment_cost' => 0,
        'per_user_fee' => 0,
    ]);
    $user->update(['shop_id' => $shop->id]);

    $storedCard = Mockery::mock(StoredCardResponse::class);
    $storedCard->shouldReceive('isSuccess')->andReturn(false);

    $elavon = Mockery::mock(ElavonShopSubscription::class);
    $elavon->shouldReceive('finalizeHostedSubscriptionFromSession')
        ->once()
        ->with('session-abc')
        ->andReturnUsing(function () use ($shop): array {
            $shop->update([
                'subscription_id' => 'card-xyz',
                'shopperId' => 'shopper-1',
                'elavon_initial_transaction_id' => 'tx-1',
                'payment_order_id' => 'tx-1',
            ]);

            return [
                'status' => true,
                'data' => [
                    'cardId' => 'card-xyz',
                    'transactionId' => 'tx-1',
                ],
            ];
        });
    $elavon->shouldReceive('getStoredCardResource')->andReturn($storedCard);

    $factory = Mockery::mock(ElavonShopSubscriptionFactory::class);
    $factory->shouldReceive('make')->andReturn($elavon);
    $this->app->instance(ElavonShopSubscriptionFactory::class, $factory);

    $this->actingAs($user)
        ->get(route('shop.subscription.elavon.return', ['sessionId' => 'session-abc']))
        ->assertRedirect(route('shop.complete.signup'));

    $shop->refresh();

    expect((int) $shop->status)->toBe(1)
        ->and((int) $shop->establishment)->toBe(1)
        ->and($shop->subscription_id)->toBe('card-xyz')
        ->and($shop->paid_at)->not->toBeNull();

    Mail::assertSent(ShopInvoice::class);
});

it('activates the shop when elavon hpp returns to the subscription page', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'role_id' => User::ROLES['Vendor'],
    ]);
    $user->assignRole('vendor');

    $shop = Shop::query()->create([
        'user_id' => $user->id,
        'user_name' => 'shop-hpp-subscription-'.uniqid(),
        'subscriptionMethod' => 'elavon',
        'paymentMethod' => 'elavon',
        'status' => 0,
        'establishment' => 0,
        'payment_order_id' => 'session-subscription-page',
        'payment_url' => 'https://hpp.example.test/?sessionId=session-subscription-page',
        'monthly_cost' => 0,
        'establishment_cost' => 0,
        'per_user_fee' => 0,
    ]);
    $user->update(['shop_id' => $shop->id]);

    $storedCard = Mockery::mock(StoredCardResponse::class);
    $storedCard->shouldReceive('isSuccess')->andReturn(false);

    $elavon = Mockery::mock(ElavonShopSubscription::class);
    $elavon->shouldReceive('finalizeHostedSubscriptionFromSession')
        ->once()
        ->with('session-subscription-page')
        ->andReturnUsing(function () use ($shop): array {
            $shop->update([
                'subscription_id' => 'card-from-subscription-page',
                'shopperId' => 'shopper-subscription-page',
                'elavon_initial_transaction_id' => 'tx-subscription-page',
                'payment_order_id' => 'tx-subscription-page',
                'payment_url' => null,
            ]);

            return [
                'status' => true,
                'data' => [
                    'cardId' => 'card-from-subscription-page',
                    'transactionId' => 'tx-subscription-page',
                ],
            ];
        });
    $elavon->shouldReceive('getStoredCardResource')->andReturn($storedCard);

    $factory = Mockery::mock(ElavonShopSubscriptionFactory::class);
    $factory->shouldReceive('make')->andReturn($elavon);
    $this->app->instance(ElavonShopSubscriptionFactory::class, $factory);

    $this->actingAs($user)
        ->get(route('shop.subscription.payment'))
        ->assertRedirect(route('shop.complete.signup'));

    $shop->refresh();

    expect((int) $shop->status)->toBe(1)
        ->and((int) $shop->establishment)->toBe(1)
        ->and($shop->subscription_id)->toBe('card-from-subscription-page')
        ->and($shop->paid_at)->not->toBeNull();

    Mail::assertSent(ShopInvoice::class);
});

it('sends cancelled payments back to the subscription page with a message', function (): void {
    $user = User::factory()->create([
        'role_id' => User::ROLES['Vendor'],
    ]);
    $user->assignRole('vendor');

    $shop = Shop::query()->create([
        'user_id' => $user->id,
        'user_name' => 'shop-hpp-cancel-'.uniqid(),
        'subscriptionMethod' => 'elavon',
        'status' => 0,
    ]);
    $user->update(['shop_id' => $shop->id]);

    $this->actingAs($user)
        ->get(route('shop.subscription.elavon.cancel'))
        ->assertRedirect(route('shop.subscription.payment'))
        ->assertSessionHasErrors();
});
