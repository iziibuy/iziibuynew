<?php

declare(strict_types=1);

use App\Models\PaymentMethodAccess;
use App\Models\User;
use App\Payment\Elavon\ElavonExternalSubscription;
use App\Payment\Elavon\ElavonExternalSubscriptionFactory;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

function trialUser(): User
{
    $user = User::factory()->create(['role_id' => User::ROLES['External']]);
    $user->assignRole('external');

    return $user;
}

function trialAccess(User $user, array $attributes = []): PaymentMethodAccess
{
    $access = PaymentMethodAccess::query()->create(array_merge([
        'user_id' => $user->id,
        'company_name' => 'Trial Plugin',
        'company_email' => 'trial-'.uniqid().'@example.com',
        'key' => (string) Str::uuid(),
        'fee' => 50,
        'subscriptionMethod' => 'quickpay',
        'status' => 0,
    ], $attributes));

    $access->subscription()->create(['key' => null, 'fee' => 50, 'status' => 0]);

    return $access;
}

function mockElavon(Closure $configure): ElavonExternalSubscription
{
    $elavon = Mockery::mock(ElavonExternalSubscription::class);
    $configure($elavon);

    $factory = Mockery::mock(ElavonExternalSubscriptionFactory::class);
    $factory->shouldReceive('make')->andReturn($elavon);
    app()->instance(ElavonExternalSubscriptionFactory::class, $factory);

    return $elavon;
}

it('shows the trial button for an eligible user and hides it while a trial is running', function (): void {
    $user = trialUser();
    $access = trialAccess($user);

    $this->actingAs($user)
        ->get(route('external.subscription.payment'))
        ->assertOk()
        ->assertSee(route('external.start-trial', $access->subscription), false);

    $access->update(['trial_used' => true, 'trial_ends_at' => now()->addDays(3)]);

    $this->actingAs($user->fresh())
        ->get(route('external.subscription.payment'))
        ->assertOk()
        ->assertDontSee(route('external.start-trial', $access->subscription), false);
});

it('starts the trial without any Elavon call and keeps status unchanged', function (): void {
    $user = trialUser();
    $access = trialAccess($user);

    $factory = Mockery::mock(ElavonExternalSubscriptionFactory::class);
    $factory->shouldNotReceive('make');
    app()->instance(ElavonExternalSubscriptionFactory::class, $factory);

    $this->actingAs($user)
        ->post(route('external.start-trial', $access->subscription))
        ->assertRedirect(route('external.contract'));

    $access->refresh();

    expect($access->trial_used)->toBeTrue()
        ->and($access->trial_started_at)->not->toBeNull()
        ->and($access->trial_ends_at->isSameDay(now()->addDays(7)))->toBeTrue()
        ->and($access->status)->toBeFalse()
        ->and($access->onTrial())->toBeTrue();
});

it('grants dashboard access with a days-left banner during the trial', function (): void {
    $user = trialUser();
    $access = trialAccess($user);

    $this->actingAs($user)->post(route('external.start-trial', $access->subscription));

    $this->actingAs($user->fresh())
        ->get(route('external.dashboard'))
        ->assertOk()
        ->assertSee(__('words.trial_days_left', ['days' => 7]), false)
        ->assertSee(route('external.subscription.payment'), false);
});

it('redirects to the payment page once the trial has ended and allows a new trial', function (): void {
    $user = trialUser();
    $access = trialAccess($user);

    $this->actingAs($user)->post(route('external.start-trial', $access->subscription));

    $this->actingAs($user->fresh())
        ->post(route('external.start-trial', $access->subscription))
        ->assertRedirect(route('external.subscription.payment'))
        ->assertSessionHasErrors();

    $this->travel(8)->days();

    $this->actingAs($user->fresh())
        ->get(route('external.dashboard'))
        ->assertRedirect(route('external.subscription.payment'));

    $this->actingAs($user->fresh())
        ->get(route('external.subscription.payment'))
        ->assertOk()
        ->assertSee(route('external.start-trial', $access->subscription), false);

    $this->actingAs($user->fresh())
        ->post(route('external.start-trial', $access->subscription))
        ->assertRedirect(route('external.contract'));

    $access->refresh();

    expect($access->onTrial())->toBeTrue()
        ->and($access->trialDaysLeft())->toBe(7);

    $this->actingAs($user->fresh())->get(route('external.dashboard'))->assertOk();
});

it('allows a trial for a lapsed account that has paid before', function (): void {
    $user = trialUser();
    $access = trialAccess($user);
    $access->subscription->charges()->create(['amount' => 50, 'status' => true]);

    expect($access->fresh()->canStartTrial())->toBeTrue();
});

it('settles the trial on a paid signup and bills monthly afterwards but never during the trial', function (): void {
    Mail::fake();
    $user = trialUser();
    $access = trialAccess($user);
    $subscription = $access->subscription;

    $this->actingAs($user)->post(route('external.start-trial', $subscription));

    $this->artisan('payment-method-access:charge', ['status' => 1, '--dry-run' => true, '--id' => $access->id])
        ->expectsOutputToContain('No payment method accesses matched the due criteria.')
        ->assertSuccessful();

    mockElavon(function ($elavon) use ($access, $subscription): void {
        $elavon->shouldReceive('finalizeHostedSubscriptionFromSession')
            ->once()
            ->andReturnUsing(function () use ($access, $subscription): array {
                $access->update([
                    'status' => true,
                    'subscriptionMethod' => PaymentMethodAccess::SUBSCRIPTION_METHOD_ELAVON,
                    'last_paid_at' => now()->subMonths(2),
                ]);
                $subscription->update(['key' => 'card-paid', 'status' => 1, 'establishment_status' => 1, 'paid_at' => now()]);

                return ['status' => true, 'data' => ['cardId' => 'card-paid', 'transactionId' => 'tx']];
            });
    });

    $this->actingAs($user->fresh())
        ->get(route('external.subscription.payment', ['sessionId' => 'sess-paid']))
        ->assertRedirect(route('external.contract'));

    $access->refresh();

    expect($access->status)->toBeTrue()
        ->and($access->trial_charged_at)->not->toBeNull()
        ->and($access->onTrial())->toBeFalse();

    $this->artisan('payment-method-access:charge', ['status' => 1, '--dry-run' => true, '--id' => $access->id])
        ->expectsOutputToContain('Found 1 payment method access(es) to process.')
        ->assertSuccessful();
});

it('expires trials without any payment calls', function (): void {
    $access = trialAccess(trialUser(), ['trial_used' => true, 'trial_ends_at' => now()->subHour()]);

    $factory = Mockery::mock(ElavonExternalSubscriptionFactory::class);
    $factory->shouldNotReceive('make');
    app()->instance(ElavonExternalSubscriptionFactory::class, $factory);

    $this->artisan('payment-method-access:expire-trials')
        ->expectsOutputToContain('Expired trials awaiting subscription: 1')
        ->assertSuccessful();

    expect($access->fresh()->status)->toBeFalse();
});
