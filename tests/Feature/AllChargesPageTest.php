<?php

declare(strict_types=1);

use App\Filament\Pages\AllChargesPage;
use App\Models\Charge;
use App\Models\Enterprise;
use App\Models\ExternalSubscription;
use App\Models\ExternalSubscriptionCharge;
use App\Models\PaymentApi;
use App\Models\PaymentMethodAccess;
use App\Models\Shop;
use App\Models\SubscriptionCharge;
use App\Models\User;
use App\Services\Admin\ChargeBreakdown;
use App\Services\Admin\ChargeCardLookup;
use App\Services\Admin\ChargeCsvExporter;
use App\Services\Admin\UnifiedChargeLedger;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

beforeEach(function (): void {
    config(['cache.default' => 'array']);

    $this->seed(RoleSeeder::class);
});

function createAllChargesAdmin(): User
{
    $admin = User::factory()->create([
        'role_id' => User::ROLES['Admin'],
        'password' => bcrypt('password'),
        'service_type' => 'both',
        'pt_free_tier' => false,
    ]);
    $admin->assignRole('admin');

    return $admin;
}

function createAllChargesShopFixture(): Charge
{
    $owner = User::factory()->create();

    $shop = Shop::query()->create([
        'user_id' => $owner->id,
        'user_name' => 'shop-all-charges-'.uniqid(),
        'subscriptionMethod' => 'elavon',
        'paymentMethod' => 'elavon',
        'status' => 1,
        'establishment' => 0,
        'monthly_cost' => 299,
        'establishment_cost' => 0,
        'per_user_fee' => 0,
    ]);

    if (method_exists($shop, 'createMetas')) {
        $shop->createMetas([
            'company_name' => 'All Charges Shop',
            'contact_email' => 'shop-all-charges@example.com',
        ]);
    }

    return Charge::query()->create([
        'shop_id' => $shop->id,
        'order_id' => 'order-all-charges-1',
        'amount' => 199,
        'details' => json_encode(['shop' => ['id' => $shop->id]]),
        'comment' => 'Monthly subscription fee',
        'status' => 1,
        'payment_type' => 'Real',
        'created_at' => now()->subDays(2),
    ]);
}

function createAllChargesSubscriptionFixture(): SubscriptionCharge
{
    $enterprise = Enterprise::query()->create([
        'enterprise_name' => 'All Charges Enterprise',
        'domain' => 'https://enterprise-all-charges.example',
        'unqid' => (string) Str::uuid(),
        'status' => 1,
    ]);

    $subscription = $enterprise->subscription()->create([
        'key' => 'sub-all-charges-1',
        'fee' => 450,
        'status' => 1,
    ]);

    return SubscriptionCharge::query()->create([
        'subscription_id' => $subscription->id,
        'amount' => 450,
        'status' => 1,
        'quickpay_order_id' => 'qp-all-charges-1',
        'created_at' => now()->subDay(),
    ]);
}

function createAllChargesExternalFixture(): ExternalSubscriptionCharge
{
    $owner = User::factory()->create([
        'role_id' => User::ROLES['External'],
    ]);

    $access = PaymentMethodAccess::query()->create([
        'user_id' => $owner->id,
        'company_name' => 'All Charges Plugin',
        'company_email' => 'plugin-'.uniqid().'@example.com',
        'key' => (string) Str::uuid(),
        'paymentMethod' => 'elavon',
        'status' => 1,
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
        'customer_name' => 'Button Customer',
        'customer_email' => 'button@example.com',
        'amount' => 99,
        'currency' => 'NOK',
        'interval_days' => 30,
        'status' => 'ACTIVE',
        'payment_method' => 'elavon',
    ]);

    return ExternalSubscriptionCharge::query()->create([
        'external_subscription_id' => $subscription->id,
        'amount' => 99,
        'currency' => 'NOK',
        'status' => true,
        'type' => 'renewal',
        'elavon_transaction_id' => 'elv-all-charges-1',
        'created_at' => now(),
    ]);
}

it('lists shop, subscription, and button charges together on the all charges page', function (): void {
    $admin = createAllChargesAdmin();
    createAllChargesShopFixture();
    createAllChargesSubscriptionFixture();
    createAllChargesExternalFixture();

    $component = Livewire::actingAs($admin)
        ->test(AllChargesPage::class)
        ->assertSuccessful();

    $tableRecords = $component->instance()->getTableRecords();
    $records = collect(method_exists($tableRecords, 'items') ? $tableRecords->items() : $tableRecords);

    expect($records->pluck('reference')->all())
        ->toContain('order-all-charges-1')
        ->toContain('qp-all-charges-1')
        ->toContain('elv-all-charges-1')
        ->and($records->pluck('model_label')->all())->toContain('All Charges Enterprise')
        ->and($records->pluck('entity_name')->all())->toContain('Button Customer');
});

it('breaks down a shop charge from its saved fee snapshot', function (): void {
    $charge = createAllChargesShopFixture();
    $charge->update([
        'amount' => 2216.26,
        'details' => json_encode([
            'charged_at' => '10 Jul,2026',
            'last_day_of_month' => '31 Jul,2026',
            'days_before_the_months_end' => 22.52,
            'shop' => ['fees' => ['per_manager_cost' => 200, 'monthly_cost' => 299, 'esatblisment_cost' => 1999]],
            'need_to_pay' => ['managers_cost' => 0, 'monthly_cost' => 217.26, 'esatblisment_cost' => 1999, 'tax' => 0],
            'discount' => 0,
            'total' => 2216.26,
        ]),
    ]);

    $breakdown = app(ChargeBreakdown::class)->forRecord([
        'id' => UnifiedChargeLedger::SOURCE_SHOP.'-'.$charge->id,
        'amount' => 2216.26,
    ]);

    expect($breakdown['method'])->toBe(ChargeBreakdown::METHOD_SNAPSHOT)
        ->and(array_column($breakdown['lines'], 'amount'))->toBe([1999.0, 217.26, 0.0])
        ->and($breakdown['lines'][1]['note'])->toContain('299.00 × 22.52 of 31 days')
        ->and($breakdown['period'])->toBe('10 Jul 2026 – 31 Jul 2026 (22 days)')
        ->and($breakdown['total'])->toBe(2216.26)
        ->and($breakdown['notes'])->toBeEmpty();
});

it('shows the unexplained difference when the snapshot does not match the charged amount', function (): void {
    $charge = createAllChargesShopFixture();
    $charge->update([
        'amount' => 623.75,
        'details' => json_encode([
            'charged_at' => '01 Aug,2026',
            'last_day_of_month' => '31 Aug,2026',
            'days_before_the_months_end' => 31,
            'shop' => ['fees' => ['monthly_cost' => 299]],
            'need_to_pay' => ['managers_cost' => 0, 'monthly_cost' => 299, 'esatblisment_cost' => 0, 'tax' => 74.75],
            'discount' => 0,
        ]),
    ]);

    $breakdown = app(ChargeBreakdown::class)->forRecord([
        'id' => UnifiedChargeLedger::SOURCE_SHOP.'-'.$charge->id,
        'amount' => 623.75,
    ]);

    expect(end($breakdown['lines'])['amount'])->toBe(250.0)
        ->and($breakdown['total'])->toBe(623.75)
        ->and($breakdown['notes'])->not->toBeEmpty();
});

it('breaks down a plugin charge into the pro-rated fee and 25 percent VAT', function (): void {
    $owner = User::factory()->create(['role_id' => User::ROLES['External']]);
    $plugin = PaymentMethodAccess::query()->create([
        'user_id' => $owner->id,
        'company_name' => 'Breakdown Plugin',
        'company_email' => 'breakdown-'.uniqid().'@example.com',
        'key' => (string) Str::uuid(),
        'paymentMethod' => 'elavon',
        'status' => 1,
        'fee' => 199,
    ]);
    $subscription = $plugin->subscription()->create(['key' => 'sub-breakdown-plugin', 'fee' => 199, 'status' => 1]);
    $charge = SubscriptionCharge::query()->create([
        'subscription_id' => $subscription->id,
        'amount' => 150.50,
        'status' => 1,
        'created_at' => '2026-10-14 09:51:40',
    ]);

    $breakdown = app(ChargeBreakdown::class)->forRecord([
        'id' => UnifiedChargeLedger::SOURCE_SUBSCRIPTION.'-'.$charge->id,
        'amount' => 150.50,
    ]);

    expect(array_column($breakdown['lines'], 'amount'))->toBe([120.4, 30.1])
        ->and($breakdown['lines'][0]['note'])->toBe('199.00 × 18.76 of 31 days')
        ->and($breakdown['period'])->toBe('14 Oct 2026 – 31 Oct 2026 (18 days)')
        ->and($breakdown['total'])->toBe(150.5);
});

it('flags a pro-rated monthly plugin renewal as under-charged for the full month', function (): void {
    $owner = User::factory()->create(['role_id' => User::ROLES['External']]);
    $plugin = PaymentMethodAccess::query()->create([
        'user_id' => $owner->id,
        'company_name' => 'Renewal Plugin',
        'company_email' => 'renewal-'.uniqid().'@example.com',
        'key' => (string) Str::uuid(),
        'paymentMethod' => 'elavon',
        'status' => 1,
        'fee' => 199,
    ]);
    $subscription = $plugin->subscription()->create(['key' => 'sub-renewal-plugin', 'fee' => 199, 'status' => 1]);

    $proratedCharge = SubscriptionCharge::query()->create([
        'subscription_id' => $subscription->id,
        'amount' => 150.50,
        'status' => 1,
        'payment_details' => json_encode(['type' => 'monthly']),
        'created_at' => '2026-10-14 09:51:40',
    ]);
    $fullCharge = SubscriptionCharge::query()->create([
        'subscription_id' => $subscription->id,
        'amount' => 248.75,
        'status' => 1,
        'payment_details' => json_encode(['type' => 'monthly']),
        'created_at' => '2026-11-01 05:11:02',
    ]);

    $prorated = app(ChargeBreakdown::class)->forRecord([
        'id' => UnifiedChargeLedger::SOURCE_SUBSCRIPTION.'-'.$proratedCharge->id,
        'amount' => 150.50,
    ]);
    $full = app(ChargeBreakdown::class)->forRecord([
        'id' => UnifiedChargeLedger::SOURCE_SUBSCRIPTION.'-'.$fullCharge->id,
        'amount' => 248.75,
    ]);

    expect($prorated['period'])->toBe('01 Oct 2026 – 31 Oct 2026 (31 days)')
        ->and($prorated['notes'][0])->toContain('under-charged by 98.25')
        ->and($full['period'])->toBe('01 Nov 2026 – 30 Nov 2026 (30 days)')
        ->and($full['lines'][0]['label'])->toBe('Plugin fee (full month)')
        ->and(array_column($full['lines'], 'amount'))->toBe([199.0, 49.75])
        ->and($full['notes'])->toBeEmpty();
});

it('shows enterprise subscription charges as charged', function (): void {
    $charge = createAllChargesSubscriptionFixture();

    $breakdown = app(ChargeBreakdown::class)->forRecord([
        'id' => UnifiedChargeLedger::SOURCE_SUBSCRIPTION.'-'.$charge->id,
        'amount' => 450,
    ]);

    expect($breakdown['method'])->toBe(ChargeBreakdown::METHOD_AS_CHARGED)
        ->and($breakdown['lines'])->toHaveCount(1)
        ->and($breakdown['total'])->toBe(450.0);
});

it('opens the calculation breakdown from the row icon', function (): void {
    $admin = createAllChargesAdmin();
    $charge = createAllChargesShopFixture();

    $this->mock(ChargeCardLookup::class, function ($mock): void {
        $mock->shouldReceive('limitRemoteLookups')->andReturnSelf();
        $mock->shouldReceive('lastFour')->andReturn('4242');
    });

    Livewire::actingAs($admin)
        ->test(AllChargesPage::class)
        ->mountAction(TestAction::make('breakdown')->table(UnifiedChargeLedger::SOURCE_SHOP.'-'.$charge->id))
        ->assertMountedActionModalSee(['Billing period', 'Monthly fee', 'Total charged', '199.00 NOK', '•••• 4242']);
});

it('shows the card last four digits in the table', function (): void {
    $admin = createAllChargesAdmin();
    $charge = createAllChargesSubscriptionFixture();
    $charge->update(['charge_details' => json_encode(['provider' => 'elavon', 'last4' => '1234'])]);

    Livewire::actingAs($admin)
        ->test(AllChargesPage::class)
        ->call('loadTable')
        ->assertTableColumnExists('card_last_four')
        ->assertSee('•••• 1234');
});

it('stops calling Elavon once the per-render lookup limit is used up', function (): void {
    $charge = createAllChargesSubscriptionFixture();
    $charge->update(['elavon_transaction_id' => 't2fhfm8wg9tprcjfrm2tg83dt3t9', 'charge_details' => null]);

    $lookup = app(ChargeCardLookup::class)->limitRemoteLookups(0);

    expect($lookup->lastFour(['id' => UnifiedChargeLedger::SOURCE_SUBSCRIPTION.'-'.$charge->id]))->toBeNull()
        ->and(Cache::has('charge-card-last4:'.UnifiedChargeLedger::SOURCE_SUBSCRIPTION.'-'.$charge->id))->toBeFalse();
});

it('reads card digits stored on the charge', function (): void {
    $shopCharge = createAllChargesShopFixture();
    $shopCharge->update(['payment_body' => json_encode([['metadata' => ['last4' => '8863']]])]);

    $subscriptionCharge = createAllChargesSubscriptionFixture();
    $subscriptionCharge->update(['charge_details' => json_encode(['provider' => 'elavon', 'last4' => '1234'])]);

    $lookup = app(ChargeCardLookup::class);

    expect($lookup->lastFour(['id' => UnifiedChargeLedger::SOURCE_SHOP.'-'.$shopCharge->id]))->toBe('8863')
        ->and($lookup->lastFour(['id' => UnifiedChargeLedger::SOURCE_SUBSCRIPTION.'-'.$subscriptionCharge->id]))->toBe('1234');
});

it('uses the cached Elavon card digits for subscription charges without stored digits', function (): void {
    $charge = createAllChargesSubscriptionFixture();
    $charge->update(['elavon_transaction_id' => 't2fhfm8wg9tprcjfrm2tg83dt3t9', 'charge_details' => null]);
    Cache::forever('charge-card-last4:'.UnifiedChargeLedger::SOURCE_SUBSCRIPTION.'-'.$charge->id, '5678');

    expect(app(ChargeCardLookup::class)->lastFour(['id' => UnifiedChargeLedger::SOURCE_SUBSCRIPTION.'-'.$charge->id]))
        ->toBe('5678')
        ->and(json_decode((string) $charge->fresh()->getAttributes()['charge_details'], true))
        ->toMatchArray(['last4' => '5678']);
});

it('returns no card digits when nothing is stored and the reference is not an Elavon transaction', function (): void {
    $charge = createAllChargesShopFixture();

    expect(app(ChargeCardLookup::class)->lastFour(['id' => UnifiedChargeLedger::SOURCE_SHOP.'-'.$charge->id]))->toBeNull();
});

it('exports charges to csv with breakdown, period, and card digits', function (): void {
    $shopCharge = createAllChargesShopFixture();
    $shopCharge->update(['payment_body' => json_encode([['metadata' => ['last4' => '8863']]])]);
    createAllChargesSubscriptionFixture();

    $rows = app(UnifiedChargeLedger::class)->collect();
    $csv = TestResponse::fromBaseResponse(app(ChargeCsvExporter::class)->download($rows))->streamedContent();
    $lines = array_map('str_getcsv', explode("\n", trim(ltrim($csv, "\xEF\xBB\xBF"))));

    expect($lines[0])->toBe(app(ChargeCsvExporter::class)->headers())
        ->and($lines)->toHaveCount(3);

    $shopLine = collect($lines)->first(fn (array $line): bool => $line[4] === 'order-all-charges-1');

    expect($shopLine[5])->toBe('199.00')
        ->and($shopLine[8])->toBe('**** 8863')
        ->and($shopLine[10])->toContain('Monthly fee: 299.00');
});

it('neutralises spreadsheet formulas in exported values', function (): void {
    $charge = createAllChargesShopFixture();
    $charge->update(['comment' => '=HYPERLINK("http://evil.test")']);

    $row = app(UnifiedChargeLedger::class)->find(UnifiedChargeLedger::SOURCE_SHOP.'-'.$charge->id);

    expect(app(ChargeCsvExporter::class)->toCsvRow($row)[11])->toStartWith("'=");
});

it('downloads the filtered charges from the export button', function (): void {
    $this->travelTo(Carbon::parse('2026-10-14 10:00:00'));
    $admin = createAllChargesAdmin();
    createAllChargesShopFixture();
    createAllChargesExternalFixture();

    Livewire::actingAs($admin)
        ->test(AllChargesPage::class)
        ->filterTable('model', 'Shop')
        ->callAction(TestAction::make('exportCsv')->table())
        ->assertFileDownloaded('all-charges-2026-10-14-100000.csv');
});

it('filters unified charges by model', function (): void {
    createAllChargesShopFixture();
    createAllChargesSubscriptionFixture();
    createAllChargesExternalFixture();

    $rows = app(UnifiedChargeLedger::class)->collect([
        'model' => ['value' => 'Shop'],
    ]);

    expect($rows)->not->toBeEmpty()
        ->and($rows->every(fn (array $row): bool => $row['model_type'] === 'Shop'))->toBeTrue();
});

it('filters unified charges by date range', function (): void {
    createAllChargesShopFixture();
    createAllChargesExternalFixture();

    $rows = app(UnifiedChargeLedger::class)->collect([
        'date' => [
            'from' => now()->toDateString(),
            'until' => now()->toDateString(),
        ],
    ]);

    expect($rows)->not->toBeEmpty()
        ->and($rows->pluck('reference')->all())->toContain('elv-all-charges-1')
        ->and($rows->pluck('reference')->all())->not->toContain('order-all-charges-1');
});
