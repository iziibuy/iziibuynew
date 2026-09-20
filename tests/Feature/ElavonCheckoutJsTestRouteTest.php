<?php

declare(strict_types=1);

use App\Models\ExternalOrder;
use App\Models\PaymentApi;
use App\Models\PaymentMethodAccess;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);

    if (! Schema::hasColumn('external_orders', 'uuid') && ! Schema::hasColumn('external_orders', 'ulid')) {
        Schema::table('external_orders', function (Blueprint $table): void {
            $table->ulid('uuid')->nullable()->after('id');
        });
    }
});

function createCheckoutJsTestPlugin(): PaymentMethodAccess
{
    $owner = User::factory()->create([
        'role_id' => User::ROLES['External'],
    ]);

    $access = PaymentMethodAccess::query()->create([
        'user_id' => $owner->id,
        'company_name' => 'CheckoutJS Test Plugin',
        'company_email' => 'checkoutjs-test-'.uniqid().'@example.com',
        'key' => (string) Str::uuid(),
        'fee' => 0,
        'paymentMethod' => 'elavon',
        'subscriptionMethod' => 'elavon',
        'status' => 1,
    ]);

    $access->createMetas([
        'elavon_merchant_alias' => 'alias_test',
        'elavon_public_key' => 'pk_test',
        'elavon_secret_key' => 'sk_test',
    ]);

    return $access->fresh();
}

function createCheckoutJsTestOrder(PaymentMethodAccess $access): ExternalOrder
{
    $api = PaymentApi::query()->create([
        'payment_method_access_id' => $access->id,
        'domain' => 'https://example.test',
        'success_redirect_url' => route('test.elavon.checkoutjs.success'),
        'failed_redirect_url' => route('test.elavon.checkoutjs.failed'),
        'status' => true,
        'is_subscription' => false,
        'elavon_link_mode' => PaymentApi::ELAVON_LINK_MODE_CHECKOUTJS,
    ]);

    $publicId = (string) Str::ulid();
    $attributes = [
        'payment_method_access_id' => $access->id,
        'api_id' => $api->id,
        'customer_name' => 'Tester',
        'customer_email' => 'tester@example.com',
        'source_url' => 'https://example.test',
        'success_redirect_url' => route('test.elavon.checkoutjs.success'),
        'failed_redirect_url' => route('test.elavon.checkoutjs.failed'),
        'amount' => 10,
        'currency' => 'NOK',
        'status' => 'COMPLETED',
        'payment_method' => 'elavon',
        'payment_id' => 'ps_test',
        'orderId' => 'CHKJS-TEST-1',
        'description' => 'CheckoutJS temporary test payment',
        'response' => 'tx_test',
    ];

    if (Schema::hasColumn('external_orders', 'uuid')) {
        $attributes['uuid'] = $publicId;
    }

    if (Schema::hasColumn('external_orders', 'ulid')) {
        $attributes['ulid'] = $publicId;
    }

    return ExternalOrder::query()->create($attributes);
}

it('shows the checkoutjs test page in local environments', function (): void {
    createCheckoutJsTestPlugin();

    $this->get(route('test.elavon.checkoutjs'))
        ->assertSuccessful()
        ->assertSee('Elavon CheckoutJS test', false)
        ->assertSee('CheckoutJS Test Plugin', false)
        ->assertSee('Create temporary order', false);
});

it('validates plugin selection when starting a checkoutjs test payment', function (): void {
    $this->from(route('test.elavon.checkoutjs'))
        ->post(route('test.elavon.checkoutjs.start'), [
            'amount' => 10,
            'currency' => 'NOK',
        ])
        ->assertSessionHasErrors('payment_method_access_id');
});

it('shows success redirect details for the temporary session order', function (): void {
    $access = createCheckoutJsTestPlugin();
    $order = createCheckoutJsTestOrder($access);

    $this->withSession([
        'elavon_checkoutjs_test' => [
            'order_id' => $order->id,
            'public_id' => $order->uuid ?? $order->ulid,
            'order_number' => $order->orderId,
            'payment_id' => $order->payment_id,
        ],
    ])
        ->get(route('test.elavon.checkoutjs.success', [
            'order' => $order->orderId,
            'payment_id' => $order->payment_id,
            'transaction_id' => 'tx_test',
        ]))
        ->assertSuccessful()
        ->assertSee('success redirect', false)
        ->assertSee($order->orderId, false)
        ->assertSee('COMPLETED', false)
        ->assertSee('tx_test', false);
});

it('shows failed redirect details for the temporary session order', function (): void {
    $access = createCheckoutJsTestPlugin();
    $order = createCheckoutJsTestOrder($access);
    $order->update(['status' => 'CANCELED']);

    $this->withSession([
        'elavon_checkoutjs_test' => [
            'order_id' => $order->id,
            'public_id' => $order->uuid ?? $order->ulid,
            'order_number' => $order->orderId,
            'payment_id' => $order->payment_id,
        ],
    ])
        ->get(route('test.elavon.checkoutjs.failed', [
            'order' => $order->orderId,
            'payment_id' => $order->payment_id,
            'status' => 'canceled',
        ]))
        ->assertSuccessful()
        ->assertSee('failed / cancel redirect', false)
        ->assertSee('canceled', false)
        ->assertSee('CANCELED', false);
});
