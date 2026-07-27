<?php

declare(strict_types=1);

use App\Filament\Resources\ExternalOrders\Pages\ListExternalOrders;
use App\Filament\Resources\ExternalOrders\Pages\ViewExternalOrder;
use App\Models\ExternalOrder;
use App\Models\PaymentApi;
use App\Models\PaymentMethodAccess;
use App\Models\User;
use App\Services\ExternalOrderGatewayInspector;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

function createExternalOrderAdmin(): User
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

function createExternalOrderFixture(): ExternalOrder
{
    $owner = User::factory()->create([
        'role_id' => User::ROLES['External'],
    ]);

    $access = PaymentMethodAccess::query()->create([
        'user_id' => $owner->id,
        'company_name' => 'Button Plugin',
        'company_email' => 'btn-'.uniqid().'@example.com',
        'key' => (string) Str::uuid(),
        'paymentMethod' => 'surfboard',
        'status' => 1,
    ]);

    $access->createMetas([
        'surfboard_merchantId' => 'merchant-1',
        'surfboard_storeId' => 'store-1',
        'surfboard_terminalId' => 'terminal-1',
    ]);

    $api = PaymentApi::query()->create([
        'payment_method_access_id' => $access->id,
        'domain' => 'https://merchant.example',
        'success_redirect_url' => 'https://merchant.example/ok',
        'failed_redirect_url' => 'https://merchant.example/fail',
        'status' => true,
        'is_subscription' => false,
    ]);

    $attributes = [
        'api_id' => $api->id,
        'payment_method_access_id' => $access->id,
        'customer_name' => 'Jane Doe',
        'customer_email' => 'jane@example.com',
        'customer_phone' => '12345678',
        'source_url' => 'https://merchant.example',
        'success_redirect_url' => 'https://merchant.example/ok',
        'failed_redirect_url' => 'https://merchant.example/fail',
        'amount' => 250,
        'currency' => 'NOK',
        'status' => 'COMPLETED',
        'payment_method' => 'surfboard',
        'payment_id' => 'ord-analyze-1',
        'description' => 'Test purchase',
        'orderId' => 'MERCHANT-99',
    ];

    if (Schema::hasColumn('external_orders', 'uuid')) {
        $attributes['uuid'] = (string) Str::ulid();
    } elseif (Schema::hasColumn('external_orders', 'ulid')) {
        $attributes['ulid'] = (string) Str::ulid();
    }

    if (Schema::hasColumn('external_orders', 'paid_at')) {
        $attributes['paid_at'] = now();
    }

    return ExternalOrder::query()->create($attributes);
}

it('lists external button payment orders in filament', function (): void {
    $admin = createExternalOrderAdmin();
    $order = createExternalOrderFixture();

    Livewire::actingAs($admin)
        ->test(ListExternalOrders::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$order]);
});

it('shows external order details on the view page', function (): void {
    $admin = createExternalOrderAdmin();
    $order = createExternalOrderFixture();

    Livewire::actingAs($admin)
        ->test(ViewExternalOrder::class, ['record' => $order->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Jane Doe')
        ->assertSee('jane@example.com')
        ->assertSee('Fetch gateway details');
});

it('inspects surfboard gateway details for an external order', function (): void {
    $order = createExternalOrderFixture();

    Http::fake([
        '*/orders/ord-analyze-1/status' => Http::response([
            'data' => [
                'orderStatus' => 'PAYMENT_COMPLETED',
                'paymentId' => 'pay-1',
            ],
        ], 200),
        '*/payments/pay-1' => Http::response([
            'data' => [
                'paymentId' => 'pay-1',
                'status' => 'CAPTURED',
            ],
        ], 200),
    ]);

    $result = app(ExternalOrderGatewayInspector::class)->inspect($order);

    expect($result['success'])->toBeTrue()
        ->and($result['provider'])->toBe('surfboard')
        ->and($result['summary']['order_status'])->toBe('PAYMENT_COMPLETED')
        ->and($result['gateway']['payment'])->not->toBeNull();
});
