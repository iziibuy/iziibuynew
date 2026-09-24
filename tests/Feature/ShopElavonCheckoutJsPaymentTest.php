<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Payment\Elavon\CheckoutJsTheme;
use App\Payment\Elavon\ElavonPayment;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);

    if (! Schema::hasColumn('shops', 'elavon_link_mode')) {
        Schema::table('shops', function (Blueprint $table): void {
            $table->string('elavon_link_mode', 32)->default('hosted');
            $table->json('checkoutjs_appearance')->nullable();
        });
    }

    if (! Schema::hasColumn('orders', 'uuid')) {
        Schema::table('orders', function (Blueprint $table): void {
            $table->ulid('uuid')->nullable()->unique();
        });
    }
});

function createCheckoutJsShop(array $shopOverrides = []): array
{
    $user = User::factory()->create([
        'role_id' => User::ROLES['Vendor'],
    ]);
    $user->assignRole('vendor');

    $shop = Shop::query()->create(array_merge([
        'user_id' => $user->id,
        'user_name' => 'shop-checkoutjs-'.uniqid(),
        'subscription_id' => 'sub-'.uniqid(),
        'subscriptionMethod' => 'elavon',
        'paymentMethod' => 'elavon',
        'status' => 1,
        'elavon_link_mode' => Shop::ELAVON_LINK_MODE_CHECKOUTJS,
    ], $shopOverrides));

    $shop->createMetas([
        'company_name' => 'Shop Checkout',
        'elavon_merchant_alias' => 'alias_test',
        'elavon_public_key' => 'pk_test',
        'elavon_secret_key' => 'sk_test',
        'site_mode' => 'test',
    ]);

    return [$user, $shop->fresh()];
}

function createShopCheckoutOrder(Shop $shop, array $overrides = []): Order
{
    $order = Order::query()->create(array_merge([
        'shop_id' => $shop->id,
        'user_id' => $shop->user_id,
        'total' => 250,
        'currency' => 'NOK',
        'status' => 0,
        'payment_status' => 0,
        'payment_method' => 'elavon',
        'payment_id' => 'ps_shop_session_123',
        'uuid' => (string) Str::ulid(),
    ], $overrides));

    $order->createMetas([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'address' => 'Main Street 1',
        'city' => 'Oslo',
        'post_code' => '0150',
        'phone' => '12345678',
    ]);

    return $order->fresh();
}

it('shows checkout page customization on the shop payment tab when checkoutjs is enabled', function (): void {
    [$user, $shop] = createCheckoutJsShop();

    $this->actingAs($user)
        ->get(route('shop.store.profile'))
        ->assertSuccessful()
        ->assertSee('Elavon payment page', false)
        ->assertSee('Elavon checkout page', false)
        ->assertSee('Custom CSS', false)
        ->assertSee('Useful selectors', false);
});

it('lets a shop choose the elavon payment page mode from the profile', function (): void {
    [$user, $shop] = createCheckoutJsShop([
        'elavon_link_mode' => Shop::ELAVON_LINK_MODE_HOSTED,
    ]);

    $this->actingAs($user)
        ->get(route('shop.store.profile'))
        ->assertSuccessful()
        ->assertSee('Elavon payment page', false)
        ->assertSee('Own CheckoutJS page', false)
        ->assertSee('Select “Own CheckoutJS page” above', false);

    $this->actingAs($user)
        ->post(route('shop.store.profile.update'), [
            'user_name' => $shop->user_name,
            'default_currency' => 'NOK',
            'currencies' => ['NOK'],
            'meta' => [
                'site_mode' => 'test',
            ],
            'elavon_link_mode' => Shop::ELAVON_LINK_MODE_CHECKOUTJS,
            'checkout_company_name' => 'Shop Enabled Brand',
            'checkout_primary_color' => '#13579B',
        ])
        ->assertRedirect();

    $shop->refresh();

    expect($shop->elavon_link_mode)->toBe(Shop::ELAVON_LINK_MODE_CHECKOUTJS)
        ->and($shop->usesElavonCheckoutJs())->toBeTrue()
        ->and($shop->checkoutjs_appearance['company_name'])->toBe('Shop Enabled Brand')
        ->and($shop->checkoutjs_appearance['primary_color'])->toBe('#13579B');
});

it('lets a shop save checkout appearance and renders it for pending orders', function (): void {
    Storage::fake(CheckoutJsTheme::disk());

    [$user, $shop] = createCheckoutJsShop();
    $logo = UploadedFile::fake()->image('shop-mark.png', 80, 80);

    $this->actingAs($user)
        ->post(route('shop.store.profile.update'), [
            'user_name' => $shop->user_name,
            'default_currency' => 'NOK',
            'currencies' => ['NOK'],
            'meta' => [
                'site_mode' => 'test',
            ],
            'checkout_company_name' => 'Nordic Shop',
            'checkout_heading' => 'Pay for your order',
            'checkout_lead' => 'Card details stay with Elavon.',
            'checkout_pay_button_label' => 'Pay now',
            'checkout_cancel_label' => 'Back to shop',
            'checkout_summary_note' => 'Protected checkout.',
            'checkout_primary_color' => '#112233',
            'checkout_secondary_color' => '#AABBCC',
            'checkout_background_color' => '#F7F4EE',
            'checkout_footer' => 'Thanks for your order.',
            'checkout_custom_css' => '.btn-primary { border-radius: 10px; }',
            'checkout_logo' => $logo,
        ])
        ->assertRedirect();

    $shop->refresh();
    $appearance = $shop->checkoutjs_appearance;

    expect($shop->usesElavonCheckoutJs())->toBeTrue()
        ->and($appearance['company_name'])->toBe('Nordic Shop')
        ->and($appearance['heading'])->toBe('Pay for your order')
        ->and($appearance['primary_color'])->toBe('#112233')
        ->and($appearance['footer'])->toBe('Thanks for your order.')
        ->and($appearance['logo'])->toStartWith('checkoutjs-logos/');

    $order = createShopCheckoutOrder($shop);

    $this->get(route('elavon.checkoutjs.shop.pay', $order->uuid))
        ->assertSuccessful()
        ->assertSee('Nordic Shop', false)
        ->assertSee('Pay for your order', false)
        ->assertSee('Card details stay with Elavon.', false)
        ->assertSee('Pay now', false)
        ->assertSee('Back to shop', false)
        ->assertSee('Protected checkout.', false)
        ->assertSee('#112233', false)
        ->assertSee('Thanks for your order.', false)
        ->assertSee('.btn-primary { border-radius: 10px; }', false)
        ->assertSee('Jane Doe', false)
        ->assertSee('hosted-fields-client/index.js', false);
});

it('does not render the shop checkoutjs page for hosted shops', function (): void {
    [, $shop] = createCheckoutJsShop([
        'elavon_link_mode' => Shop::ELAVON_LINK_MODE_HOSTED,
    ]);
    $order = createShopCheckoutOrder($shop);

    $this->get(route('elavon.checkoutjs.shop.pay', $order->uuid))
        ->assertNotFound();
});

it('cancels a pending shop checkoutjs payment', function (): void {
    [, $shop] = createCheckoutJsShop();
    $order = createShopCheckoutOrder($shop);

    $this->get(route('elavon.checkoutjs.shop.cancel', $order->uuid))
        ->assertRedirect(route('shop.home', $shop->user_name));

    expect((int) Order::withoutGlobalScopes()->find($order->id)->status)->toBe(3);
});

it('builds the shop checkoutjs payment url when checkoutjs mode is enabled', function (): void {
    [, $shop] = createCheckoutJsShop();
    $order = createShopCheckoutOrder($shop, [
        'payment_id' => null,
    ]);

    $payment = new class($order) extends ElavonPayment
    {
        public function getPaymentLink()
        {
            $publicId = $this->ensurePublicId();
            $sessionId = 'ps_shop_created_456';

            return [
                'status' => true,
                'code' => 200,
                'data' => [
                    'payment_id' => $sessionId,
                    'url' => $this->usesCheckoutJs()
                        ? route('elavon.checkoutjs.shop.pay', $publicId)
                        : 'https://hpp.example/?sessionId='.$sessionId,
                    'mode' => $this->usesCheckoutJs() ? 'checkoutjs' : 'hosted',
                ],
            ];
        }
    };

    $result = $payment->getPaymentLink();

    expect($result['data']['mode'])->toBe('checkoutjs')
        ->and($result['data']['url'])->toBe(route('elavon.checkoutjs.shop.pay', $order->fresh()->uuid));
});
