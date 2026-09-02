<?php

declare(strict_types=1);

use App\Models\PaymentMethodAccess;
use App\Models\User;
use Illuminate\Support\Str;

it('reports when no payment method accesses are due', function (): void {
    $this->artisan('payment-method-access:charge', ['status' => 1, '--dry-run' => true])
        ->expectsOutputToContain('No payment method accesses matched the due criteria.')
        ->assertSuccessful();
});

it('lists due payment method accesses in dry-run without charging', function (): void {
    $paidAt = now()->subMonth()->startOfMonth();

    $access = PaymentMethodAccess::query()->create([
        'user_id' => User::factory()->create()->id,
        'company_name' => 'Acme Plugin',
        'company_email' => 'acme-'.uniqid().'@example.com',
        'key' => (string) Str::uuid(),
        'subscriptionMethod' => 'elavon',
        'paymentMethod' => 'elavon',
        'status' => 1,
        'last_paid_at' => $paidAt,
    ]);

    $access->subscription()->create([
        'key' => 'stored-card-abc',
        'fee' => 149,
        'status' => 1,
        'paid_at' => now()->subMonth(),
    ]);

    $this->artisan('payment-method-access:charge', ['status' => 1, '--dry-run' => true, '--id' => $access->id])
        ->expectsOutputToContain('Found 1 payment method access(es) to process.')
        ->expectsOutputToContain('Acme Plugin')
        ->expectsOutputToContain('DRY-RUN: would charge now.')
        ->expectsOutputToContain('Summary')
        ->assertSuccessful();

    expect((string) $access->fresh()->last_paid_at)->toStartWith($paidAt->toDateString());
});

it('explains why a filtered payment method access is not due', function (): void {
    $access = PaymentMethodAccess::query()->create([
        'user_id' => User::factory()->create()->id,
        'company_name' => 'Already Paid Plugin',
        'company_email' => 'paid-'.uniqid().'@example.com',
        'key' => (string) Str::uuid(),
        'subscriptionMethod' => 'elavon',
        'paymentMethod' => 'elavon',
        'status' => 1,
        'last_paid_at' => now()->startOfMonth()->addDay(),
    ]);

    $this->artisan('payment-method-access:charge', ['status' => 1, '--dry-run' => true, '--id' => $access->id])
        ->expectsOutputToContain('No payment method accesses matched the due criteria.')
        ->expectsOutputToContain('exists but is not due')
        ->expectsOutputToContain('status: 1')
        ->assertSuccessful();
});

it('treats a never-charged payment method access as due', function (): void {
    $access = PaymentMethodAccess::query()->create([
        'user_id' => User::factory()->create()->id,
        'company_name' => 'Never Charged Plugin',
        'company_email' => 'never-'.uniqid().'@example.com',
        'key' => (string) Str::uuid(),
        'subscriptionMethod' => 'elavon',
        'paymentMethod' => 'elavon',
        'status' => 1,
        'last_paid_at' => null,
    ]);

    $access->subscription()->create([
        'key' => 'stored-card-never',
        'fee' => 149,
        'status' => 1,
        'paid_at' => null,
    ]);

    $this->artisan('payment-method-access:charge', ['status' => 1, '--dry-run' => true, '--id' => $access->id])
        ->expectsOutputToContain('Found 1 payment method access(es) to process.')
        ->expectsOutputToContain('Never Charged Plugin')
        ->expectsOutputToContain('DRY-RUN: would charge now.')
        ->assertSuccessful();
});

it('treats a payment method access overdue by more than one month as due', function (): void {
    $access = PaymentMethodAccess::query()->create([
        'user_id' => User::factory()->create()->id,
        'company_name' => 'Long Overdue Plugin',
        'company_email' => 'overdue-'.uniqid().'@example.com',
        'key' => (string) Str::uuid(),
        'subscriptionMethod' => 'elavon',
        'paymentMethod' => 'elavon',
        'status' => 1,
        'last_paid_at' => now()->subMonths(3),
    ]);

    $access->subscription()->create([
        'key' => 'stored-card-overdue',
        'fee' => 149,
        'status' => 1,
        'paid_at' => now()->subMonths(3),
    ]);

    $this->artisan('payment-method-access:charge', ['status' => 1, '--dry-run' => true, '--id' => $access->id])
        ->expectsOutputToContain('Found 1 payment method access(es) to process.')
        ->expectsOutputToContain('Long Overdue Plugin')
        ->expectsOutputToContain('DRY-RUN: would charge now.')
        ->assertSuccessful();
});

it('deactivates a payment method access with no active subscription', function (): void {
    $paidAt = now()->subMonth()->startOfMonth();

    $access = PaymentMethodAccess::query()->create([
        'user_id' => User::factory()->create()->id,
        'company_name' => 'No Subscription Plugin',
        'company_email' => 'nosub-'.uniqid().'@example.com',
        'key' => (string) Str::uuid(),
        'subscriptionMethod' => 'elavon',
        'paymentMethod' => 'elavon',
        'status' => 1,
        'last_paid_at' => $paidAt,
    ]);

    $this->artisan('payment-method-access:charge', ['status' => 0, '--id' => $access->id])
        ->expectsOutputToContain('No Subscription Plugin')
        ->expectsOutputToContain('SKIP: subscription missing or inactive')
        ->assertSuccessful();

    expect((int) $access->fresh()->status)->toBe(0);
});
