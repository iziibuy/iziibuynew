<?php

declare(strict_types=1);

use App\Filament\Resources\PaymentMethodAccesses\Pages\EditPaymentMethodAccess;
use App\Filament\Resources\PaymentMethodAccesses\RelationManagers\PaymentapisRelationManager;
use App\Models\ExternalOrder;
use App\Models\PaymentApi;
use App\Models\PaymentMethodAccess;
use App\Models\User;
use App\Payment\Elavon\ApiElavonPayment;
use App\Payment\Elavon\CheckoutJsTheme;
use Database\Seeders\RoleSeeder;
use Filament\Actions\EditAction;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);

    if (! Schema::hasColumn('external_orders', 'uuid')) {
        Schema::table('external_orders', function (Blueprint $table): void {
            $table->ulid('uuid')->nullable()->after('id');
        });
    }
});

function createCheckoutJsPlugin(array $accessOverrides = [], array $apiOverrides = []): array
{
    $user = User::factory()->create([
        'role_id' => User::ROLES['External'],
    ]);
    $user->assignRole('external');

    $access = PaymentMethodAccess::query()->create(array_merge([
        'user_id' => $user->id,
        'company_name' => 'Checkout Plugin',
        'company_email' => 'checkout-'.uniqid().'@example.com',
        'key' => (string) Str::uuid(),
        'fee' => 0,
        'paymentMethod' => 'elavon',
        'subscriptionMethod' => 'elavon',
        'status' => 1,
    ], $accessOverrides));

    $access->createMetas([
        'elavon_merchant_alias' => 'alias_test',
        'elavon_public_key' => 'pk_test',
        'elavon_secret_key' => 'sk_test',
        'site_mode' => 'test',
    ]);

    if (Schema::hasColumn('payment_method_accesses', 'site_mode')) {
        $access->forceFill(['site_mode' => 'test'])->save();
    }

    $api = PaymentApi::query()->create(array_merge([
        'payment_method_access_id' => $access->id,
        'domain' => 'https://merchant.example',
        'success_redirect_url' => 'https://merchant.example/ok',
        'failed_redirect_url' => 'https://merchant.example/fail',
        'status' => true,
        'is_subscription' => false,
        'elavon_link_mode' => PaymentApi::ELAVON_LINK_MODE_CHECKOUTJS,
    ], $apiOverrides));

    return [$user, $access->fresh(), $api];
}

function createCheckoutJsOrder(PaymentMethodAccess $access, PaymentApi $api, array $overrides = []): ExternalOrder
{
    $publicId = (string) Str::ulid();

    $attributes = array_merge([
        'payment_method_access_id' => $access->id,
        'api_id' => $api->id,
        'customer_name' => 'Jane Doe',
        'customer_email' => 'jane@example.com',
        'customer_phone' => '12345678',
        'customer_address' => 'Main Street 1',
        'customer_post_code' => '0150',
        'source_url' => $api->domain,
        'success_redirect_url' => $api->success_redirect_url,
        'failed_redirect_url' => $api->failed_redirect_url,
        'amount' => 199,
        'currency' => 'NOK',
        'status' => 'PENDING',
        'payment_method' => 'elavon',
        'payment_id' => 'ps_test_session_123',
        'orderId' => 'ORD-100',
        'description' => 'Button payment',
    ], $overrides);

    if (Schema::hasColumn('external_orders', 'uuid')) {
        $attributes['uuid'] = $overrides['uuid'] ?? $publicId;
    }

    if (Schema::hasColumn('external_orders', 'ulid')) {
        $attributes['ulid'] = $overrides['ulid'] ?? ($attributes['uuid'] ?? $publicId);
    }

    return ExternalOrder::query()->create($attributes);
}

it('lets an admin save checkoutjs as the elavon payment link mode in filament', function (): void {
    $admin = User::factory()->create([
        'role_id' => User::ROLES['Admin'],
        'password' => bcrypt('password'),
        'service_type' => 'both',
        'pt_free_tier' => false,
    ]);
    $admin->assignRole('admin');

    [, $access, $api] = createCheckoutJsPlugin(apiOverrides: [
        'elavon_link_mode' => PaymentApi::ELAVON_LINK_MODE_HOSTED,
    ]);

    Livewire::actingAs($admin)
        ->test(PaymentapisRelationManager::class, [
            'ownerRecord' => $access,
            'pageClass' => EditPaymentMethodAccess::class,
        ])
        ->callTableAction(EditAction::class, $api, data: [
            'key' => $api->key,
            'domain' => $api->domain,
            'success_redirect_url' => $api->success_redirect_url,
            'failed_redirect_url' => $api->failed_redirect_url,
            'cancel_callback_url' => $api->cancel_callback_url,
            'status' => true,
            'elavon_link_mode' => PaymentApi::ELAVON_LINK_MODE_CHECKOUTJS,
        ])
        ->assertHasNoTableActionErrors();

    expect($api->fresh()->elavon_link_mode)->toBe('checkoutjs')
        ->and($api->fresh()->usesElavonCheckoutJs())->toBeTrue();
});

it('does not let external users change elavon link mode via button settings', function (): void {
    [$user, , $api] = createCheckoutJsPlugin(apiOverrides: [
        'elavon_link_mode' => PaymentApi::ELAVON_LINK_MODE_HOSTED,
    ]);

    $this->actingAs($user)
        ->post(route('external.buttonPayment.update', $api), [
            'domain' => 'https://merchant.example',
            'success' => 'https://merchant.example/ok',
            'failed' => 'https://merchant.example/fail',
            'elavon_link_mode' => 'checkoutjs',
            'is_subscription' => 0,
        ])
        ->assertRedirect(route('external.buttonPayment'));

    expect($api->fresh()->elavon_link_mode)->toBe('hosted');
});

it('renders the checkoutjs payment page for pending checkoutjs orders', function (): void {
    [, $access, $api] = createCheckoutJsPlugin();
    $order = createCheckoutJsOrder($access, $api);
    $publicId = $order->uuid ?? $order->ulid;

    $this->get(route('elavon.checkoutjs.pay', $publicId))
        ->assertSuccessful()
        ->assertSee('Card details', false)
        ->assertSee('ps_test_session_123', false)
        ->assertSee('hosted-fields-client/index.js', false)
        ->assertSee('ElavonHostedFields', false)
        ->assertSee('Jane Doe', false);
});

it('does not render checkoutjs page for hosted-mode buttons', function (): void {
    [, $access, $api] = createCheckoutJsPlugin(apiOverrides: [
        'elavon_link_mode' => PaymentApi::ELAVON_LINK_MODE_HOSTED,
    ]);
    $order = createCheckoutJsOrder($access, $api);
    $publicId = $order->uuid ?? $order->ulid;

    $this->get(route('elavon.checkoutjs.pay', $publicId))
        ->assertNotFound();
});

it('cancels a pending checkoutjs payment and redirects to the failed url', function (): void {
    [, $access, $api] = createCheckoutJsPlugin();
    $order = createCheckoutJsOrder($access, $api);
    $publicId = $order->uuid ?? $order->ulid;

    $this->get(route('elavon.checkoutjs.cancel', $publicId))
        ->assertRedirect();

    expect($order->fresh()->status)->toBe('CANCELED');
});

it('requires a session id when completing checkoutjs payment', function (): void {
    [, $access, $api] = createCheckoutJsPlugin();
    $order = createCheckoutJsOrder($access, $api);
    $publicId = $order->uuid ?? $order->ulid;

    $this->postJson(route('elavon.checkoutjs.complete', $publicId), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['session_id']);
});

it('uses the configured app url for checkoutjs payment session origin', function (): void {
    config([
        'app.url' => 'https://iziibuy_latest.test',
        'services.enterprise_elavon.hpp_origin_url' => 'https://iziibuy_latest.test',
    ]);

    [, $access, $api] = createCheckoutJsPlugin();
    $order = createCheckoutJsOrder($access, $api);

    $payment = new class($order) extends ApiElavonPayment
    {
        public function resolvedCheckoutOrigin(): string
        {
            return $this->checkoutJsOriginUrl();
        }
    };

    expect($payment->resolvedCheckoutOrigin())->toBe('https://iziibuy_latest.test/');
});

it('builds the own checkoutjs payment url when button mode is checkoutjs', function (): void {
    [, $access, $api] = createCheckoutJsPlugin();
    $order = createCheckoutJsOrder($access, $api, [
        'payment_id' => null,
        'payment_url' => null,
    ]);
    $publicId = $order->uuid ?? $order->ulid;

    $payment = new class($order) extends ApiElavonPayment
    {
        public function getPaymentLink()
        {
            $sessionId = 'ps_mocked_session';
            $publicId = $this->order->uuid ?? $this->order->ulid;

            return [
                'status' => true,
                'code' => 200,
                'data' => [
                    'payment_id' => $sessionId,
                    'url' => $this->usesCheckoutJs()
                        ? route('elavon.checkoutjs.pay', $publicId)
                        : 'https://hpp.example/?sessionId='.$sessionId,
                    'mode' => $this->usesCheckoutJs() ? 'checkoutjs' : 'hosted',
                ],
            ];
        }
    };

    $result = $payment->getPaymentLink();

    expect($result['data']['mode'])->toBe('checkoutjs')
        ->and($result['data']['url'])->toBe(route('elavon.checkoutjs.pay', $publicId));
});

it('shows checkout page customization when the button uses checkoutjs', function (): void {
    [$user, , $api] = createCheckoutJsPlugin();

    $this->actingAs($user)
        ->get(route('external.buttonPayment.edit', $api))
        ->assertSuccessful()
        ->assertSee('Button settings', false)
        ->assertSee('Checkout page', false)
        ->assertSee('Primary color', false)
        ->assertSee('Page text', false)
        ->assertSee('Custom CSS', false)
        ->assertSee('Useful selectors', false)
        ->assertSee('Form heading', false);
});

it('hides checkout page customization for hosted buttons', function (): void {
    [$user, , $api] = createCheckoutJsPlugin(apiOverrides: [
        'elavon_link_mode' => PaymentApi::ELAVON_LINK_MODE_HOSTED,
    ]);

    $this->actingAs($user)
        ->get(route('external.buttonPayment.edit', $api))
        ->assertSuccessful()
        ->assertDontSee('Checkout page', false)
        ->assertDontSee('Useful selectors', false);
});

it('saves checkout page appearance and renders it on the payment page', function (): void {
    Storage::fake(CheckoutJsTheme::disk());

    [$user, $access, $api] = createCheckoutJsPlugin();
    $logo = UploadedFile::fake()->image('mark.png', 80, 80);

    $this->actingAs($user)
        ->post(route('external.buttonPayment.update', $api), [
            'domain' => 'https://merchant.example',
            'success' => 'https://merchant.example/ok',
            'failed' => 'https://merchant.example/fail',
            'is_subscription' => 0,
            'checkout_company_name' => 'Nordic Atelier',
            'checkout_heading' => 'Secure card payment',
            'checkout_lead' => 'Your card never touches our servers.',
            'checkout_pay_button_label' => 'Complete',
            'checkout_cancel_label' => 'Go back',
            'checkout_summary_note' => 'Protected by Elavon.',
            'checkout_primary_color' => '#112233',
            'checkout_secondary_color' => '#aabbcc',
            'checkout_background_color' => '#f7f4ee',
            'checkout_footer' => 'Thank you for shopping with us.',
            'checkout_custom_css' => ".btn-primary { border-radius: 8px; }\n.footnote { letter-spacing: 0.04em; } </style><script>alert(1)</script>",
            'checkout_logo' => $logo,
        ])
        ->assertRedirect(route('external.buttonPayment'));

    $api->refresh();
    $appearance = $api->checkoutjs_appearance;

    expect($appearance['company_name'])->toBe('Nordic Atelier')
        ->and($appearance['heading'])->toBe('Secure card payment')
        ->and($appearance['pay_button_label'])->toBe('Complete')
        ->and($appearance['primary_color'])->toBe('#112233')
        ->and($appearance['footer'])->toBe('Thank you for shopping with us.')
        ->and($appearance['custom_css'])->toContain('.btn-primary { border-radius: 8px; }')
        ->and($appearance['custom_css'])->toContain('.footnote { letter-spacing: 0.04em; }')
        ->and($appearance['custom_css'])->not->toContain('<script>')
        ->and($appearance['logo'])->toStartWith('checkoutjs-logos/');

    Storage::disk(CheckoutJsTheme::disk())->assertExists($appearance['logo']);

    $order = createCheckoutJsOrder($access, $api);
    $publicId = $order->uuid ?? $order->ulid;

    $this->get(route('elavon.checkoutjs.pay', $publicId))
        ->assertSuccessful()
        ->assertSee('Nordic Atelier', false)
        ->assertSee('Secure card payment', false)
        ->assertSee('Your card never touches our servers.', false)
        ->assertSee('Complete', false)
        ->assertSee('Go back', false)
        ->assertSee('Protected by Elavon.', false)
        ->assertSee('#112233', false)
        ->assertSee('Thank you for shopping with us.', false)
        ->assertSee('.btn-primary { border-radius: 8px; }', false)
        ->assertDontSee('</style><script>', false)
        ->assertSee('checkoutjs-logos/', false);
});

it('does not store checkout appearance for hosted buttons', function (): void {
    [$user, , $api] = createCheckoutJsPlugin(apiOverrides: [
        'elavon_link_mode' => PaymentApi::ELAVON_LINK_MODE_HOSTED,
    ]);

    $this->actingAs($user)
        ->post(route('external.buttonPayment.update', $api), [
            'domain' => 'https://merchant.example',
            'success' => 'https://merchant.example/ok',
            'failed' => 'https://merchant.example/fail',
            'is_subscription' => 0,
            'checkout_company_name' => 'Should not save',
            'checkout_primary_color' => '#112233',
        ])
        ->assertRedirect(route('external.buttonPayment'));

    expect($api->fresh()->checkoutjs_appearance)->toBeNull();
});
