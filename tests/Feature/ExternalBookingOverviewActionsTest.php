<?php

declare(strict_types=1);

use App\Mail\ExternalBookingPaymentMessage;
use App\Models\ExternalBooking;
use App\Models\PaymentMethodAccess;
use App\Models\User;
use App\Services\SMS\SmsService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Mockery\MockInterface;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

/**
 * @return array{0: User, 1: PaymentMethodAccess, 2: ExternalBooking}
 */
function createExternalOverviewOwner(?string $smsText = null): array
{
    $owner = User::factory()->create([
        'role_id' => User::ROLES['External'],
    ]);
    $owner->assignRole('external');

    $access = PaymentMethodAccess::query()->create([
        'user_id' => $owner->id,
        'company_name' => 'Overview Plugin',
        'company_email' => 'overview-'.uniqid().'@example.com',
        'company_domain' => 'overview-'.uniqid().'.example.com',
        'company_registration' => '123456789',
        'company_address' => json_encode([
            'city' => 'Oslo',
            'street' => 'Testveien 1',
            'zip' => '0150',
            'post_code' => '0150',
        ]),
        'key' => (string) Str::uuid(),
        'paymentMethod' => 'elavon',
        'status' => 1,
    ]);

    if ($smsText !== null) {
        $access->createMetas(['sms_text' => $smsText]);
    }

    $booking = ExternalBooking::query()->create([
        'ulid' => (string) Str::ulid(),
        'booking_number' => 'BK-'.uniqid(),
        'phone_number' => '+4712345678',
        'payment_method_access_id' => $access->id,
        'tax' => 0,
        'subtotal' => 250,
        'total' => 250,
        'currency' => 'NOK',
        'payment_method' => 'elavon',
        'payment_id' => 'pay-existing',
        'payment_url' => 'https://gateway.example.test/pay/existing',
        'status' => 'PENDING',
        'payment_status' => 'PENDING',
    ]);

    return [$owner, $access, $booking];
}

it('allows the owner to send payment SMS to a chosen phone number', function (): void {
    [$owner, $access, $booking] = createExternalOverviewOwner(
        'Pay {TOTAL} for {BOOKING_NUMBER} at {LINK}'
    );

    $expectedLink = route('external-payment', $booking);
    $expectedMessage = 'Pay '.$booking->total.' '.$booking->currency.' for '.$booking->booking_number.' at '.$expectedLink;
    $overridePhone = '+4798765432';

    $this->mock(SmsService::class, function (MockInterface $mock) use ($overridePhone, $expectedMessage): void {
        $mock->shouldReceive('send')
            ->once()
            ->with($overridePhone, $expectedMessage);
    });

    $this->actingAs($owner)
        ->postJson(route('external.booking.send-sms', $booking), [
            'phone_number' => $overridePhone,
        ])
        ->assertSuccessful()
        ->assertJson([
            'success' => true,
        ]);
});

it('validates the overview sms phone number', function (): void {
    [$owner, $access, $booking] = createExternalOverviewOwner();

    $this->actingAs($owner)
        ->postJson(route('external.booking.send-sms', $booking), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone_number']);
});

it('returns the rendered payment message for copy and respects paid bookings', function (): void {
    [$owner, $access, $booking] = createExternalOverviewOwner(
        'Pay {TOTAL} for {BOOKING_NUMBER} at {LINK}'
    );

    $expectedLink = route('external-payment', $booking);
    $expectedMessage = 'Pay '.$booking->total.' '.$booking->currency.' for '.$booking->booking_number.' at '.$expectedLink;

    $this->actingAs($owner)
        ->postJson(route('external.booking.ensure-payment-link', $booking))
        ->assertSuccessful()
        ->assertJson([
            'success' => true,
            'url' => route('external-payment', $booking),
            'message' => $expectedMessage,
        ]);

    $booking->update([
        'payment_status' => 'PAID',
        'status' => 'COMPLETED',
        'paid_at' => now(),
    ]);

    $this->actingAs($owner)
        ->postJson(route('external.booking.ensure-payment-link', $booking))
        ->assertSuccessful()
        ->assertJson([
            'success' => true,
            'url' => route('external-payment-page', $booking),
            'message' => $expectedMessage,
        ]);
});

it('emails the same rendered sms_text body from the overview', function (): void {
    Mail::fake();

    [$owner, $access, $booking] = createExternalOverviewOwner(
        'Pay {TOTAL} for {BOOKING_NUMBER} at {LINK}'
    );

    $expectedLink = route('external-payment', $booking);
    $expectedMessage = 'Pay '.$booking->total.' '.$booking->currency.' for '.$booking->booking_number.' at '.$expectedLink;

    $this->actingAs($owner)
        ->postJson(route('external.booking.send-email', $booking), [
            'email' => 'customer@example.com',
        ])
        ->assertSuccessful()
        ->assertJson([
            'success' => true,
        ]);

    Mail::assertSent(ExternalBookingPaymentMessage::class, function (ExternalBookingPaymentMessage $mail) use ($booking, $expectedMessage): bool {
        $content = $mail->content();

        return $mail->hasTo('customer@example.com')
            && $mail->bodyText === $expectedMessage
            && $mail->bookingNumber === (string) $booking->booking_number
            && $mail->envelope()->subject === 'Payment for booking '.$booking->booking_number
            && filled($content->htmlString)
            && str_contains($content->htmlString, e($expectedMessage));
    });
});

it('forbids overview actions on another tenants booking', function (): void {
    [$owner, $access, $booking] = createExternalOverviewOwner();
    [$otherOwner] = createExternalOverviewOwner();

    $this->mock(SmsService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('send');
    });
    Mail::fake();

    $this->actingAs($otherOwner)
        ->postJson(route('external.booking.send-sms', $booking), [
            'phone_number' => '+4711111111',
        ])
        ->assertForbidden();

    $this->actingAs($otherOwner)
        ->postJson(route('external.booking.ensure-payment-link', $booking))
        ->assertForbidden();

    $this->actingAs($otherOwner)
        ->postJson(route('external.booking.send-email', $booking), [
            'email' => 'customer@example.com',
        ])
        ->assertForbidden();

    Mail::assertNothingSent();
});

it('shows overview handling actions on the booking index', function (): void {
    [$owner, $access, $booking] = createExternalOverviewOwner();

    $this->actingAs($owner)
        ->get(route('external.booking.index'))
        ->assertOk()
        ->assertSee('btn-send-sms', false)
        ->assertSee('btn-copy-url', false)
        ->assertSee('btn-send-email', false)
        ->assertSee('smsModal', false)
        ->assertSee('emailModal', false)
        ->assertSee(route('external.booking.send-sms', $booking), false)
        ->assertSee(route('external.booking.ensure-payment-link', $booking), false)
        ->assertSee(route('external.booking.send-email', $booking), false);
});

it('validates the overview email recipient', function (): void {
    [$owner, $access, $booking] = createExternalOverviewOwner();

    $this->actingAs($owner)
        ->postJson(route('external.booking.send-email', $booking), [
            'email' => 'not-an-email',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});
