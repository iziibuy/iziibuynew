<?php

declare(strict_types=1);

use App\Filament\Pages\DueBillsPage;
use App\Models\PaymentMethodAccess;
use App\Models\Shop;
use App\Models\User;
use App\Services\Admin\DueBillMakeAvailable;
use App\Services\Admin\UnifiedDueLedger;
use App\Services\Subscription\MonthlyBillingPeriod;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

function makeAvailableAdmin(): User
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

function makeAvailableShop(array $attributes = []): Shop
{
    $companyName = $attributes['company_name'] ?? 'Make Available Shop';
    unset($attributes['company_name']);

    $owner = User::factory()->create([
        'name' => 'Shop',
        'last_name' => 'Owner',
        'email' => 'shop-available-'.uniqid().'@example.com',
        'role_id' => User::ROLES['Vendor'],
    ]);

    $shop = Shop::query()->create(array_merge([
        'user_id' => $owner->id,
        'user_name' => 'avail-shop-'.uniqid(),
        'subscriptionMethod' => 'elavon',
        'paymentMethod' => 'elavon',
        'subscription_id' => 'card-avail-shop-'.uniqid(),
        'status' => 1,
        'establishment' => 1,
        'monthly_cost' => 299,
        'establishment_cost' => 0,
        'per_user_fee' => 0,
        'paid_at' => now()->subMonth()->endOfMonth(),
    ], $attributes));

    $shop->createMetas([
        'company_name' => $companyName,
        'contact_email' => $owner->email,
    ]);

    $owner->update(['shop_id' => $shop->id]);

    return $shop->fresh(['metas', 'user', 'users']);
}

function makeAvailablePlugin(array $attributes = []): PaymentMethodAccess
{
    $owner = User::factory()->create([
        'name' => 'Plugin',
        'last_name' => 'Owner',
        'email' => 'plugin-available-'.uniqid().'@example.com',
        'role_id' => User::ROLES['External'],
    ]);

    $access = PaymentMethodAccess::query()->create(array_merge([
        'user_id' => $owner->id,
        'company_name' => 'Make Available Plugin',
        'company_email' => $owner->email,
        'key' => (string) Str::uuid(),
        'fee' => 50,
        'subscriptionMethod' => 'elavon',
        'status' => 1,
        'last_paid_at' => now()->subMonth()->endOfMonth(),
    ], $attributes));

    $access->subscription()->create([
        'key' => 'plugin-card-'.uniqid(),
        'fee' => 50,
        'status' => 1,
        'paid_at' => now()->subMonth()->endOfMonth(),
    ]);

    return $access->fresh(['subscription', 'user']);
}

it('moves an already-paid shop onto the due list', function (): void {
    $period = MonthlyBillingPeriod::resolve();
    $shop = makeAvailableShop(['paid_at' => $period->paidThrough()]);

    $result = app(DueBillMakeAvailable::class)->makeAvailable(
        UnifiedDueLedger::TYPE_SHOP,
        $shop->id,
        $period,
    );

    expect($result['success'])->toBeTrue()
        ->and($result['on_due_list'])->toBeTrue()
        ->and($shop->fresh()->paid_at->toDateTimeString())
        ->toBeLessThan($period->paidThrough()->toDateTimeString());
});

it('activates an inactive shop and clears paid marker together', function (): void {
    $period = MonthlyBillingPeriod::resolve();
    $shop = makeAvailableShop([
        'status' => 0,
        'paid_at' => $period->paidThrough(),
    ]);

    $result = app(DueBillMakeAvailable::class)->makeAvailable(
        UnifiedDueLedger::TYPE_SHOP,
        $shop->id,
        $period,
    );

    $shop->refresh();

    expect($result['on_due_list'])->toBeTrue()
        ->and((int) $shop->getAttributes()['status'])->toBe(1)
        ->and($shop->paid_at->toDateTimeString())
        ->toBeLessThan($period->paidThrough()->toDateTimeString());
});

it('settles a free-trial plugin so it becomes due', function (): void {
    $period = MonthlyBillingPeriod::resolve();
    $plugin = makeAvailablePlugin([
        'trial_used' => true,
        'trial_ends_at' => now()->addDays(3),
        'trial_charged_at' => null,
        'last_paid_at' => null,
    ]);

    $result = app(DueBillMakeAvailable::class)->makeAvailable(
        UnifiedDueLedger::TYPE_PLUGIN,
        $plugin->id,
        $period,
    );

    expect($result['on_due_list'])->toBeTrue()
        ->and($plugin->fresh()->trial_charged_at)->not->toBeNull();
});

it('makes an unavailable row available from the due bills page', function (): void {
    $admin = makeAvailableAdmin();
    $period = MonthlyBillingPeriod::resolve();
    $shop = makeAvailableShop([
        'paid_at' => $period->paidThrough(),
        'company_name' => 'Unlockable Shop Co',
    ]);

    Livewire::actingAs($admin)
        ->test(DueBillsPage::class)
        ->set('activeTab', UnifiedDueLedger::SCOPE_UNAVAILABLE)
        ->assertSee('Unlockable Shop Co')
        ->callAction(TestAction::make('makeAvailable')->table('shop-'.$shop->id))
        ->assertNotified(__('Moved to the due list.'));

    expect($shop->fresh()->paid_at->toDateTimeString())
        ->toBeLessThan($period->paidThrough()->toDateTimeString());
});
