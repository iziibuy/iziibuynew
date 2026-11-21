<?php

declare(strict_types=1);

use App\Services\Subscription\MonthlyBillingPeriod;
use Illuminate\Support\Carbon;

it('resolves an explicit billing month and paid through date', function (): void {
    $period = MonthlyBillingPeriod::resolve('2026-10');

    expect($period->label())->toBe('01 Oct 2026 – 31 Oct 2026')
        ->and($period->paidThrough()->toDateTimeString())->toBe('2026-10-31 23:59:59')
        ->and($period->inclusiveDays())->toBe(31.0);
});

it('uses the previous month as the open charge month until the current month ends', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-11-21 12:00:00'));

    try {
        expect(MonthlyBillingPeriod::openChargeMonth()->start->toDateString())->toBe('2026-10-01')
            ->and(MonthlyBillingPeriod::openChargeMonth()->paidThrough()->toDateString())->toBe('2026-10-31');

        Carbon::setTestNow(Carbon::parse('2026-12-01 00:05:00'));

        expect(MonthlyBillingPeriod::openChargeMonth()->start->toDateString())->toBe('2026-11-01');
    } finally {
        Carbon::setTestNow();
    }
});

it('resolves february in a leap year', function (): void {
    $period = MonthlyBillingPeriod::resolve('2024-02');

    expect($period->label())->toBe('01 Feb 2024 – 29 Feb 2024')
        ->and($period->inclusiveDays())->toBe(29.0);
});
