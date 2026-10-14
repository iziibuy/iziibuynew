<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Exceptions\ChargeRefundException;
use App\Models\Charge;
use App\Models\ChargeRefund;
use App\Models\Enterprise;
use App\Models\PaymentMethodAccess;
use App\Models\RetailerEarning;
use App\Models\SubscriptionCharge;
use App\Models\User;
use App\Services\Elavon\ElavonRecurringTransaction;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fully refunds an Elavon platform charge and rolls back what the charge changed.
 *
 * Rollback: the account's paid date returns to its previous successful charge (so the next
 * monthly run charges it again) and, for shops, the retailer commission paid for the charge is reversed.
 */
class ChargeRefundService
{
    public const COMMISSION_MATCH_WINDOW_MINUTES = 5;

    public const COMMISSION_REVERSAL_PREFIX = 'refund-reversal:';

    /**
     * @var list<string>
     */
    protected const COMMISSION_METHODS = ['commission_from_recurring_payments', 'one_time_pay_out'];

    public function __construct(protected ChargeElavonGateway $gateway) {}

    /**
     * Why a ledger row cannot be refunded, or null when it can. Uses only the row data.
     *
     * @param  array<string, mixed>  $record
     */
    public function blockedReason(array $record): ?string
    {
        [$source] = UnifiedChargeLedger::parseKey((string) ($record['id'] ?? ''));

        return match (true) {
            ! in_array($source, [UnifiedChargeLedger::SOURCE_SHOP, UnifiedChargeLedger::SOURCE_SUBSCRIPTION], true) => __('Only shop, plugin, and enterprise charges can be refunded here.'),
            (bool) ($record['refunded'] ?? false) => __('This charge has already been refunded.'),
            ! (bool) ($record['status'] ?? false) => __('Only paid charges can be refunded.'),
            (float) ($record['amount'] ?? 0) <= 0 => __('There is nothing to refund on this charge.'),
            blank(data_get($record, 'extras.elavon_transaction_id')) => __('Only Elavon charges can be refunded here.'),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $record
     *
     * @throws ChargeRefundException
     */
    public function refund(array $record, string $reason, ?User $refundedBy = null): ChargeRefund
    {
        $ledgerKey = (string) ($record['id'] ?? '');
        $lock = Cache::lock('charge-refund:'.$ledgerKey, 120);

        if (! $lock->get()) {
            throw new ChargeRefundException(__('A refund for this charge is already in progress.'));
        }

        try {
            return $this->refundWhileLocked($record, $ledgerKey, $reason, $refundedBy);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<string, mixed>  $record
     */
    protected function refundWhileLocked(array $record, string $ledgerKey, string $reason, ?User $refundedBy): ChargeRefund
    {
        if (($blocked = $this->blockedReason($record)) !== null) {
            throw new ChargeRefundException($blocked);
        }

        if (ChargeRefund::query()->where('ledger_key', $ledgerKey)->exists()) {
            throw new ChargeRefundException(__('This charge has already been refunded.'));
        }

        $charge = $this->resolveCharge($ledgerKey);
        $transactionId = $charge !== null ? $this->gateway->transactionId($charge) : null;
        $client = $charge !== null ? $this->gateway->client($charge) : null;

        if ($charge === null || $transactionId === null || $client === null) {
            throw new ChargeRefundException(__('This charge is not linked to a refundable Elavon transaction.'));
        }

        $amount = round((float) $charge->amount, 2);

        $transaction = $client->getTransaction($transactionId);
        if (! $transaction->isSuccess()) {
            throw new ChargeRefundException(__('Could not load the Elavon transaction: :message', [
                'message' => ElavonRecurringTransaction::extractFailureMessage($transaction) ?: __('unknown error'),
            ]));
        }

        if ((float) ($transaction->getTotalRefundedAmount() ?? 0) > 0) {
            throw new ChargeRefundException(__('Elavon already shows a refund on this transaction. Check it in Elavon before continuing.'));
        }

        $state = $transaction->getState();
        $parentTransaction = (string) $transaction->getHref();

        $operation = match (true) {
            $state?->isVoidable() === true => ChargeRefund::OPERATION_VOID,
            $state?->isRefundable() === true => ChargeRefund::OPERATION_REFUND,
            default => throw new ChargeRefundException(__('The Elavon transaction is :state and cannot be refunded.', [
                'state' => $state !== null ? (string) $state->getValue() : __('in an unknown state'),
            ])),
        };

        $response = $operation === ChargeRefund::OPERATION_VOID
            ? $client->createVoidTransaction(['type' => 'void', 'parentTransaction' => $parentTransaction])
            : $client->createRefundTransaction([
                'type' => 'refund',
                'parentTransaction' => $parentTransaction,
                'total' => ['amount' => $amount, 'currencyCode' => 'NOK'],
            ]);

        $responseState = $response->isSuccess() ? $response->getState() : null;

        if (! $response->isSuccess() || $responseState?->isDeclined() || $responseState?->isRejected()) {
            throw new ChargeRefundException(__('Elavon did not approve the :operation: :message', [
                'operation' => $operation,
                'message' => ElavonRecurringTransaction::extractFailureMessage($response) ?: __('declined'),
            ]));
        }

        $providerTransactionId = (string) $response->getId();

        try {
            return DB::transaction(fn (): ChargeRefund => ChargeRefund::query()->create([
                'ledger_key' => $ledgerKey,
                'refundable_type' => $charge::class,
                'refundable_id' => $charge->getKey(),
                'amount' => $amount,
                'currency' => 'NOK',
                'provider' => 'elavon',
                'provider_operation' => $operation,
                'provider_transaction_id' => $providerTransactionId,
                'original_transaction_id' => $transactionId,
                'reason' => $reason,
                'refunded_by' => $refundedBy?->getKey(),
                'rollback' => $this->rollBack($charge),
            ]));
        } catch (Throwable $exception) {
            Log::critical('Charge refunded at Elavon but the rollback could not be saved', [
                'ledger_key' => $ledgerKey,
                'operation' => $operation,
                'elavon_transaction_id' => $providerTransactionId,
                'error' => $exception->getMessage(),
            ]);

            throw new ChargeRefundException(__('Elavon refunded the charge (transaction :id), but saving the rollback failed. Fix the account manually.', [
                'id' => $providerTransactionId,
            ]), previous: $exception);
        }
    }

    protected function resolveCharge(string $ledgerKey): ?Model
    {
        [$source, $id] = UnifiedChargeLedger::parseKey($ledgerKey);

        return match ($source) {
            UnifiedChargeLedger::SOURCE_SHOP => Charge::query()->with('shop')->find($id),
            UnifiedChargeLedger::SOURCE_SUBSCRIPTION => SubscriptionCharge::query()->with('subscription.subscribable')->find($id),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function rollBack(Model $charge): array
    {
        if ($charge instanceof Charge) {
            $shop = $charge->shop;
            $previous = $this->previousSuccessfulCharge(Charge::query()->where('shop_id', $charge->shop_id), $charge);

            return [
                'account' => ['type' => 'Shop', 'id' => $shop->getKey()],
                'paid_date' => $this->revertPaidDate($charge, $previous, [[$shop, 'paid_at']]),
                'commissions_reversed' => $this->reverseCommissions($charge),
            ];
        }

        /** @var SubscriptionCharge $charge */
        $subscription = $charge->subscription;
        $subscribable = $subscription->subscribable;
        $previous = $this->previousSuccessfulCharge(SubscriptionCharge::query()->where('subscription_id', $charge->subscription_id), $charge);

        $paidDateFields = match (true) {
            $subscribable instanceof PaymentMethodAccess => [[$subscribable, 'last_paid_at'], [$subscription, 'paid_at']],
            $subscribable instanceof Enterprise => [[$subscribable, 'paid_at'], [$subscription, 'paid_at']],
            default => [],
        };

        return [
            'account' => ['type' => class_basename($subscribable), 'id' => $subscribable?->getKey()],
            'paid_date' => $this->revertPaidDate($charge, $previous, $paidDateFields),
            'commissions_reversed' => [],
        ];
    }

    /**
     * Latest earlier paid charge of the same account that has not been refunded.
     *
     * @param  Builder<Model>  $query
     */
    protected function previousSuccessfulCharge(Builder $query, Model $charge): ?Model
    {
        return $query
            ->whereKeyNot($charge->getKey())
            ->where('status', 1)
            ->where('amount', '>', 0)
            ->where('created_at', '<=', $charge->created_at)
            ->whereNotIn('id', $this->refundedIds($charge::class))
            ->latest('created_at')
            ->latest('id')
            ->first();
    }

    /**
     * @param  list<array{0: Model, 1: string}>  $fields
     * @return array<string, mixed>
     */
    protected function revertPaidDate(Model $charge, ?Model $previous, array $fields): array
    {
        $laterChargeExists = $charge->newQuery()
            ->where($charge instanceof Charge ? 'shop_id' : 'subscription_id', $charge instanceof Charge ? $charge->shop_id : $charge->subscription_id)
            ->whereKeyNot($charge->getKey())
            ->where('status', 1)
            ->where('amount', '>', 0)
            ->where('created_at', '>', $charge->created_at)
            ->whereNotIn('id', $this->refundedIds($charge::class))
            ->exists();

        if ($laterChargeExists) {
            return ['reverted' => false, 'note' => __('A later charge exists, so the paid date was left unchanged.')];
        }

        $revertTo = $previous?->created_at;
        $changes = [];

        foreach ($fields as [$model, $attribute]) {
            $from = $model->{$attribute};
            $model->{$attribute} = $revertTo?->toDateTimeString();
            $model->save();

            $changes[] = [
                'model' => class_basename($model),
                'id' => $model->getKey(),
                'attribute' => $attribute,
                'from' => $from instanceof CarbonInterface ? $from->toDateTimeString() : $from,
                'to' => $revertTo?->toDateTimeString(),
            ];
        }

        return ['reverted' => true, 'previous_charge_id' => $previous?->getKey(), 'changes' => $changes];
    }

    /**
     * Add a "Remove" entry for each retailer earning paid out at the time of the shop charge.
     *
     * @return list<array<string, mixed>>
     */
    protected function reverseCommissions(Charge $charge): array
    {
        $alreadyReversed = RetailerEarning::query()
            ->where('shop_id', $charge->shop_id)
            ->where('transaction_type', 'Remove')
            ->where('comments', 'like', self::COMMISSION_REVERSAL_PREFIX.'%')
            ->pluck('comments')
            ->map(fn (string $comment): int => (int) substr($comment, strlen(self::COMMISSION_REVERSAL_PREFIX)))
            ->all();

        return RetailerEarning::query()
            ->where('shop_id', $charge->shop_id)
            ->where('transaction_type', 'Add')
            ->whereIn('method', self::COMMISSION_METHODS)
            ->whereBetween('created_at', [
                $charge->created_at->copy()->subMinutes(self::COMMISSION_MATCH_WINDOW_MINUTES),
                $charge->created_at->copy()->addMinutes(self::COMMISSION_MATCH_WINDOW_MINUTES),
            ])
            ->whereNotIn('id', $alreadyReversed)
            ->get()
            ->map(function (RetailerEarning $earning) use ($charge): array {
                $reversal = RetailerEarning::query()->create([
                    'user_id' => $earning->user_id,
                    'shop_id' => $earning->shop_id,
                    'amount' => $earning->amount,
                    'method' => $earning->method,
                    'transaction_type' => 'Remove',
                    'comments' => self::COMMISSION_REVERSAL_PREFIX.$earning->id.' — '.__('reversed because shop charge #:id was refunded.', ['id' => $charge->id]),
                ]);

                return [
                    'earning_id' => $earning->id,
                    'reversal_id' => $reversal->id,
                    'user_id' => $earning->user_id,
                    'method' => $earning->method,
                    'amount' => $earning->amount,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  class-string<Model>  $chargeClass
     * @return list<int>
     */
    protected function refundedIds(string $chargeClass): array
    {
        return ChargeRefund::query()
            ->where('refundable_type', $chargeClass)
            ->pluck('refundable_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }
}
