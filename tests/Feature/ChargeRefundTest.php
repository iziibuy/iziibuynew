<?php

declare(strict_types=1);

use App\Elavon\Converge2\Converge2;
use App\Elavon\Converge2\DataObject\TransactionState;
use App\Elavon\Converge2\Response\TransactionResponse;
use App\Exceptions\ChargeRefundException;
use App\Filament\Pages\AllChargesPage;
use App\Models\Charge;
use App\Models\ChargeRefund;
use App\Models\PaymentMethodAccess;
use App\Models\RetailerEarning;
use App\Models\Shop;
use App\Models\SubscriptionCharge;
use App\Models\User;
use App\Services\Admin\ChargeElavonGateway;
use App\Services\Admin\ChargeRefundService;
use App\Services\Admin\UnifiedChargeLedger;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;

const REFUND_TEST_TX_SEPTEMBER = 'sepsepsepsepsepsepsepsep01';
const REFUND_TEST_TX_OCTOBER = 'octoctoctoctoctoctoctoct01';

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    $this->seed(RoleSeeder::class);
});

/**
 * @return array{plugin: PaymentMethodAccess, september: SubscriptionCharge, october: SubscriptionCharge}
 */
function createRefundablePluginCharges(): array
{
    $plugin = PaymentMethodAccess::query()->create([
        'user_id' => User::factory()->create(['role_id' => User::ROLES['External']])->id,
        'company_name' => 'Refund Plugin',
        'company_email' => 'refund-'.uniqid().'@example.com',
        'key' => (string) Str::uuid(),
        'subscriptionMethod' => 'elavon',
        'paymentMethod' => 'elavon',
        'status' => 1,
        'fee' => 199,
        'last_paid_at' => Carbon::parse('2026-10-14 09:51:40'),
    ]);

    $subscription = $plugin->subscription()->create([
        'key' => 'stored-card-refund',
        'fee' => 199,
        'status' => 1,
        'paid_at' => Carbon::parse('2026-10-14 09:51:40'),
    ]);

    $september = SubscriptionCharge::query()->create([
        'subscription_id' => $subscription->id,
        'amount' => 134.95,
        'status' => 1,
        'elavon_transaction_id' => REFUND_TEST_TX_SEPTEMBER,
        'payment_details' => json_encode(['type' => 'signup']),
        'created_at' => Carbon::parse('2026-09-15 21:23:21'),
    ]);

    $october = SubscriptionCharge::query()->create([
        'subscription_id' => $subscription->id,
        'amount' => 150.50,
        'status' => 1,
        'elavon_transaction_id' => REFUND_TEST_TX_OCTOBER,
        'payment_details' => json_encode(['type' => 'monthly']),
        'created_at' => Carbon::parse('2026-10-14 09:51:40'),
    ]);

    return ['plugin' => $plugin->fresh(), 'september' => $september, 'october' => $october];
}

/**
 * @return array<string, mixed>
 */
function refundLedgerRow(string $ledgerKey): array
{
    return app(UnifiedChargeLedger::class)->find($ledgerKey);
}

function fakeElavonTransaction(string $state, bool $success = true, ?string $id = null): TransactionResponse
{
    $transaction = Mockery::mock(TransactionResponse::class);
    $transaction->shouldReceive('isSuccess')->andReturn($success);
    $transaction->shouldReceive('getState')->andReturn(new TransactionState($state));
    $transaction->shouldReceive('getHref')->andReturn('https://api.converge.test/transactions/'.($id ?? 'parent'));
    $transaction->shouldReceive('getId')->andReturn($id);
    $transaction->shouldReceive('getTotalRefundedAmount')->andReturn(null);
    $transaction->shouldReceive('hasFailures')->andReturn(! $success);
    $transaction->shouldReceive('getFailures')->andReturn([]);

    return $transaction;
}

function fakeElavonClient(TransactionResponse $original, ?Closure $configure = null): Converge2
{
    $client = Mockery::mock(Converge2::class);
    $client->shouldReceive('getTransaction')->andReturn($original);

    if ($configure !== null) {
        $configure($client);
    }

    test()->partialMock(ChargeElavonGateway::class, function ($gateway) use ($client): void {
        $gateway->shouldReceive('client')->andReturn($client);
    });

    return $client;
}

it('refunds a settled plugin charge and moves the paid date back to the previous charge', function (): void {
    ['plugin' => $plugin, 'september' => $september, 'october' => $october] = createRefundablePluginCharges();
    $ledgerKey = UnifiedChargeLedger::SOURCE_SUBSCRIPTION.'-'.$october->id;
    $admin = User::factory()->create();

    fakeElavonClient(fakeElavonTransaction(TransactionState::SETTLED, id: REFUND_TEST_TX_OCTOBER), function ($client): void {
        $client->shouldReceive('createRefundTransaction')
            ->once()
            ->with([
                'type' => 'refund',
                'parentTransaction' => 'https://api.converge.test/transactions/'.REFUND_TEST_TX_OCTOBER,
                'total' => ['amount' => 150.5, 'currencyCode' => 'NOK'],
            ])
            ->andReturn(fakeElavonTransaction(TransactionState::CAPTURED, id: 'refundrefundrefundrefund01'));
        $client->shouldNotReceive('createVoidTransaction');
    });

    $refund = app(ChargeRefundService::class)->refund(refundLedgerRow($ledgerKey), 'Pro-rated by mistake', $admin);

    expect($refund->provider_operation)->toBe(ChargeRefund::OPERATION_REFUND)
        ->and($refund->provider_transaction_id)->toBe('refundrefundrefundrefund01')
        ->and((float) $refund->amount)->toBe(150.5)
        ->and($refund->refunded_by)->toBe($admin->id)
        ->and($refund->rollback['paid_date']['reverted'])->toBeTrue()
        ->and($refund->rollback['paid_date']['previous_charge_id'])->toBe($september->id);

    $plugin->refresh();

    expect(Carbon::parse($plugin->last_paid_at)->toDateTimeString())->toBe('2026-09-15 21:23:21')
        ->and(Carbon::parse($plugin->subscription->paid_at)->toDateTimeString())->toBe('2026-09-15 21:23:21')
        ->and((int) $plugin->status)->toBe(1);

    $row = refundLedgerRow($ledgerKey);

    expect($row['refunded'])->toBeTrue()
        ->and($row['status_label'])->toBe('Refunded')
        ->and(app(ChargeRefundService::class)->blockedReason($row))->toBe('This charge has already been refunded.');
});

it('voids a charge that Elavon has not settled yet', function (): void {
    ['october' => $october] = createRefundablePluginCharges();

    fakeElavonClient(fakeElavonTransaction(TransactionState::CAPTURED, id: REFUND_TEST_TX_OCTOBER), function ($client): void {
        $client->shouldReceive('createVoidTransaction')
            ->once()
            ->with(['type' => 'void', 'parentTransaction' => 'https://api.converge.test/transactions/'.REFUND_TEST_TX_OCTOBER])
            ->andReturn(fakeElavonTransaction(TransactionState::VOIDED, id: 'voidvoidvoidvoidvoidvoid01'));
        $client->shouldNotReceive('createRefundTransaction');
    });

    $refund = app(ChargeRefundService::class)->refund(
        refundLedgerRow(UnifiedChargeLedger::SOURCE_SUBSCRIPTION.'-'.$october->id),
        'Duplicate charge',
    );

    expect($refund->provider_operation)->toBe(ChargeRefund::OPERATION_VOID);
});

it('changes nothing when Elavon declines the refund', function (): void {
    ['plugin' => $plugin, 'october' => $october] = createRefundablePluginCharges();

    fakeElavonClient(fakeElavonTransaction(TransactionState::SETTLED, id: REFUND_TEST_TX_OCTOBER), function ($client): void {
        $client->shouldReceive('createRefundTransaction')->andReturn(fakeElavonTransaction(TransactionState::DECLINED, success: false));
    });

    expect(fn () => app(ChargeRefundService::class)->refund(
        refundLedgerRow(UnifiedChargeLedger::SOURCE_SUBSCRIPTION.'-'.$october->id),
        'Should fail',
    ))->toThrow(ChargeRefundException::class, 'Elavon did not approve the refund');

    expect(ChargeRefund::query()->count())->toBe(0)
        ->and(Carbon::parse($plugin->fresh()->last_paid_at)->toDateTimeString())->toBe('2026-10-14 09:51:40');
});

it('keeps the paid date when a later charge exists', function (): void {
    ['plugin' => $plugin, 'september' => $september] = createRefundablePluginCharges();

    fakeElavonClient(fakeElavonTransaction(TransactionState::SETTLED, id: REFUND_TEST_TX_SEPTEMBER), function ($client): void {
        $client->shouldReceive('createRefundTransaction')->andReturn(fakeElavonTransaction(TransactionState::CAPTURED, id: 'refundrefundrefundrefund02'));
    });

    $refund = app(ChargeRefundService::class)->refund(
        refundLedgerRow(UnifiedChargeLedger::SOURCE_SUBSCRIPTION.'-'.$september->id),
        'Old signup refund',
    );

    expect($refund->rollback['paid_date']['reverted'])->toBeFalse()
        ->and(Carbon::parse($plugin->fresh()->last_paid_at)->toDateTimeString())->toBe('2026-10-14 09:51:40');
});

it('refunds a shop charge and reverses the retailer commission paid for it', function (): void {
    $retailer = User::factory()->create();
    $shop = Shop::query()->create([
        'user_id' => User::factory()->create()->id,
        'user_name' => 'refund-shop-'.uniqid(),
        'subscriptionMethod' => 'elavon',
        'paymentMethod' => 'elavon',
        'status' => 1,
        'establishment' => 1,
        'monthly_cost' => 599,
        'paid_at' => Carbon::parse('2026-10-14 09:51:27'),
    ]);

    $september = Charge::query()->create([
        'shop_id' => $shop->id, 'order_id' => 'shopshopshopshopshopshop01', 'amount' => 748.75, 'status' => 1,
        'created_at' => Carbon::parse('2026-09-02 18:30:20'),
    ]);
    $october = Charge::query()->create([
        'shop_id' => $shop->id, 'order_id' => 'shopshopshopshopshopshop02', 'amount' => 748.75, 'status' => 1,
        'created_at' => Carbon::parse('2026-10-14 09:51:27'),
    ]);

    $matchingEarning = RetailerEarning::query()->create([
        'user_id' => $retailer->id, 'shop_id' => $shop->id, 'amount' => 135.86,
        'method' => 'commission_from_recurring_payments', 'transaction_type' => 'Add',
        'created_at' => Carbon::parse('2026-10-14 09:51:27'),
    ]);
    $septemberEarning = RetailerEarning::query()->create([
        'user_id' => $retailer->id, 'shop_id' => $shop->id, 'amount' => 220.03,
        'method' => 'commission_from_recurring_payments', 'transaction_type' => 'Add',
        'created_at' => Carbon::parse('2026-09-02 18:30:20'),
    ]);

    fakeElavonClient(fakeElavonTransaction(TransactionState::SETTLED, id: 'shopshopshopshopshopshop02'), function ($client): void {
        $client->shouldReceive('createRefundTransaction')->andReturn(fakeElavonTransaction(TransactionState::CAPTURED, id: 'refundrefundrefundrefund03'));
    });

    $refund = app(ChargeRefundService::class)->refund(
        refundLedgerRow(UnifiedChargeLedger::SOURCE_SHOP.'-'.$october->id),
        'Customer cancelled',
    );

    $reversal = RetailerEarning::query()->where('transaction_type', 'Remove')->sole();

    expect($reversal->comments)->toStartWith(ChargeRefundService::COMMISSION_REVERSAL_PREFIX.$matchingEarning->id.' ')
        ->and((float) $reversal->amount)->toBe(135.86)
        ->and($reversal->user_id)->toBe($retailer->id)
        ->and($refund->rollback['commissions_reversed'])->toHaveCount(1)
        ->and($refund->rollback['commissions_reversed'][0]['earning_id'])->toBe($matchingEarning->id)
        ->and(RetailerEarning::query()->whereKey($septemberEarning->id)->exists())->toBeTrue()
        ->and(Carbon::parse($shop->fresh()->paid_at)->toDateTimeString())->toBe($september->created_at->toDateTimeString());
});

it('only offers refunds for paid Elavon charges', function (): void {
    ['october' => $october] = createRefundablePluginCharges();
    $service = app(ChargeRefundService::class);
    $row = refundLedgerRow(UnifiedChargeLedger::SOURCE_SUBSCRIPTION.'-'.$october->id);

    expect($service->blockedReason($row))->toBeNull()
        ->and($service->blockedReason(array_merge($row, ['status' => false])))->toBe('Only paid charges can be refunded.')
        ->and($service->blockedReason(array_replace_recursive($row, ['extras' => ['elavon_transaction_id' => null]])))->toBe('Only Elavon charges can be refunded here.')
        ->and($service->blockedReason(array_merge($row, ['id' => UnifiedChargeLedger::SOURCE_EXTERNAL_SUBSCRIPTION.'-1'])))
        ->toBe('Only shop, plugin, and enterprise charges can be refunded here.');
});

it('refunds from the row action with a reason', function (): void {
    $admin = User::factory()->create(['role_id' => User::ROLES['Admin'], 'service_type' => 'both', 'pt_free_tier' => false]);
    $admin->assignRole('admin');
    ['october' => $october] = createRefundablePluginCharges();
    $ledgerKey = UnifiedChargeLedger::SOURCE_SUBSCRIPTION.'-'.$october->id;

    fakeElavonClient(fakeElavonTransaction(TransactionState::SETTLED, id: REFUND_TEST_TX_OCTOBER), function ($client): void {
        $client->shouldReceive('createRefundTransaction')->once()->andReturn(fakeElavonTransaction(TransactionState::CAPTURED, id: 'refundrefundrefundrefund04'));
    });

    Livewire::actingAs($admin)
        ->test(AllChargesPage::class)
        ->call('loadTable')
        ->assertActionVisible(TestAction::make('refund')->table($ledgerKey))
        ->callAction(TestAction::make('refund')->table($ledgerKey), ['reason' => 'Admin refund'])
        ->assertHasNoFormErrors()
        ->assertNotified('Charge refunded');

    expect(ChargeRefund::query()->where('ledger_key', $ledgerKey)->value('reason'))->toBe('Admin refund');
});

it('requires a reason to refund from the row action', function (): void {
    $admin = User::factory()->create(['role_id' => User::ROLES['Admin'], 'service_type' => 'both', 'pt_free_tier' => false]);
    $admin->assignRole('admin');
    ['october' => $october] = createRefundablePluginCharges();

    Livewire::actingAs($admin)
        ->test(AllChargesPage::class)
        ->call('loadTable')
        ->callAction(TestAction::make('refund')->table(UnifiedChargeLedger::SOURCE_SUBSCRIPTION.'-'.$october->id), ['reason' => ''])
        ->assertHasFormErrors(['reason' => 'required']);

    expect(ChargeRefund::query()->count())->toBe(0);
});
