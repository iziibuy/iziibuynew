<?php

declare(strict_types=1);

use App\Filament\Resources\PaymentMethodAccesses\Pages\EditPaymentMethodAccess;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\PaymentMethodAccess;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

function createFilamentAdmin(): User
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

function createPluginRecord(array $overrides = []): PaymentMethodAccess
{
    $owner = User::factory()->create([
        'role_id' => User::ROLES['External'],
        'service_type' => 'both',
        'pt_free_tier' => false,
    ]);

    return PaymentMethodAccess::query()->create(array_merge([
        'user_id' => $owner->id,
        'company_name' => 'Test Plugin',
        'company_email' => 'plugin-'.uniqid().'@example.com',
        'company_address' => [
            'city' => 'Oslo',
            'street' => 'Main',
            'zip' => '0001',
        ],
        'company_registration' => '123456',
        'company_domain' => 'https://plugin-'.uniqid().'.example.com',
        'key' => (string) Str::uuid(),
        'paymentMethod' => 'elavon',
        'subscriptionMethod' => 'elavon',
        'status' => 0,
    ], $overrides));
}

it('allows a user to keep their own email on edit', function (): void {
    $admin = createFilamentAdmin();

    $user = User::factory()->create([
        'email' => 'keep-email-'.uniqid().'@example.com',
        'service_type' => 'both',
        'pt_free_tier' => false,
    ]);

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $user->getRouteKey()])
        ->set('data.email', $user->email)
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->email)->toBe($user->email);
});

it('rejects duplicate user emails on edit with a validation error', function (): void {
    $admin = createFilamentAdmin();

    $existingEmail = 'existing-'.uniqid().'@example.com';

    User::factory()->create([
        'email' => $existingEmail,
        'service_type' => 'both',
        'pt_free_tier' => false,
    ]);

    $user = User::factory()->create([
        'email' => 'other-'.uniqid().'@example.com',
        'service_type' => 'both',
        'pt_free_tier' => false,
    ]);

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $user->getRouteKey()])
        ->set('data.email', $existingEmail)
        ->call('save')
        ->assertHasErrors(['data.email' => 'unique']);
});

it('rejects duplicate plugin company domains on edit with a validation error', function (): void {
    $admin = createFilamentAdmin();

    $existingDomain = 'https://existing-'.uniqid().'.example.com';
    createPluginRecord(['company_domain' => $existingDomain]);

    $plugin = createPluginRecord([
        'company_domain' => 'https://other-'.uniqid().'.example.com',
    ]);

    Livewire::actingAs($admin)
        ->test(EditPaymentMethodAccess::class, ['record' => $plugin->getRouteKey()])
        ->set('data.company_domain', $existingDomain)
        ->call('save')
        ->assertHasErrors(['data.company_domain' => 'unique']);
});
