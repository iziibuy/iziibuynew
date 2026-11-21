<?php

declare(strict_types=1);

use App\Models\Shop;
use App\Models\User;
use App\Services\Admin\DueBillsQuery;
use App\Services\Subscription\MonthlyBillingPeriod;
use Illuminate\Support\Carbon;

function chargeRunShop(array $attributes = []): Shop
{
    $companyName = $attributes['company_name'] ?? 'Charge Run Shop';
    unset($attributes['company_name']);

    $owner = User::factory()->create([
        'role_id' => User::ROLES['Vendor'],
    ]);

    $shop = Shop::query()->create(array_merge([
        'user_id' => $owner->id,
        'user_name' => 'charge-shop-'.uniqid(),
        'subscriptionMethod' => 'elavon',
        'paymentMethod' => 'elavon',
        'subscription_id' => 'card-charge-shop',
        'status' => 1,
        'establishment' => 1,
        'monthly_cost' => 299,
        'establishment_cost' => 0,
        'per_user_fee' => 0,
        'paid_at' => Carbon::parse('2026-09-30 23:59:59'),
    ], $attributes));

    $shop->createMetas([
        'company_name' => $companyName,
        'contact_email' => $owner->email,
    ]);

    return $shop->fresh(['metas']);
}

it('skips accounts already paid for the charge month and lists the rest', function (): void {
    $this->travelTo(Carbon::parse('2026-11-21 12:00:00'));

    $due = chargeRunShop([
        'user_name' => 'due-october-shop',
        'paid_at' => Carbon::parse('2026-09-30 23:59:59'),
        'company_name' => 'October Due Co',
    ]);
    $paid = chargeRunShop([
        'user_name' => 'paid-october-shop',
        'paid_at' => MonthlyBillingPeriod::resolve('2026-10')->paidThrough(),
        'company_name' => 'October Paid Co',
    ]);

    $rows = app(DueBillsQuery::class)->rows(null, null, null, null);

    expect($rows->pluck('company')->all())
        ->toContain('October Due Co')
        ->not->toContain('October Paid Co');

    $this->artisan('due-bills:charge', ['--dry-run' => true])
        ->expectsOutputToContain('01 Oct 2026 – 31 Oct 2026')
        ->expectsOutputToContain('October Due Co')
        ->expectsOutputToContain('DRY-RUN: would charge now.')
        ->expectsOutputToContain('They show again for November 2026 after November 2026 ends')
        ->doesntExpectOutputToContain('October Paid Co')
        ->assertSuccessful();

    expect($due->fresh()->paid_at->toDateTimeString())->toBe('2026-09-30 23:59:59')
        ->and($paid->fresh()->paid_at->toDateTimeString())->toBe('2026-10-31 23:59:59');
});

it('shows an october-paid account again after november ends', function (): void {
    $this->travelTo(Carbon::parse('2026-11-21 12:00:00'));

    chargeRunShop([
        'user_name' => 'returns-after-november',
        'paid_at' => MonthlyBillingPeriod::resolve('2026-10')->paidThrough(),
        'company_name' => 'Returns After November Co',
    ]);

    expect(app(DueBillsQuery::class)->rows(null, null, null, null)->pluck('company')->all())
        ->not->toContain('Returns After November Co');

    $this->travelTo(Carbon::parse('2026-12-01 00:10:00'));

    expect(app(DueBillsQuery::class)->rows(null, null, null, null)->pluck('company')->all())
        ->toContain('Returns After November Co');

    $this->artisan('due-bills:charge', ['--month' => '2026-10', '--dry-run' => true])
        ->expectsOutputToContain('01 Oct 2026 – 31 Oct 2026')
        ->expectsOutputToContain('No shops matched the due criteria.')
        ->doesntExpectOutputToContain('Returns After November Co')
        ->assertSuccessful();
});

it('rejects a charge month that is not YYYY-MM', function (): void {
    $this->artisan('due-bills:charge', ['--month' => 'October', '--dry-run' => true])
        ->expectsOutputToContain('YYYY-MM')
        ->assertFailed();
});
