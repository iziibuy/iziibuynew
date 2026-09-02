<?php

declare(strict_types=1);

use App\Models\Enterprise;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

it('reports when no enterprises are due', function (): void {
    $this->artisan('enterprise:payment', ['status' => 1, '--dry-run' => true])
        ->expectsOutputToContain('No enterprises matched the due criteria.')
        ->assertSuccessful();
});

it('lists due enterprises in dry-run without charging', function (): void {
    $paidAt = now()->subMonth()->startOfMonth();

    $enterprise = Enterprise::query()->create([
        'unqid' => (string) Str::ulid(),
        'domain' => 'https://enterprise-'.uniqid().'.example.com',
        'enterprise_name' => 'Acme Enterprise',
        'status' => 1,
        'paid_at' => $paidAt,
        'subscription_id' => 'stored-card-123',
    ]);

    $enterprise->subscription()->create([
        'key' => 'stored-card-123',
        'fee' => 299,
        'status' => 1,
        'paid_at' => now()->subMonth(),
    ]);

    $this->artisan('enterprise:payment', ['status' => 1, '--dry-run' => true, '--id' => $enterprise->id])
        ->expectsOutputToContain('Found 1 enterprise(s) to process.')
        ->expectsOutputToContain('Acme Enterprise')
        ->expectsOutputToContain('DRY-RUN: would charge now.')
        ->expectsOutputToContain('Summary')
        ->assertSuccessful();

    expect((string) $enterprise->fresh()->paid_at)->toStartWith($paidAt->toDateString());
});

it('explains why a filtered enterprise is not due', function (): void {
    $enterprise = Enterprise::query()->create([
        'unqid' => (string) Str::ulid(),
        'domain' => 'https://enterprise-'.uniqid().'.example.com',
        'enterprise_name' => 'Already Paid Co',
        'status' => 1,
        'paid_at' => now()->startOfMonth()->addDay(),
        'subscription_id' => 'stored-card-456',
    ]);

    $this->artisan('enterprise:payment', ['status' => 1, '--dry-run' => true, '--id' => $enterprise->id])
        ->expectsOutputToContain('No enterprises matched the due criteria.')
        ->expectsOutputToContain('exists but is not due')
        ->expectsOutputToContain('status: 1')
        ->assertSuccessful();
});

it('uses the enterprise total fee when the stored fee is zero', function (): void {
    $domain = 'https://enterprise-'.uniqid().'.example.com';
    Http::fake([
        $domain.'/api/enterprise/*/details' => Http::response(['total_fee' => 4210]),
    ]);

    $enterprise = Enterprise::query()->create([
        'unqid' => (string) Str::ulid(),
        'domain' => $domain,
        'enterprise_name' => 'Zero Fee Enterprise',
        'status' => 1,
        'paid_at' => now()->subMonth(),
        'subscription_id' => 'stored-card-789',
    ]);

    $enterprise->subscription()->create([
        'key' => 'stored-card-789',
        'fee' => 0,
        'status' => 1,
        'paid_at' => now()->subMonth(),
    ]);

    $this->artisan('enterprise:payment', ['status' => 1, '--dry-run' => true, '--id' => $enterprise->id])
        ->expectsOutputToContain('fee: 4,210.00 NOK')
        ->expectsOutputToContain('DRY-RUN: would charge now.')
        ->assertSuccessful();
});
