<?php

declare(strict_types=1);

use App\Mail\PaymentMethodAccessMail;
use App\Models\PaymentMethodAccess;
use App\Models\Subscription;
use App\Models\User;
use App\Payment\Elavon\ElavonExternalSubscription;
use App\Payment\Elavon\ElavonExternalSubscriptionFactory;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

it('finalizes elavon hpp when returned to the subscription page with a session id', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'role_id' => User::ROLES['External'],
    ]);
    $user->assignRole('external');

    $access = PaymentMethodAccess::query()->create([
        'user_id' => $user->id,
        'company_name' => 'Plugin HPP Return',
        'company_email' => 'plugin-hpp-'.uniqid().'@example.com',
        'key' => (string) Str::uuid(),
        'fee' => 50,
        'subscriptionMethod' => 'elavon',
        'status' => 0,
    ]);

    $subscription = $access->subscription()->create([
        'key' => 'session-external-page',
        'url' => 'https://hpp.example.test/?sessionId=session-external-page',
        'fee' => 50,
        'status' => 0,
    ]);

    $elavon = Mockery::mock(ElavonExternalSubscription::class);
    $elavon->shouldReceive('finalizeHostedSubscriptionFromSession')
        ->once()
        ->with('session-external-page', Mockery::type(Subscription::class), Mockery::type('float'))
        ->andReturnUsing(function () use ($access, $subscription): array {
            $access->update([
                'status' => true,
                'shopperId' => 'shopper-external',
                'last_paid_at' => now(),
            ]);
            $subscription->update([
                'key' => 'card-external',
                'url' => null,
                'status' => 1,
                'establishment_status' => 1,
                'paid_at' => now(),
            ]);

            return [
                'status' => true,
                'data' => [
                    'cardId' => 'card-external',
                    'transactionId' => 'tx-external',
                ],
            ];
        });

    $factory = Mockery::mock(ElavonExternalSubscriptionFactory::class);
    $factory->shouldReceive('make')->andReturn($elavon);
    $this->app->instance(ElavonExternalSubscriptionFactory::class, $factory);

    $this->actingAs($user)
        ->get(route('external.subscription.payment', ['sessionId' => 'session-external-page']))
        ->assertRedirect(route('external.contract'));

    $access->refresh();
    $subscription->refresh();

    expect((bool) $access->status)->toBeTrue()
        ->and((int) $subscription->status)->toBe(1)
        ->and($subscription->key)->toBe('card-external');

    Mail::assertSent(PaymentMethodAccessMail::class);
});

it('does not finalize a pending elavon hpp session before elavon returns', function (): void {
    $user = User::factory()->create([
        'role_id' => User::ROLES['External'],
    ]);
    $user->assignRole('external');

    $access = PaymentMethodAccess::query()->create([
        'user_id' => $user->id,
        'company_name' => 'Plugin HPP Pending',
        'company_email' => 'plugin-pending-'.uniqid().'@example.com',
        'key' => (string) Str::uuid(),
        'fee' => 50,
        'subscriptionMethod' => 'elavon',
        'status' => 0,
    ]);

    $access->subscription()->create([
        'key' => 'session-pending-external',
        'url' => 'https://hpp.example.test/?sessionId=session-pending-external',
        'fee' => 50,
        'status' => 0,
    ]);

    $factory = Mockery::mock(ElavonExternalSubscriptionFactory::class);
    $factory->shouldNotReceive('make');
    $this->app->instance(ElavonExternalSubscriptionFactory::class, $factory);

    $this->actingAs($user)
        ->get(route('external.subscription.payment'))
        ->assertOk();
});

it('sends cancelled payments back to the subscription page with a message', function (): void {
    $user = User::factory()->create([
        'role_id' => User::ROLES['External'],
    ]);
    $user->assignRole('external');

    $access = PaymentMethodAccess::query()->create([
        'user_id' => $user->id,
        'company_name' => 'Plugin HPP Cancel',
        'company_email' => 'plugin-cancel-'.uniqid().'@example.com',
        'key' => (string) Str::uuid(),
        'fee' => 50,
        'subscriptionMethod' => 'elavon',
        'status' => 0,
    ]);

    $subscription = $access->subscription()->create([
        'key' => 'session-cancel-external',
        'fee' => 50,
        'status' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('external.subscription.cancel', $subscription))
        ->assertRedirect(route('external.subscription.payment'))
        ->assertSessionHasErrors();
});

it('starts hosted payment with return url pointing at the subscription page', function (): void {
    $user = User::factory()->create([
        'role_id' => User::ROLES['External'],
    ]);
    $user->assignRole('external');

    $access = PaymentMethodAccess::query()->create([
        'user_id' => $user->id,
        'company_name' => 'Plugin HPP Start',
        'company_email' => 'plugin-start-'.uniqid().'@example.com',
        'key' => (string) Str::uuid(),
        'fee' => 50,
        'subscriptionMethod' => 'elavon',
        'status' => 0,
        'company_address' => [
            'city' => 'Oslo',
            'street' => 'Main',
            'zip' => '0001',
        ],
        'company_domain' => 'https://example.com',
        'company_registration' => '123',
    ]);

    $subscription = $access->subscription()->create([
        'fee' => 50,
        'status' => 0,
    ]);

    $elavon = Mockery::mock(ElavonExternalSubscription::class);
    $elavon->shouldReceive('getPaymentLink')
        ->once()
        ->withArgs(function (float $amount, string $returnUrl, string $cancelUrl) use ($subscription): bool {
            return $returnUrl === route('external.subscription.payment', absolute: true)
                && $cancelUrl === route('external.subscription.cancel', $subscription, absolute: true);
        })
        ->andReturn([
            'status' => true,
            'data' => [
                'payment_id' => 'session-start-external',
                'url' => 'https://hpp.example.test/?sessionId=session-start-external',
            ],
        ]);

    $factory = Mockery::mock(ElavonExternalSubscriptionFactory::class);
    $factory->shouldReceive('make')->once()->with(Mockery::type(PaymentMethodAccess::class))->andReturn($elavon);
    $this->app->instance(ElavonExternalSubscriptionFactory::class, $factory);

    $this->actingAs($user)
        ->get(route('external.start-subscription', $subscription))
        ->assertRedirect('https://hpp.example.test/?sessionId=session-start-external');

    $subscription->refresh();

    expect($subscription->key)->toBe('session-start-external')
        ->and($subscription->url)->toBe('https://hpp.example.test/?sessionId=session-start-external');
});
