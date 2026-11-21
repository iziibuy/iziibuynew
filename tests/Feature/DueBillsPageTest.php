<?php

declare(strict_types=1);

use App\Filament\Pages\DueBillsPage;
use App\Models\Enterprise;
use App\Models\PaymentMethodAccess;
use App\Models\Shop;
use App\Models\User;
use App\Services\Admin\DueBillCharger;
use App\Services\Admin\DueBillOwnerInspector;
use App\Services\Admin\DueBillsQuery;
use App\Services\Admin\UnifiedDueLedger;
use App\Services\Subscription\MonthlyBillingPeriod;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

function dueBillsAdmin(): User
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

function dueBillsShop(array $attributes = []): Shop
{
    $companyName = $attributes['company_name'] ?? 'Due Shop Co';
    unset($attributes['company_name']);

    $owner = User::factory()->create([
        'name' => 'Shop',
        'last_name' => 'Owner',
        'email' => 'shop-due-'.uniqid().'@example.com',
        'role_id' => User::ROLES['Vendor'],
    ]);

    $shop = Shop::query()->create(array_merge([
        'user_id' => $owner->id,
        'user_name' => 'due-shop-'.uniqid(),
        'subscriptionMethod' => 'elavon',
        'paymentMethod' => 'elavon',
        'subscription_id' => 'card-due-shop',
        'status' => 1,
        'establishment' => 1,
        'monthly_cost' => 299,
        'establishment_cost' => 0,
        'per_user_fee' => 0,
        'paid_at' => now()->subMonths(2)->endOfMonth(),
    ], $attributes));

    $shop->createMetas([
        'company_name' => $companyName,
        'contact_email' => $owner->email,
    ]);

    $owner->update(['shop_id' => $shop->id]);

    return $shop->fresh(['metas', 'user', 'users']);
}

function dueBillsPlugin(array $attributes = []): PaymentMethodAccess
{
    $owner = User::factory()->create([
        'name' => 'Plugin',
        'last_name' => 'Owner',
        'email' => 'plugin-due-'.uniqid().'@example.com',
        'role_id' => User::ROLES['External'],
    ]);

    $access = PaymentMethodAccess::query()->create(array_merge([
        'user_id' => $owner->id,
        'company_name' => 'Due Plugin Co',
        'company_email' => $owner->email,
        'key' => (string) Str::uuid(),
        'fee' => 50,
        'subscriptionMethod' => 'elavon',
        'status' => 1,
        'last_paid_at' => now()->subMonths(2)->endOfMonth(),
    ], $attributes));

    $access->subscription()->create([
        'key' => 'plugin-card-'.uniqid(),
        'fee' => 50,
        'status' => 1,
        'paid_at' => now()->subMonths(2)->endOfMonth(),
    ]);

    return $access->fresh(['subscription', 'user']);
}

function dueBillsEnterprise(array $attributes = []): Enterprise
{
    $enterprise = Enterprise::query()->create(array_merge([
        'enterprise_name' => 'Due Enterprise Co',
        'domain' => 'https://due-enterprise-'.uniqid().'.example',
        'unqid' => (string) Str::uuid(),
        'status' => 1,
        'subscription_id' => 'enterprise-card-'.uniqid(),
        'subscription_fee' => 450,
        'paid_at' => now()->subMonths(2)->endOfMonth(),
    ], $attributes));

    $enterprise->subscription()->create([
        'key' => 'enterprise-sub-'.uniqid(),
        'fee' => 450,
        'status' => 1,
        'paid_at' => now()->subMonths(2)->endOfMonth(),
    ]);

    return $enterprise->fresh(['subscription']);
}

it('lists due shops, plugins, and enterprise subscriptions together', function (): void {
    $admin = dueBillsAdmin();
    dueBillsShop();
    dueBillsPlugin();
    dueBillsEnterprise();

    $rows = app(UnifiedDueLedger::class)->collect();

    expect($rows->pluck('company')->all())
        ->toContain('Due Shop Co')
        ->toContain('Due Plugin Co')
        ->toContain('Due Enterprise Co')
        ->and($rows->pluck('type')->unique()->sort()->values()->all())
        ->toBe([
            UnifiedDueLedger::TYPE_ENTERPRISE,
            UnifiedDueLedger::TYPE_PLUGIN,
            UnifiedDueLedger::TYPE_SHOP,
        ]);

    Livewire::actingAs($admin)
        ->test(DueBillsPage::class)
        ->assertSuccessful()
        ->assertSee('Due bills')
        ->assertSee('Due Shop Co')
        ->assertSee('Due Plugin Co')
        ->assertSee('Due Enterprise Co');
});

it('excludes already-paid and active-trial entities from the due ledger', function (): void {
    $period = MonthlyBillingPeriod::resolve();

    dueBillsShop(['paid_at' => $period->paidThrough()]);
    dueBillsPlugin([
        'last_paid_at' => $period->paidThrough(),
    ]);
    dueBillsPlugin([
        'status' => 1,
        'trial_used' => true,
        'trial_ends_at' => now()->addDays(3),
        'trial_charged_at' => null,
        'last_paid_at' => null,
    ]);

    $rows = app(UnifiedDueLedger::class)->collect($period);

    expect($rows)->toHaveCount(0);
});

it('calculates plugin due amount with vat and shop due from subscriptionFeeFull', function (): void {
    $shop = dueBillsShop(['monthly_cost' => 200, 'establishment' => 1, 'per_user_fee' => 0]);
    $plugin = dueBillsPlugin(['fee' => 100]);

    $rows = app(UnifiedDueLedger::class)->collect()->keyBy('id');

    expect($rows['shop-'.$shop->id]['due_amount'])->toBe((float) $shop->subscriptionFeeFull())
        ->and($rows['plugin-'.$plugin->id]['due_amount'])->toBe(125.0)
        ->and($rows['plugin-'.$plugin->id]['monthly_price'])->toBe(100.0);
});

it('filters due bills by type', function (): void {
    dueBillsShop();
    dueBillsPlugin();
    dueBillsEnterprise();

    $rows = app(DueBillsQuery::class)->rows(
        filters: ['type' => UnifiedDueLedger::TYPE_PLUGIN],
        sortColumn: 'company',
        sortDirection: 'asc',
        search: null,
    );

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['type'])->toBe(UnifiedDueLedger::TYPE_PLUGIN)
        ->and($rows->first()['company'])->toBe('Due Plugin Co');
});

it('searches due bills by company name', function (): void {
    dueBillsShop();
    dueBillsPlugin();

    $rows = app(DueBillsQuery::class)->rows(
        filters: null,
        sortColumn: null,
        sortDirection: null,
        search: 'Plugin Co',
    );

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['company'])->toBe('Due Plugin Co');
});

it('explains when an owner shop is already on the due list', function (): void {
    $shop = dueBillsShop();
    $inspection = app(DueBillOwnerInspector::class)->inspect((int) $shop->user_id);

    expect($inspection['user']['id'])->toBe($shop->user_id)
        ->and($inspection['entities'])->toHaveCount(1)
        ->and($inspection['entities'][0]['on_due_list'])->toBeTrue()
        ->and($inspection['entities'][0]['status'])->toBe(DueBillOwnerInspector::STATUS_DUE)
        ->and($inspection['entities'][0]['due_amount'])->toBe((float) $shop->subscriptionFeeFull());
});

it('explains why an already-paid owner shop is not billed', function (): void {
    $period = MonthlyBillingPeriod::resolve();
    $shop = dueBillsShop(['paid_at' => $period->paidThrough()]);

    $inspection = app(DueBillOwnerInspector::class)->inspect((int) $shop->user_id, $period);
    $entity = $inspection['entities'][0];

    expect($entity['on_due_list'])->toBeFalse()
        ->and($entity['status'])->toBe(DueBillOwnerInspector::STATUS_NOT_DUE)
        ->and(implode(' ', $entity['reasons']))->toContain('Already paid');
});

it('explains why an active-trial plugin is not billed', function (): void {
    $plugin = dueBillsPlugin([
        'trial_used' => true,
        'trial_ends_at' => now()->addDays(3),
        'trial_charged_at' => null,
        'last_paid_at' => null,
    ]);

    $inspection = app(DueBillOwnerInspector::class)->inspect((int) $plugin->user_id);
    $entity = $inspection['entities'][0];

    expect($entity['type'])->toBe(UnifiedDueLedger::TYPE_PLUGIN)
        ->and($entity['on_due_list'])->toBeFalse()
        ->and(implode(' ', $entity['reasons']))->toContain('Free trial is unsettled');
});

it('reports when the owner user id has no billable accounts', function (): void {
    $inspection = app(DueBillOwnerInspector::class)->inspect(999999);

    expect($inspection['user'])->toBeNull()
        ->and($inspection['entities'])->toHaveCount(1)
        ->and($inspection['entities'][0]['status'])->toBe(DueBillOwnerInspector::STATUS_MISSING);
});

it('checks an owner user id from the due bills page', function (): void {
    $admin = dueBillsAdmin();
    $shop = dueBillsShop();

    Livewire::actingAs($admin)
        ->test(DueBillsPage::class)
        ->assertSuccessful()
        ->callAction(TestAction::make('inspectOwner')->table(), [
            'user_id' => $shop->user_id,
        ])
        ->assertSet('ownerInspection.user_id', $shop->user_id)
        ->assertSee('On due list')
        ->assertSee('Due Shop Co')
        ->assertSee('Make unavailable');
});

it('can make an owner entity unavailable from the owner check', function (): void {
    $admin = dueBillsAdmin();
    $period = MonthlyBillingPeriod::openChargeMonth();
    $shop = dueBillsShop(['company_name' => 'Owner Toggle Shop']);

    Livewire::actingAs($admin)
        ->test(DueBillsPage::class)
        ->callAction(TestAction::make('inspectOwner')->table(), [
            'user_id' => $shop->user_id,
        ])
        ->assertSee('Make unavailable')
        ->call('makeOwnerEntityUnavailable', UnifiedDueLedger::TYPE_SHOP, $shop->id)
        ->assertNotified(__('Moved to the unavailable list.'))
        ->assertSee('Make available')
        ->assertSee('Not billed this run');

    expect($shop->fresh()->paid_at->toDateTimeString())
        ->toBe($period->paidThrough()->toDateTimeString());
});

it('can make an owner entity available from the owner check', function (): void {
    $admin = dueBillsAdmin();
    $period = MonthlyBillingPeriod::resolve();
    $shop = dueBillsShop([
        'paid_at' => $period->paidThrough(),
        'company_name' => 'Owner Unlock Shop',
    ]);

    Livewire::actingAs($admin)
        ->test(DueBillsPage::class)
        ->callAction(TestAction::make('inspectOwner')->table(), [
            'user_id' => $shop->user_id,
        ])
        ->assertSee('Make available')
        ->call('makeOwnerEntityAvailable', UnifiedDueLedger::TYPE_SHOP, $shop->id)
        ->assertNotified(__('Moved to the due list.'))
        ->assertSee('Make unavailable')
        ->assertSee('On due list');

    expect($shop->fresh()->paid_at->toDateTimeString())
        ->toBeLessThan($period->paidThrough()->toDateTimeString());
});

it('lists already-paid and inactive accounts on the unavailable scope', function (): void {
    $period = MonthlyBillingPeriod::resolve();
    $paidShop = dueBillsShop([
        'paid_at' => $period->paidThrough(),
        'company_name' => 'Paid Shop Co',
    ]);
    $inactiveShop = dueBillsShop([
        'status' => 0,
        'company_name' => 'Inactive Shop Co',
    ]);
    dueBillsShop(['company_name' => 'Active Due Shop Co']);

    $unavailable = app(UnifiedDueLedger::class)->collect($period, scope: UnifiedDueLedger::SCOPE_UNAVAILABLE);
    $due = app(UnifiedDueLedger::class)->collect($period, scope: UnifiedDueLedger::SCOPE_DUE);

    expect($unavailable->pluck('id')->all())
        ->toContain('shop-'.$paidShop->id)
        ->toContain('shop-'.$inactiveShop->id)
        ->and($due->pluck('id')->all())
        ->not->toContain('shop-'.$paidShop->id)
        ->not->toContain('shop-'.$inactiveShop->id)
        ->and($unavailable->firstWhere('id', 'shop-'.$paidShop->id)['unavailable_reason'])
        ->toContain('Already paid');
});

it('filters by company, name and email', function (): void {
    $shop = dueBillsShop(['company_name' => 'Alpha Retail Co']);
    dueBillsPlugin();

    $byCompany = app(DueBillsQuery::class)->rows(
        filters: ['company' => ['company' => 'Alpha Retail']],
        sortColumn: null,
        sortDirection: null,
        search: null,
    );

    $byName = app(DueBillsQuery::class)->rows(
        filters: ['name' => ['name' => 'Shop Owner']],
        sortColumn: null,
        sortDirection: null,
        search: null,
    );

    $byEmail = app(DueBillsQuery::class)->rows(
        filters: ['email' => ['email' => $shop->contact_email]],
        sortColumn: null,
        sortDirection: null,
        search: null,
    );

    expect($byCompany)->toHaveCount(1)
        ->and($byCompany->first()['company'])->toBe('Alpha Retail Co')
        ->and($byName)->toHaveCount(1)
        ->and($byName->first()['company'])->toBe('Alpha Retail Co')
        ->and($byEmail)->toHaveCount(1)
        ->and($byEmail->first()['id'])->toBe('shop-'.$shop->id);
});

it('includes an owed amount breakdown on each row', function (): void {
    $shop = dueBillsShop(['monthly_cost' => 200, 'establishment' => 0, 'establishment_cost' => 50]);
    $plugin = dueBillsPlugin(['fee' => 100]);

    $rows = app(UnifiedDueLedger::class)->collect()->keyBy('id');

    expect($rows['shop-'.$shop->id]['breakdown']['total'])->toBe((float) $shop->subscriptionFeeFull())
        ->and(collect($rows['shop-'.$shop->id]['breakdown']['lines'])->pluck('label')->all())
        ->toContain('Establishment fee')
        ->toContain('Monthly fee')
        ->and($rows['plugin-'.$plugin->id]['breakdown']['lines'])
        ->toHaveCount(2)
        ->and($rows['plugin-'.$plugin->id]['breakdown']['total'])->toBe(125.0);
});

it('switches the table between due and unavailable tabs', function (): void {
    $admin = dueBillsAdmin();
    $period = MonthlyBillingPeriod::resolve();
    dueBillsShop([
        'paid_at' => $period->paidThrough(),
        'company_name' => 'Paid Shop Co',
    ]);
    dueBillsShop(['company_name' => 'Active Due Shop Co']);

    Livewire::actingAs($admin)
        ->test(DueBillsPage::class)
        ->assertSuccessful()
        ->assertSee('Active Due Shop Co')
        ->assertDontSee('Paid Shop Co')
        ->set('activeTab', UnifiedDueLedger::SCOPE_UNAVAILABLE)
        ->assertSet('activeTab', UnifiedDueLedger::SCOPE_UNAVAILABLE)
        ->assertSee('Paid Shop Co')
        ->assertSee('Already paid');
});

it('charges the open billing month from the due bills page', function (): void {
    $admin = dueBillsAdmin();
    $period = MonthlyBillingPeriod::openChargeMonth();

    $this->mock(DueBillCharger::class, function ($mock) use ($period): void {
        $mock->shouldReceive('charge')
            ->once()
            ->withArgs(function (MonthlyBillingPeriod $billingPeriod): bool {
                return $billingPeriod->start->toDateString() === MonthlyBillingPeriod::openChargeMonth()->start->toDateString();
            })
            ->andReturn([
                'month' => $period->start->format('Y-m'),
                'label' => $period->label(),
                'dry_run' => false,
                'due_count' => 0,
                'exit_code' => 0,
                'output' => '',
            ]);
    });

    Livewire::actingAs($admin)
        ->test(DueBillsPage::class)
        ->assertSee('Charge due accounts')
        ->assertSee($period->start->format('F Y'))
        ->callAction(TestAction::make('chargeDueAccounts')->table())
        ->assertNotified(__('Charge finished for :month', ['month' => $period->start->format('F Y')]));
});
