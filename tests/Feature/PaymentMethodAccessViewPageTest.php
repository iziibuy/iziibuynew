<?php

declare(strict_types=1);

use App\Filament\Resources\PaymentMethodAccesses\Pages\ViewPaymentMethodAccess;
use App\Filament\Resources\PaymentMethodAccesses\RelationManagers\ExternalBookingsRelationManager;
use App\Filament\Resources\PaymentMethodAccesses\RelationManagers\ExternalOrdersRelationManager;
use App\Models\ExternalBooking;
use App\Models\ExternalOrder;
use App\Models\PaymentApi;
use App\Models\PaymentMethodAccess;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

function createPluginAdmin(): User
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

function createPluginFixture(): PaymentMethodAccess
{
    $owner = User::factory()->create();

    return PaymentMethodAccess::query()->create([
        'user_id' => $owner->id,
        'company_name' => 'View Page Plugin',
        'company_email' => 'plugin-view-'.uniqid().'@example.com',
        'key' => (string) Str::uuid(),
        'subscriptionMethod' => 'elavon',
        'paymentMethod' => 'elavon',
        'status' => 1,
    ]);
}

it('shows plugin details on the view page', function (): void {
    $admin = createPluginAdmin();
    $access = createPluginFixture();

    Livewire::actingAs($admin)
        ->test(ViewPaymentMethodAccess::class, ['record' => $access->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('View Page Plugin')
        ->assertSee($access->company_email);
});

it('lists the plugin button payment orders relation', function (): void {
    $admin = createPluginAdmin();
    $access = createPluginFixture();

    $api = PaymentApi::query()->create([
        'payment_method_access_id' => $access->id,
        'domain' => 'https://merchant.example',
        'success_redirect_url' => 'https://merchant.example/ok',
        'failed_redirect_url' => 'https://merchant.example/fail',
        'status' => true,
        'is_subscription' => false,
    ]);

    $order = ExternalOrder::query()->create([
        'ulid' => (string) Str::ulid(),
        'api_id' => $api->id,
        'payment_method_access_id' => $access->id,
        'customer_name' => 'Related Customer',
        'customer_email' => 'related@example.com',
        'source_url' => 'https://merchant.example',
        'success_redirect_url' => 'https://merchant.example/ok',
        'failed_redirect_url' => 'https://merchant.example/fail',
        'amount' => 199,
        'currency' => 'NOK',
        'status' => 'COMPLETED',
        'payment_method' => 'elavon',
        'payment_id' => 'ord-related-1',
        'orderId' => 'MERCHANT-related-1',
    ]);

    Livewire::actingAs($admin)
        ->test(ExternalOrdersRelationManager::class, [
            'ownerRecord' => $access,
            'pageClass' => ViewPaymentMethodAccess::class,
        ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$order]);
});

it('lists the plugin bookings relation', function (): void {
    $admin = createPluginAdmin();
    $access = createPluginFixture();

    $booking = ExternalBooking::query()->create([
        'ulid' => (string) Str::ulid(),
        'booking_number' => 'BK-related-1',
        'payment_method_access_id' => $access->id,
        'phone_number' => '12345678',
        'tax' => 0,
        'subtotal' => 199,
        'total' => 199,
        'currency' => 'NOK',
        'status' => 'PENDING',
        'payment_status' => 'PENDING',
    ]);

    Livewire::actingAs($admin)
        ->test(ExternalBookingsRelationManager::class, [
            'ownerRecord' => $access,
            'pageClass' => ViewPaymentMethodAccess::class,
        ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$booking]);
});
