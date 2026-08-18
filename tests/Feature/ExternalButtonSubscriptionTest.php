<?php

declare(strict_types=1);

use App\Models\ExternalOrder;
use App\Models\ExternalSubscription;
use App\Models\PaymentApi;
use App\Models\PaymentMethodAccess;
use App\Models\User;
use App\Services\ExternalButtonSubscriptionCharger;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

function createExternalButtonPlugin(): array
{
    $user = User::factory()->create([
        'role_id' => User::ROLES['External'],
    ]);
    $user->assignRole('external');

    $access = PaymentMethodAccess::query()->create([
        'user_id' => $user->id,
        'company_name' => 'Sub Plugin',
        'company_email' => 'sub-'.uniqid().'@example.com',
        'key' => (string) Str::uuid(),
        'fee' => 0,
        'paymentMethod' => 'elavon',
        'subscriptionMethod' => 'elavon',
        'status' => 1,
    ]);

    return [$user, $access];
}

it('updates plugin profile company fields from create-payment without storing them on the order', function (): void {
    [, $access] = createExternalButtonPlugin();
    $access->update([
        'company_name' => 'Old Company',
        'company_registration' => '111111111',
    ]);

    $api = PaymentApi::query()->create([
        'payment_method_access_id' => $access->id,
        'domain' => 'https://example.com',
        'success_redirect_url' => 'https://example.com/ok',
        'failed_redirect_url' => 'https://example.com/fail',
        'status' => true,
        'is_subscription' => false,
    ]);

    $this->postJson(route('iziipay.createPayment', $access->key), [
        'source_key' => $api->key,
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'amount' => 100,
        'currency' => 'NOK',
        'company_name' => 'Acme AS',
        'organization_number' => '999888777',
    ]);

    $order = ExternalOrder::query()->latest('id')->first();

    expect($access->fresh()->company_name)->toBe('Acme AS')
        ->and($access->fresh()->company_registration)->toBe('999888777');

    if ($order !== null) {
        expect($order->customer_company)->toBeNull()
            ->and($order->getAttributes())->not->toHaveKeys(['company_name', 'organization_number', 'company_registration']);
    }
});

it('does not overwrite plugin profile company fields when omitted from create-payment', function (): void {
    [, $access] = createExternalButtonPlugin();
    $access->update([
        'company_name' => 'Keep Company',
        'company_registration' => '222333444',
    ]);

    $api = PaymentApi::query()->create([
        'payment_method_access_id' => $access->id,
        'domain' => 'https://example.com',
        'success_redirect_url' => 'https://example.com/ok',
        'failed_redirect_url' => 'https://example.com/fail',
        'status' => true,
        'is_subscription' => false,
    ]);

    $this->postJson(route('iziipay.createPayment', $access->key), [
        'source_key' => $api->key,
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'amount' => 100,
        'currency' => 'NOK',
    ]);

    expect($access->fresh()->company_name)->toBe('Keep Company')
        ->and($access->fresh()->company_registration)->toBe('222333444');
});

it('rejects one-time create-payment for subscription source keys', function (): void {
    [, $access] = createExternalButtonPlugin();

    $api = PaymentApi::query()->create([
        'payment_method_access_id' => $access->id,
        'domain' => 'https://example.com',
        'success_redirect_url' => 'https://example.com/ok',
        'failed_redirect_url' => 'https://example.com/fail',
        'status' => true,
        'is_subscription' => true,
    ]);

    $this->postJson(route('iziipay.createPayment', $access->key), [
        'source_key' => $api->key,
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'amount' => 100,
        'currency' => 'NOK',
    ])
        ->assertStatus(400)
        ->assertJsonFragment([
            'message' => 'This source key is a subscription button. Use the create-subscription endpoint instead.',
        ]);
});

it('validates create-subscription requires interval_days and amount', function (): void {
    [, $access] = createExternalButtonPlugin();

    $api = PaymentApi::query()->create([
        'payment_method_access_id' => $access->id,
        'domain' => 'https://example.com',
        'success_redirect_url' => 'https://example.com/ok',
        'failed_redirect_url' => 'https://example.com/fail',
        'status' => true,
        'is_subscription' => true,
    ]);

    $this->postJson(route('iziipay.createSubscription', $access->key), [
        'source_key' => $api->key,
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'currency' => 'NOK',
    ])->assertStatus(422);
});

it('cancels an external button subscription via api', function (): void {
    [, $access] = createExternalButtonPlugin();

    $api = PaymentApi::query()->create([
        'payment_method_access_id' => $access->id,
        'domain' => 'https://example.com',
        'success_redirect_url' => 'https://example.com/ok',
        'failed_redirect_url' => 'https://example.com/fail',
        'status' => true,
        'is_subscription' => true,
    ]);

    $subscription = ExternalSubscription::query()->create([
        'payment_method_access_id' => $access->id,
        'api_id' => $api->id,
        'customer_name' => 'Jane',
        'customer_email' => 'jane@example.com',
        'amount' => 199,
        'currency' => 'NOK',
        'interval_days' => 30,
        'status' => 'ACTIVE',
        'stored_card_id' => 'card-1',
        'next_charge_at' => now()->addDays(10),
        'payment_method' => 'elavon',
    ]);

    $this->postJson(route('iziipay.cancelSubscription', $access->key), [
        'subscription_id' => $subscription->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('status', true);

    expect($subscription->fresh()->status)->toBe('CANCELED')
        ->and($subscription->fresh()->next_charge_at)->toBeNull();
});

it('charges due external button subscriptions via command', function (): void {
    [, $access] = createExternalButtonPlugin();

    $api = PaymentApi::query()->create([
        'payment_method_access_id' => $access->id,
        'domain' => 'https://example.com',
        'success_redirect_url' => 'https://example.com/ok',
        'failed_redirect_url' => 'https://example.com/fail',
        'status' => true,
        'is_subscription' => true,
    ]);

    $subscription = ExternalSubscription::query()->create([
        'payment_method_access_id' => $access->id,
        'api_id' => $api->id,
        'customer_name' => 'Jane',
        'customer_email' => 'jane@example.com',
        'amount' => 199,
        'currency' => 'NOK',
        'interval_days' => 7,
        'status' => 'ACTIVE',
        'stored_card_id' => 'card-1',
        'initial_transaction_id' => 'tx-1',
        'next_charge_at' => now()->subMinute(),
        'payment_method' => 'elavon',
    ]);

    $charger = new class extends ExternalButtonSubscriptionCharger
    {
        public function charge(ExternalSubscription $subscription): array
        {
            $subscription->charges()->create([
                'amount' => $subscription->amount,
                'currency' => $subscription->currency,
                'status' => true,
                'type' => 'renewal',
                'elavon_transaction_id' => 'tx-renewal',
            ]);
            $subscription->paid_at = now();
            $subscription->status = 'ACTIVE';
            $subscription->scheduleNextCharge();

            return ['status' => true, 'message' => 'Charged successfully.'];
        }
    };

    $result = $charger->chargeDue();

    expect($result['charged'])->toBe(1)
        ->and($subscription->fresh()->status)->toBe('ACTIVE')
        ->and($subscription->fresh()->next_charge_at?->greaterThan(now()))->toBeTrue()
        ->and($subscription->charges()->where('type', 'renewal')->count())->toBe(1);

    $this->artisan('external-button-subscriptions:charge')
        ->assertSuccessful();
});

it('creates a surfboard subscription payment link', function (): void {
    [, $access] = createExternalButtonPlugin();
    $access->update(['paymentMethod' => 'surfboard']);
    $access->createMetas([
        'surfboard_merchantId' => 'merchant-1',
        'surfboard_storeId' => 'store-1',
        'surfboard_terminalId' => 'terminal-pp',
    ]);

    $api = PaymentApi::query()->create([
        'payment_method_access_id' => $access->id,
        'domain' => 'https://example.com',
        'success_redirect_url' => 'https://example.com/ok',
        'failed_redirect_url' => 'https://example.com/fail',
        'status' => true,
        'is_subscription' => true,
    ]);

    Http::fake([
        '*/orders' => Http::response([
            'status' => 'SUCCESS',
            'data' => [
                'orderId' => 'ord-sub-1',
                'paymentPageLink' => 'https://pay.example/sub',
            ],
        ], 200),
    ]);

    $this->postJson(route('iziipay.createSubscription', $access->key), [
        'source_key' => $api->key,
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'amount' => 199,
        'currency' => 'NOK',
        'interval_days' => 14,
        'preferred_acquirer' => 'surfboard',
    ])
        ->assertSuccessful()
        ->assertJsonPath('url', 'https://pay.example/sub')
        ->assertJsonPath('subscription.payment_method', 'surfboard');

    expect(ExternalSubscription::query()->where('payment_id', 'ord-sub-1')->exists())->toBeTrue();
});

it('charges a due surfboard subscription renewal via MIT token', function (): void {
    [, $access] = createExternalButtonPlugin();
    $access->update(['paymentMethod' => 'surfboard']);
    $access->createMetas([
        'surfboard_merchantId' => 'merchant-1',
        'surfboard_storeId' => 'store-1',
        'surfboard_terminalId' => 'terminal-pp',
        'surfboard_mit_terminalId' => 'terminal-mit',
    ]);

    $api = PaymentApi::query()->create([
        'payment_method_access_id' => $access->id,
        'domain' => 'https://example.com',
        'success_redirect_url' => 'https://example.com/ok',
        'failed_redirect_url' => 'https://example.com/fail',
        'status' => true,
        'is_subscription' => true,
    ]);

    $subscription = ExternalSubscription::query()->create([
        'payment_method_access_id' => $access->id,
        'api_id' => $api->id,
        'customer_name' => 'Jane',
        'customer_email' => 'jane@example.com',
        'amount' => 199,
        'currency' => 'NOK',
        'interval_days' => 7,
        'status' => 'ACTIVE',
        'surfboard_token' => 'tok-abc',
        'stored_card_id' => 'tok-abc',
        'next_charge_at' => now()->subMinute(),
        'payment_method' => 'surfboard',
    ]);

    Http::fake([
        '*/orders' => Http::response([
            'status' => 'SUCCESS',
            'data' => ['orderId' => 'ord-renew-1'],
        ], 200),
        '*/payments' => Http::response([
            'status' => 'SUCCESS',
            'data' => ['paymentId' => 'pay-renew-1'],
        ], 200),
        '*/orders/ord-renew-1/status' => Http::response([
            'data' => ['orderStatus' => 'PAYMENT_COMPLETED', 'paymentId' => 'pay-renew-1'],
        ], 200),
    ]);

    $result = (new ExternalButtonSubscriptionCharger)->charge($subscription->fresh());

    expect($result['status'])->toBeTrue()
        ->and($subscription->fresh()->status)->toBe('ACTIVE')
        ->and($subscription->fresh()->next_charge_at?->greaterThan(now()))->toBeTrue()
        ->and($subscription->charges()->where('type', 'renewal')->where('status', true)->count())->toBe(1);
});
