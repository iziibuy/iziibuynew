<?php

declare(strict_types=1);

use App\Filament\Resources\PaymentMethodAccesses\Pages\EditPaymentMethodAccess;
use App\Models\PaymentMethodAccess;
use App\Models\User;
use App\Services\Elavon\PlatformElavonCredentials;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

it('stores is_demo on payment method accesses', function (): void {
    expect(Schema::hasColumn('payment_method_accesses', 'is_demo'))->toBeTrue();
});

it('uses production platform elavon credentials for live plugins', function (): void {
    config([
        'services.enterprise_elavon.credentials.production' => [
            'merchant_alias' => 'prod-alias',
            'public_key' => 'prod-public',
            'secret_key' => 'prod-secret',
        ],
        'services.enterprise_elavon.credentials.sandbox' => [
            'merchant_alias' => 'sandbox-alias',
            'public_key' => 'sandbox-public',
            'secret_key' => 'sandbox-secret',
        ],
    ]);

    $access = new PaymentMethodAccess(['is_demo' => false]);

    expect(PlatformElavonCredentials::forPaymentMethodAccess($access))->toMatchArray([
        'mercahantAlias' => 'prod-alias',
        'publicKey' => 'prod-public',
        'secretKey' => 'prod-secret',
        'sandbox' => false,
    ]);
});

it('uses sandbox platform elavon credentials for demo plugins', function (): void {
    config([
        'services.enterprise_elavon.credentials.production' => [
            'merchant_alias' => 'prod-alias',
            'public_key' => 'prod-public',
            'secret_key' => 'prod-secret',
        ],
        'services.enterprise_elavon.credentials.sandbox' => [
            'merchant_alias' => 'sandbox-alias',
            'public_key' => 'sandbox-public',
            'secret_key' => 'sandbox-secret',
        ],
    ]);

    $access = new PaymentMethodAccess(['is_demo' => true]);

    expect($access->usesElavonSandbox())->toBeTrue()
        ->and(PlatformElavonCredentials::forPaymentMethodAccess($access))->toMatchArray([
            'mercahantAlias' => 'sandbox-alias',
            'publicKey' => 'sandbox-public',
            'secretKey' => 'sandbox-secret',
            'sandbox' => true,
        ]);
});

it('shows is_demo on the plugin edit form', function (): void {
    $admin = User::factory()->create([
        'role_id' => User::ROLES['Admin'],
        'password' => bcrypt('password'),
        'service_type' => 'both',
        'pt_free_tier' => false,
    ]);
    $admin->assignRole('admin');

    $owner = User::factory()->create([
        'role_id' => User::ROLES['External'],
        'service_type' => 'both',
        'pt_free_tier' => false,
    ]);

    $plugin = PaymentMethodAccess::query()->create([
        'user_id' => $owner->id,
        'company_name' => 'Demo Toggle Plugin',
        'company_email' => 'plugin-demo-'.uniqid().'@example.com',
        'company_address' => [
            'city' => 'Oslo',
            'street' => 'Main',
            'zip' => '0001',
        ],
        'company_registration' => '123456',
        'company_domain' => 'https://plugin-demo-'.uniqid().'.example.com',
        'key' => (string) Str::uuid(),
        'paymentMethod' => 'elavon',
        'subscriptionMethod' => 'elavon',
        'status' => 0,
        'is_demo' => false,
    ]);

    Livewire::actingAs($admin)
        ->test(EditPaymentMethodAccess::class, ['record' => $plugin->getRouteKey()])
        ->assertFormFieldExists('is_demo')
        ->assertSchemaStateSet([
            'is_demo' => false,
        ]);
});
