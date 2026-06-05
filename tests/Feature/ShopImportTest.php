<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

it('shows shop import panel on filament shops index', function (): void {
    $user = User::factory()->create([
        'role_id' => User::ROLES['Admin'],
        'email' => 'shop-import-panel@test.com',
        'password' => bcrypt('password'),
    ]);
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get('/panel/shops')
        ->assertOk()
        ->assertSee('Import shops')
        ->assertSee('Demo Excel');
});

it('requires a sheet when importing shops', function (): void {
    $user = User::factory()->create([
        'role_id' => User::ROLES['Admin'],
        'email' => 'shop-import-validate@test.com',
        'password' => bcrypt('password'),
    ]);
    $user->assignRole('admin');

    $this->actingAs($user)
        ->post(route('admin.shops.import'), [])
        ->assertSessionHasErrors('sheet');
});
