<?php

declare(strict_types=1);

use App\Models\Charge;
use App\Models\Shop;
use App\Models\User;
use App\Services\Admin\ChargeBreakdown;
use App\Services\Admin\UnifiedChargeLedger;
use App\Services\Subscription\MonthlyBillingPeriod;
use Illuminate\Support\Carbon;

it('shows the saved billing period on shop charge breakdown instead of created_at month', function (): void {
    $owner = User::factory()->create();

    $shop = Shop::query()->create([
        'user_id' => $owner->id,
        'user_name' => 'dykkerhuset-test',
        'subscriptionMethod' => 'elavon',
        'paymentMethod' => 'elavon',
        'status' => 1,
        'establishment' => 1,
        'monthly_cost' => 599,
        'per_user_fee' => 0,
        'paid_at' => MonthlyBillingPeriod::resolve('2026-09')->paidThrough(),
    ]);

    $billingPeriod = MonthlyBillingPeriod::resolve('2026-09');

    $charge = Charge::query()->create([
        'shop_id' => $shop->id,
        'order_id' => 'shop-sept-renewal-'.uniqid(),
        'amount' => 748.75,
        'status' => 1,
        'comment' => 'Monthly subscription fee',
        'details' => json_encode($shop->subscriptionRenewalFeeDetails($billingPeriod)),
        'created_at' => Carbon::parse('2026-10-14 05:51:40'),
    ]);

    $breakdown = app(ChargeBreakdown::class)->forRecord([
        'id' => UnifiedChargeLedger::SOURCE_SHOP.'-'.$charge->id,
        'amount' => 748.75,
    ]);

    expect($breakdown['period'])->toBe('01 Sep 2026 – 30 Sep 2026 (30 days)');
});

it('falls back to shop paid_at month when charge details have no billing period', function (): void {
    $owner = User::factory()->create();

    $shop = Shop::query()->create([
        'user_id' => $owner->id,
        'user_name' => 'legacy-paid-through-test',
        'subscriptionMethod' => 'elavon',
        'paymentMethod' => 'elavon',
        'status' => 1,
        'establishment' => 1,
        'monthly_cost' => 599,
        'per_user_fee' => 0,
        'paid_at' => MonthlyBillingPeriod::resolve('2026-09')->paidThrough(),
    ]);

    $charge = Charge::query()->create([
        'shop_id' => $shop->id,
        'order_id' => 'shop-legacy-'.uniqid(),
        'amount' => 748.75,
        'status' => 1,
        'comment' => 'Monthly subscription fee',
        'details' => json_encode(['shop' => ['id' => $shop->id]]),
        'created_at' => Carbon::parse('2026-10-14 05:51:40'),
    ]);

    $breakdown = app(ChargeBreakdown::class)->forRecord([
        'id' => UnifiedChargeLedger::SOURCE_SHOP.'-'.$charge->id,
        'amount' => 748.75,
    ]);

    expect($breakdown['period'])->toBe('01 Sep 2026 – 30 Sep 2026 (30 days)');
});
