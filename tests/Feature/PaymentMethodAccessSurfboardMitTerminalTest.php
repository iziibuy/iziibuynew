<?php

declare(strict_types=1);

use App\Filament\Resources\PaymentMethodAccesses\Schemas\PaymentMethodAccessForm;
use App\Models\PaymentMethodAccess;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('treats surfboard mit terminal id as meta, not a table column', function (): void {
    expect(PaymentMethodAccessForm::metaFieldNames())->toContain('surfboard_mit_terminalId')
        ->and(Schema::hasColumn('payment_method_accesses', 'surfboard_mit_terminalId'))->toBeFalse();
});

it('reads and writes surfboard mit terminal id through metas', function (): void {
    $owner = User::factory()->create([
        'role_id' => User::ROLES['External'],
        'service_type' => 'both',
        'pt_free_tier' => false,
    ]);

    $plugin = PaymentMethodAccess::query()->create([
        'user_id' => $owner->id,
        'company_name' => 'Surfboard MIT Plugin',
        'company_email' => 'plugin-mit-'.uniqid().'@example.com',
        'company_address' => [
            'city' => 'Oslo',
            'street' => 'Main',
            'zip' => '0001',
        ],
        'company_registration' => '123456',
        'company_domain' => 'https://plugin-mit-'.uniqid().'.example.com',
        'key' => (string) Str::uuid(),
        'paymentMethod' => 'surfboard',
        'subscriptionMethod' => 'surfboard',
        'status' => 0,
    ]);

    $plugin->createMetas([
        'surfboard_mit_terminalId' => 'terminal-mit-123',
    ]);

    $plugin->refresh();

    expect($plugin->surfboard_mit_terminalId)->toBe('terminal-mit-123')
        ->and($plugin->metas()->where('column_name', 'surfboard_mit_terminalId')->value('column_value'))
        ->toBe('terminal-mit-123');
});
