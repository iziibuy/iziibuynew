<?php

declare(strict_types=1);

use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

function createAdminUser(): User
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

it('shows login as user on the filament user view page for admins', function (): void {
    $admin = createAdminUser();
    $target = User::factory()->create([
        'role_id' => User::ROLES['External'],
    ]);

    Livewire::actingAs($admin)
        ->test(ViewUser::class, ['record' => $target->getRouteKey()])
        ->assertSuccessful()
        ->assertActionVisible('loginAsUser');
});

it('lets an admin log in as another user and redirects to their dashboard', function (): void {
    $admin = createAdminUser();
    $target = User::factory()->create([
        'role_id' => User::ROLES['External'],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users.login-as', $target))
        ->assertRedirect(route('external.dashboard'));

    expect(auth()->id())->toBe($target->id)
        ->and(session('impersonator_id'))->toBe($admin->id);
});

it('forbids non-admins from logging in as another user', function (): void {
    $actor = User::factory()->create([
        'role_id' => User::ROLES['External'],
    ]);
    $target = User::factory()->create([
        'role_id' => User::ROLES['Vendor'],
    ]);

    $this->actingAs($actor)
        ->get(route('admin.users.login-as', $target))
        ->assertForbidden();

    expect(auth()->id())->toBe($actor->id);
});

it('forbids an admin from logging in as themselves', function (): void {
    $admin = createAdminUser();

    $this->actingAs($admin)
        ->get(route('admin.users.login-as', $admin))
        ->assertForbidden();
});
