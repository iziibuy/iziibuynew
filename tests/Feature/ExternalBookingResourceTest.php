<?php

declare(strict_types=1);

use App\Filament\Resources\ExternalBookings\Pages\ListExternalBookings;
use App\Filament\Resources\ExternalBookings\Pages\ViewExternalBooking;
use App\Models\ExternalBooking;
use App\Models\PaymentMethodAccess;
use App\Models\User;
use App\Services\ExternalBookingGatewayInspector;
use App\Support\ExternalPaymentAcquirer;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

function createExternalBookingAdmin(): User
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

function createExternalBookingFixture(): ExternalBooking
{
    $owner = User::factory()->create([
        'role_id' => User::ROLES['External'],
    ]);

    $access = PaymentMethodAccess::query()->create([
        'user_id' => $owner->id,
        'company_name' => 'Booking Plugin',
        'company_email' => 'book-'.uniqid().'@example.com',
        'key' => (string) Str::uuid(),
        'paymentMethod' => 'surfboard',
        'status' => 1,
    ]);

    $access->createMetas([
        'surfboard_merchantId' => 'merchant-1',
        'surfboard_storeId' => 'store-1',
        'surfboard_terminalId' => 'terminal-1',
    ]);

    return ExternalBooking::query()->create([
        'ulid' => (string) Str::ulid(),
        'booking_number' => 'BK-'.uniqid(),
        'phone_number' => '12345678',
        'payment_method_access_id' => $access->id,
        'tax' => 25,
        'subtotal' => 225,
        'total' => 250,
        'currency' => 'NOK',
        'payment_method' => 'surfboard',
        'payment_id' => 'ord-booking-1',
        'status' => 'COMPLETED',
        'payment_status' => 'PAID',
        'paid_at' => now(),
    ]);
}

it('lists plugin bookings in filament', function (): void {
    $admin = createExternalBookingAdmin();
    $booking = createExternalBookingFixture();

    Livewire::actingAs($admin)
        ->test(ListExternalBookings::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$booking]);
});

it('shows booking details on the view page', function (): void {
    $admin = createExternalBookingAdmin();
    $booking = createExternalBookingFixture();

    Livewire::actingAs($admin)
        ->test(ViewExternalBooking::class, ['record' => $booking->getRouteKey()])
        ->assertSuccessful()
        ->assertSee($booking->booking_number)
        ->assertSee('Fetch gateway details');
});

it('inspects surfboard gateway details for a plugin booking', function (): void {
    $booking = createExternalBookingFixture();

    Http::fake([
        '*/orders/ord-booking-1/status' => Http::response([
            'data' => [
                'orderStatus' => 'PAYMENT_COMPLETED',
            ],
        ], 200),
        '*/orders/ord-booking-1' => Http::response([
            'data' => [
                'orderId' => 'ord-booking-1',
                'status' => 'PAYMENT_COMPLETED',
            ],
        ], 200),
    ]);

    $result = app(ExternalBookingGatewayInspector::class)->inspect($booking);

    expect($result['success'])->toBeTrue()
        ->and($result['provider'])->toBe('surfboard')
        ->and($result['summary']['order_status'])->toBe('PAYMENT_COMPLETED')
        ->and($result['gateway']['order'])->not->toBeNull();
});

it('resolves elavon for dual payment_method when elavon transaction exists', function (): void {
    $booking = createExternalBookingFixture();
    $booking->update(['payment_method' => 'elavon,surfboard']);
    $booking->createMeta('elavon_transaction_id', 'mrtqfhgpkyx4qxh3vykjhjxppmb6');

    expect(ExternalPaymentAcquirer::forBooking($booking->fresh()))->toBe('elavon')
        ->and($booking->fresh()->resolvedPaymentMethod())->toBe('elavon')
        ->and($booking->fresh()->usesElavon())->toBeTrue();
});

it('defaults dual payment_method without transaction evidence to elavon', function (): void {
    expect(ExternalPaymentAcquirer::resolve('elavon,surfboard'))->toBe('elavon')
        ->and(ExternalPaymentAcquirer::resolve('surfboard'))->toBe('surfboard');
});
