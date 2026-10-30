<?php

declare(strict_types=1);

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

it('shows the trial button for an eligible user and hides it once the trial is used', function (): void {
    $user = trialUser();
    $access = trialAccess($user);

    $this->actingAs($user)
        ->get(route('external.subscription.payment'))
        ->assertOk()
        ->assertSee(route('external.start-trial', $access->subscription), false);

    $access->update(['trial_used' => true]);

    $this->actingAs($user)
        ->get(route('external.subscription.payment'))
        ->assertOk()
        ->assertDontSee(route('external.start-trial', $access->subscription), false);
});

it('creates a zero amount hpp session and never charges when starting a trial', function (): void {
    $user = trialUser();
    $access = trialAccess($user);

    mockElavon(function ($elavon): void {
        $elavon->shouldReceive('getPaymentLink')
            ->once()
            ->with(0, Mockery::type('string'), Mockery::type('string'))
            ->andReturn(['status' => true, 'data' => ['payment_id' => 'sess-trial', 'url' => 'https://hpp.example.test/?sessionId=sess-trial']]);
        $elavon->shouldNotReceive('charge');
    });

    $this->actingAs($user)
        ->get(route('external.start-trial', $access->subscription))
        ->assertRedirect('https://hpp.example.test/?sessionId=sess-trial')
        ->assertSessionHas('external_trial_pending', $access->subscription->id);
});

it('starts the trial on hpp return and grants access', function (): void {
    Mail::fake();
    $user = trialUser();
    $access = trialAccess($user);
    $subscription = $access->subscription;

    mockElavon(function ($elavon) use ($access, $subscription): void {
        $elavon->shouldReceive('finalizeHostedSubscriptionFromSession')
            ->once()
            ->with('sess-trial', Mockery::type(Subscription::class), 0)
            ->andReturnUsing(function () use ($access, $subscription): array {
                $access->update([
                    'status' => true,
                    'subscriptionMethod' => PaymentMethodAccess::SUBSCRIPTION_METHOD_ELAVON,
                    'last_paid_at' => now(),
                ]);
                $subscription->update(['key' => 'card-trial', 'status' => 1, 'establishment_status' => 1, 'paid_at' => now()]);

                return ['status' => true, 'data' => ['cardId' => 'card-trial', 'transactionId' => null]];
            });
    });

    $this->actingAs($user)
        ->withSession(['external_trial_pending' => $subscription->id])
        ->get(route('external.subscription.payment', ['sessionId' => 'sess-trial']))
        ->assertRedirect(route('external.contract'))
        ->assertSessionMissing('external_trial_pending');

    $access->refresh();

    expect($access->trial_used)->toBeTrue()
        ->and($access->status)->toBeTrue()
        ->and($access->trial_started_at)->not->toBeNull()
        ->and($access->trial_ends_at->isSameDay(now()->addDays(7)))->toBeTrue()
        ->and($access->onTrial())->toBeTrue();

    $user->refresh();

    $this->actingAs($user)->get(route('external.dashboard'))->assertOk();
});

it('refuses a second trial for the same access and for another access of the same user', function (): void {
    $user = trialUser();
    $first = trialAccess($user, ['trial_used' => true]);
    $second = trialAccess($user, ['company_name' => 'Second Plugin']);

    mockElavon(function ($elavon): void {
        $elavon->shouldNotReceive('getPaymentLink');
    });

    foreach ([$first, $second] as $access) {
        expect($access->fresh()->canStartTrial())->toBeFalse();

        $this->actingAs($user)
            ->get(route('external.start-trial', $access->subscription))
            ->assertRedirect(route('external.subscription.payment'))
            ->assertSessionHasErrors();
    }
});

it('charges only expired trials once and deactivates on failure', function (): void {
    $due = trialAccess(trialUser(), [
        'status' => 1, 'trial_used' => true, 'trial_started_at' => now()->subDays(8), 'trial_ends_at' => now()->subHour(),
    ]);
    $due->subscription->update(['key' => 'card-due', 'status' => 1]);

    $running = trialAccess(trialUser(), [
        'status' => 1, 'trial_used' => true, 'trial_started_at' => now()->subDay(), 'trial_ends_at' => now()->addDays(6),
    ]);
    $running->subscription->update(['key' => 'card-running', 'status' => 1]);

    mockElavon(function ($elavon): void {
        $elavon->shouldReceive('charge')
            ->once()
            ->with(Mockery::type('float'), Mockery::type(Subscription::class), ['type' => 'trial_conversion'])
            ->andReturn(['status' => true, 'data' => []]);
    });

    $this->artisan('payment-method-access:charge-trials')->assertSuccessful();
    $this->artisan('payment-method-access:charge-trials')->assertSuccessful();

    expect($due->fresh()->trial_charged_at)->not->toBeNull()
        ->and($running->fresh()->trial_charged_at)->toBeNull();
});

it('deactivates the access when the trial conversion charge fails', function (): void {
    $due = trialAccess(trialUser(), [
        'status' => 1, 'trial_used' => true, 'trial_ends_at' => now()->subMinute(),
    ]);
    $due->subscription->update(['key' => 'card-fail', 'status' => 1]);

    mockElavon(function ($elavon): void {
        $elavon->shouldReceive('charge')->once()->andReturn(['status' => false, 'data' => ['message' => 'Declined']]);
    });

    $this->artisan('payment-method-access:charge-trials')->assertFailed();

    expect($due->fresh()->status)->toBeFalse()
        ->and($due->fresh()->trial_charged_at)->toBeNull();
});

it('skips accesses on trial in the monthly charge command', function (): void {
    $onTrial = trialAccess(trialUser(), [
        'status' => 1, 'trial_used' => true, 'trial_ends_at' => now()->addDays(3), 'last_paid_at' => now()->subMonths(2),
    ]);
    $onTrial->subscription->update(['key' => 'card-trial', 'status' => 1]);

    $this->artisan('payment-method-access:charge', ['status' => 1, '--dry-run' => true, '--id' => $onTrial->id])
        ->expectsOutputToContain('No payment method accesses matched the due criteria.')
        ->assertSuccessful();
});
