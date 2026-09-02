<?php

declare(strict_types=1);

use App\Filament\Resources\Charges\Pages\ManageCharges;
use App\Filament\Resources\Charges\Pages\ViewCharge;
use App\Models\Charge;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

function createChargeAdmin(): User
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

function createChargeFixture(): Charge
{
    $owner = User::factory()->create();

    $shop = Shop::query()->create([
        'user_id' => $owner->id,
        'user_name' => 'shop-charge-view-'.uniqid(),
        'subscriptionMethod' => 'elavon',
        'paymentMethod' => 'elavon',
        'status' => 1,
        'establishment' => 0,
        'monthly_cost' => 299,
        'establishment_cost' => 0,
        'per_user_fee' => 0,
    ]);

    return Charge::query()->create([
        'shop_id' => $shop->id,
        'order_id' => 'order-charge-view-1',
        'amount' => 299,
        'details' => json_encode(['shop' => ['id' => $shop->id, 'name' => $shop->user_name]]),
        'comment' => 'Monthly subscription fee',
        'status' => 1,
        'payment_type' => 'Real',
    ]);
}

it('lists charges in filament', function (): void {
    $admin = createChargeAdmin();
    $charge = createChargeFixture();

    Livewire::actingAs($admin)
        ->test(ManageCharges::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$charge]);
});

it('shows charge details with shop relation on the view page', function (): void {
    $admin = createChargeAdmin();
    $charge = createChargeFixture();

    Livewire::actingAs($admin)
        ->test(ViewCharge::class, ['record' => $charge->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('order-charge-view-1')
        ->assertSee($charge->shop->user_name);
});
