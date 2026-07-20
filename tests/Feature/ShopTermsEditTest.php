<?php

declare(strict_types=1);

use App\Filament\Resources\Shops\ShopResource;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

function createShopForTermsTest(string $terms): Shop
{
    $user = User::factory()->create([
        'role_id' => User::ROLES['Admin'],
    ]);

    $shop = Shop::query()->create([
        'user_id' => $user->id,
        'user_name' => 'terms-test-'.uniqid(),
        'subscription_id' => 'sub-'.uniqid(),
        'subscriptionMethod' => 'quickpay',
        'paymentMethod' => 'quickpay',
        'terms' => $terms,
    ]);

    return $shop->refresh();
}

it('resolves legacy plain html shop terms', function (): void {
    $shop = createShopForTermsTest('<p>Legacy terms content</p>');

    expect($shop->terms)->toContain('Legacy terms content');
});

it('resolves shop terms stored under a non-default locale', function (): void {
    $shop = createShopForTermsTest(json_encode([
        'no' => '<p>Norwegian terms</p>',
    ], JSON_UNESCAPED_UNICODE));

    expect($shop->terms)->toContain('Norwegian terms');
});

it('allows admin to open shop edit page when terms use legacy html', function (): void {
    $shop = createShopForTermsTest('<p>Legacy terms for edit page</p>');

    $admin = User::factory()->create([
        'role_id' => User::ROLES['Admin'],
        'email' => 'shop-terms-edit@test.com',
        'password' => bcrypt('password'),
    ]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(ShopResource::getUrl('edit', ['record' => $shop]))
        ->assertOk();
});

it('allows admin to open shop edit page when terms are stored as legacy plain html in the database', function (): void {
    $shop = createShopForTermsTest('');

    DB::table('shops')
        ->where('id', $shop->id)
        ->update(['terms' => '<p>Raw legacy terms content</p>']);

    $shop->refresh();

    expect($shop->terms)->toContain('Raw legacy terms content');

    $admin = User::factory()->create([
        'role_id' => User::ROLES['Admin'],
        'email' => 'shop-legacy-terms-edit@test.com',
        'password' => bcrypt('password'),
    ]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(ShopResource::getUrl('edit', ['record' => $shop]))
        ->assertOk();
});
