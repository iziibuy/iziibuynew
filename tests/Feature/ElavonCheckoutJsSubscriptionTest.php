<?php

declare(strict_types=1);

use App\Elavon\Converge2\Response\OrderResponse;
use App\Models\ExternalSubscription;
use App\Models\PaymentApi;
use App\Models\PaymentMethodAccess;
use App\Models\User;
use App\Payment\Elavon\ApiElavonButtonSubscription;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

/**
 * @return array{0: PaymentMethodAccess, 1: PaymentApi}
 */
function createCheckoutJsSubscriptionButton(array $apiOverrides = []): array
{
    $user = User::factory()->create(['role_id' => User::ROLES['External']]);
    $user->assignRole('external');

    $access = PaymentMethodAccess::query()->create([
        'user_id' => $user->id,
        'company_name' => 'Subscription Plugin',
        'company_email' => 'subscription-'.uniqid().'@example.com',
        'key' => (string) Str::uuid(),
        'fee' => 0,
        'paymentMethod' => 'elavon',
        'subscriptionMethod' => 'elavon',
        'status' => 1,
    ]);

    $access->createMetas([
        'elavon_merchant_alias' => 'alias_test',
        'elavon_public_key' => 'pk_test',
        'elavon_secret_key' => 'sk_test',
        'site_mode' => 'test',
    ]);

    if (Schema::hasColumn('payment_method_accesses', 'site_mode')) {
        $access->forceFill(['site_mode' => 'test'])->save();
    }

    $api = PaymentApi::query()->create(array_merge([
        'payment_method_access_id' => $access->id,
        'domain' => 'https://merchant.example',
        'success_redirect_url' => 'https://merchant.example/ok',
        'failed_redirect_url' => 'https://merchant.example/fail',
        'status' => true,
        'is_subscription' => true,
        'elavon_link_mode' => PaymentApi::ELAVON_LINK_MODE_CHECKOUTJS,
    ], $apiOverrides));

    return [$access->fresh(), $api];
}

function createCheckoutJsSubscription(PaymentMethodAccess $access, PaymentApi $api, array $overrides = []): ExternalSubscription
{
    return ExternalSubscription::query()->create(array_merge([
        'payment_method_access_id' => $access->id,
        'api_id' => $api->id,
        'customer_name' => 'Jane Doe',
        'customer_email' => 'jane@example.com',
        'customer_phone' => '12345678',
        'customer_address' => 'Main Street 1',
        'customer_post_code' => '0150',
        'amount' => 149,
        'currency' => 'NOK',
        'interval_days' => 30,
        'description' => 'Monthly plan',
        'orderId' => 'SUB-1001',
        'status' => 'PENDING',
        'payment_method' => 'elavon',
        'payment_id' => 'session_sub_123',
    ], $overrides));
}

/**
 * Binds a gateway double that never calls Elavon.
 *
 * @param  array{status:bool,message:?string}|null  $finalizeResult
 */
function fakeElavonSubscriptionGateway(?array $finalizeResult = null): void
{
    app()->bind(ApiElavonButtonSubscription::class, function ($app, array $parameters) use ($finalizeResult) {
        return new class($parameters['subscription'], $finalizeResult) extends ApiElavonButtonSubscription
        {
            public function __construct(ExternalSubscription $subscription, private ?array $finalizeResult)
            {
                parent::__construct($subscription);
            }

            public function ensureCheckoutJsSession(): array
            {
                return ['status' => true, 'payment_id' => (string) $this->subscription->payment_id];
            }

            public function finalizeCheckoutJsSubscription(string $sessionId): array
            {
                if ($this->finalizeResult === null) {
                    return parent::finalizeCheckoutJsSubscription($sessionId);
                }

                if ($this->finalizeResult['status']) {
                    $this->subscription->update([
                        'status' => 'ACTIVE',
                        'stored_card_id' => 'card_saved_1',
                        'next_charge_at' => now()->addDays(30),
                    ]);
                }

                return $this->finalizeResult;
            }
        };
    });
}

it('creates a tokenize-only hosted fields session for checkoutjs subscription buttons', function (): void {
    [$access, $api] = createCheckoutJsSubscriptionButton();
    $subscription = createCheckoutJsSubscription($access, $api);

    $gateway = new class($subscription) extends ApiElavonButtonSubscription
    {
        public function sessionBody(OrderResponse $order): array
        {
            return $this->makePaymentSessionCreateBody($order);
        }
    };

    $order = Mockery::mock(OrderResponse::class);
    $order->shouldReceive('getId')->andReturn('order_1');

    $body = $gateway->sessionBody($order);

    expect($gateway->usesCheckoutJs())->toBeTrue()
        ->and($body['hppType'])->toBe('hostedPaymentFields')
        ->and($body['doCreateTransaction'])->toBeFalse()
        ->and($body['doThreeDSecure'])->toBe(1)
        ->and($body)->not->toHaveKey('returnUrl');
});

it('keeps the hosted payment page for subscription buttons in hosted mode', function (): void {
    [$access, $api] = createCheckoutJsSubscriptionButton(['elavon_link_mode' => PaymentApi::ELAVON_LINK_MODE_HOSTED]);
    $subscription = createCheckoutJsSubscription($access, $api);

    $gateway = new class($subscription) extends ApiElavonButtonSubscription
    {
        public function sessionBody(OrderResponse $order): array
        {
            return $this->makePaymentSessionCreateBody($order);
        }
    };

    $order = Mockery::mock(OrderResponse::class);
    $order->shouldReceive('getId')->andReturn('order_1');

    expect($gateway->usesCheckoutJs())->toBeFalse()
        ->and($gateway->sessionBody($order)['hppType'])->toBe('fullPageRedirect');

    $this->get(route('elavon.checkoutjs.subscription.pay', $subscription->uuid))->assertNotFound();
});

it('renders the subscription checkoutjs page with tokenization and billing details', function (): void {
    fakeElavonSubscriptionGateway();
    [$access, $api] = createCheckoutJsSubscriptionButton();
    $subscription = createCheckoutJsSubscription($access, $api);

    $this->get(route('elavon.checkoutjs.subscription.pay', $subscription->uuid))
        ->assertOk()
        ->assertSee('session_sub_123')
        ->assertSee('hostedCardCreated')
        ->assertSee('Every 30 days')
        ->assertSee('every 30 days until you cancel')
        ->assertSee(route('elavon.checkoutjs.subscription.cancel', $subscription->uuid), false);
});

it('redirects active subscriptions straight to the success url', function (): void {
    fakeElavonSubscriptionGateway();
    [$access, $api] = createCheckoutJsSubscriptionButton();
    $subscription = createCheckoutJsSubscription($access, $api, ['status' => 'ACTIVE']);

    $this->get(route('elavon.checkoutjs.subscription.pay', $subscription->uuid))
        ->assertRedirect('https://merchant.example/ok?subscription='.$subscription->id.'&order=SUB-1001&status=active');
});

it('rejects a complete request for a different payment session', function (): void {
    fakeElavonSubscriptionGateway();
    [$access, $api] = createCheckoutJsSubscriptionButton();
    $subscription = createCheckoutJsSubscription($access, $api);

    $this->postJson(route('elavon.checkoutjs.subscription.complete', $subscription->uuid), [
        'session_id' => 'another_session',
    ])
        ->assertUnprocessable()
        ->assertJson(['status' => false, 'message' => 'Payment session mismatch.']);

    expect($subscription->fresh()->status)->toBe('PENDING');
});

it('activates the subscription after the card is tokenized and saved', function (): void {
    fakeElavonSubscriptionGateway(['status' => true, 'message' => null]);
    [$access, $api] = createCheckoutJsSubscriptionButton();
    $subscription = createCheckoutJsSubscription($access, $api);

    $this->postJson(route('elavon.checkoutjs.subscription.complete', $subscription->uuid), [
        'session_id' => 'session_sub_123',
    ])
        ->assertOk()
        ->assertJson([
            'status' => true,
            'redirect_url' => 'https://merchant.example/ok?subscription='.$subscription->id.'&order=SUB-1001&status=active',
        ]);

    $subscription->refresh();

    expect($subscription->status)->toBe('ACTIVE')
        ->and($subscription->stored_card_id)->toBe('card_saved_1')
        ->and($subscription->next_charge_at)->not->toBeNull();
});

it('returns the failure message when the card cannot be saved', function (): void {
    fakeElavonSubscriptionGateway(['status' => false, 'message' => 'Card was not saved. Please try again.']);
    [$access, $api] = createCheckoutJsSubscriptionButton();
    $subscription = createCheckoutJsSubscription($access, $api);

    $this->postJson(route('elavon.checkoutjs.subscription.complete', $subscription->uuid), [
        'session_id' => 'session_sub_123',
    ])
        ->assertUnprocessable()
        ->assertJson(['status' => false, 'message' => 'Card was not saved. Please try again.', 'redirect_url' => null]);
});

it('cancels a pending subscription and redirects to the failed url', function (): void {
    [$access, $api] = createCheckoutJsSubscriptionButton();
    $subscription = createCheckoutJsSubscription($access, $api);

    $this->get(route('elavon.checkoutjs.subscription.cancel', $subscription->uuid))
        ->assertRedirect('https://merchant.example/fail?subscription='.$subscription->id.'&order=SUB-1001&status=canceled');

    $subscription->refresh();

    expect($subscription->status)->toBe('CANCELED')
        ->and($subscription->canceled_at)->not->toBeNull();
});
