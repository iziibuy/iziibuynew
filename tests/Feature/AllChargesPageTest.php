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
use App\Services\Admin\UnifiedChargeLedger;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
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
