<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\SMS\SmsService;
use Database\Seeders\RoleSeeder;

use function Pest\Laravel\mock;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

it('shows the twilio sms test page in local environments', function (): void {
    $this->get(route('test.twilio.sms'))
        ->assertSuccessful()
        ->assertSee('Twilio SMS test', false)
        ->assertSee('Send test SMS', false);
});

it('validates phone and message before sending', function (): void {
    $this->from(route('test.twilio.sms'))
        ->post(route('test.twilio.sms.send'), [])
        ->assertSessionHasErrors(['phone', 'message']);
});

it('sends a test sms through SmsService when configured', function (): void {
    config([
        'app.debug' => true,
        'services.twilio.sid' => 'test_sid',
        'services.twilio.token' => 'test_token',
        'services.twilio.from' => '+15005550006',
    ]);

    mock(SmsService::class, function ($mock): void {
        $mock->shouldReceive('send')
            ->once()
            ->with('+4799999999', 'Hello from Iziibuy test');
    });

    $this->from(route('test.twilio.sms'))
        ->post(route('test.twilio.sms.send'), [
            'phone' => '+4799999999',
            'message' => 'Hello from Iziibuy test',
        ])
        ->assertRedirect(route('test.twilio.sms'))
        ->assertSessionHas('success');
});

it('hides the twilio sms test page from guests in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    config(['app.debug' => false]);

    $this->get(route('test.twilio.sms'))
        ->assertNotFound();
});

it('allows admins to open the twilio sms test page in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    config(['app.debug' => false]);

    $admin = User::factory()->create([
        'role_id' => User::ROLES['Admin'],
        'password' => bcrypt('password'),
        'service_type' => 'both',
        'pt_free_tier' => false,
    ]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('test.twilio.sms'))
        ->assertSuccessful()
        ->assertSee('Twilio SMS test', false);
});
