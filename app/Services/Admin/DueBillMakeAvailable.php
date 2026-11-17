<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Enterprise;
use App\Models\PaymentMethodAccess;
use App\Models\Shop;
use App\Services\Subscription\MonthlyBillingPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class DueBillMakeAvailable
{
    /**
     * Apply reversible billing/status fixes so the entity can appear on the due list.
     *
     * @return array{
     *     success: bool,
     *     on_due_list: bool,
     *     actions: list<string>,
     *     remaining_reasons: list<string>,
     *     message: string
     * }
     */
    public function makeAvailable(
        string $type,
        int $entityId,
        ?MonthlyBillingPeriod $billingPeriod = null,
    ): array {
        $billingPeriod ??= MonthlyBillingPeriod::resolve();

        try {
            return DB::transaction(function () use ($type, $entityId, $billingPeriod): array {
                return match ($type) {
                    UnifiedDueLedger::TYPE_SHOP => $this->makeShopAvailable($entityId, $billingPeriod),
                    UnifiedDueLedger::TYPE_PLUGIN => $this->makePluginAvailable($entityId, $billingPeriod),
                    UnifiedDueLedger::TYPE_ENTERPRISE => $this->makeEnterpriseAvailable($entityId, $billingPeriod),
                    default => $this->failure(__('Unknown entity type.')),
                };
            });
        } catch (Throwable $exception) {
            report($exception);

            return $this->failure(__('Could not make this account available.'));
        }
    }

    /**
     * Exclude a due entity from this billing month by marking it paid through the period.
     *
     * @return array{
     *     success: bool,
     *     on_due_list: bool,
     *     actions: list<string>,
     *     remaining_reasons: list<string>,
     *     message: string
     * }
     */
    public function makeUnavailable(
        string $type,
        int $entityId,
        ?MonthlyBillingPeriod $billingPeriod = null,
    ): array {
        $billingPeriod ??= MonthlyBillingPeriod::resolve();

        try {
            return DB::transaction(function () use ($type, $entityId, $billingPeriod): array {
                return match ($type) {
                    UnifiedDueLedger::TYPE_SHOP => $this->makeShopUnavailable($entityId, $billingPeriod),
                    UnifiedDueLedger::TYPE_PLUGIN => $this->makePluginUnavailable($entityId, $billingPeriod),
                    UnifiedDueLedger::TYPE_ENTERPRISE => $this->makeEnterpriseUnavailable($entityId, $billingPeriod),
                    default => $this->failure(__('Unknown entity type.')),
                };
            });
        } catch (Throwable $exception) {
            report($exception);

            return $this->failure(__('Could not make this account unavailable.'));
        }
    }

    /**
     * @return array{success: bool, on_due_list: bool, actions: list<string>, remaining_reasons: list<string>, message: string}
     */
    protected function makeShopAvailable(int $shopId, MonthlyBillingPeriod $billingPeriod): array
    {
        $shop = Shop::query()->with(['user', 'users', 'metas'])->find($shopId);
        if ($shop === null) {
            return $this->failure(__('Shop not found.'));
        }

        $actions = [];
        $paidThrough = $billingPeriod->paidThrough();

        if ((int) ($shop->getAttributes()['status'] ?? 0) !== 1) {
            $shop->status = 1;
            $actions[] = __('Activated shop.');
        }

        if ($shop->paid_at !== null
            && $shop->paid_at->toDateTimeString() >= $paidThrough->toDateTimeString()) {
            $shop->paid_at = $this->previousPaidAt($billingPeriod);
            $actions[] = __('Cleared this month’s paid marker.');
        }

        if ($shop->requiresElavonResubscription() && filled($shop->subscription_id)) {
            $shop->subscriptionMethod = Shop::SUBSCRIPTION_METHOD_ELAVON;
            $actions[] = __('Set subscription method to Elavon.');
        }

        // Prefer app-managed monthly:charge when a stored card already exists.
        if (filled($shop->elavon_subscription_id) && filled($shop->subscription_id)) {
            $shop->elavon_subscription_id = null;
            $actions[] = __('Switched from Elavon-native to app-managed billing.');
        }

        if ($actions !== []) {
            $shop->save();
        }

        return $this->resultForRow(
            type: UnifiedDueLedger::TYPE_SHOP,
            entityId: $shop->id,
            billingPeriod: $billingPeriod,
            actions: $actions,
        );
    }

    /**
     * @return array{success: bool, on_due_list: bool, actions: list<string>, remaining_reasons: list<string>, message: string}
     */
    protected function makePluginAvailable(int $accessId, MonthlyBillingPeriod $billingPeriod): array
    {
        $access = PaymentMethodAccess::query()->with(['user', 'subscription'])->find($accessId);
        if ($access === null) {
            return $this->failure(__('Plugin not found.'));
        }

        $actions = [];
        $paidThrough = $billingPeriod->paidThrough();

        if ((int) ($access->getAttributes()['status'] ?? 0) !== 1) {
            $access->status = 1;
            $actions[] = __('Activated plugin.');
        }

        if ($access->last_paid_at !== null
            && $access->last_paid_at->toDateTimeString() >= $paidThrough->toDateTimeString()) {
            $access->last_paid_at = $this->previousPaidAt($billingPeriod);
            $actions[] = __('Cleared this month’s paid marker.');
        }

        if ($access->trial_ends_at !== null && $access->trial_charged_at === null) {
            $access->trial_charged_at = now();
            $actions[] = __('Settled free trial.');
        }

        if ($actions !== []) {
            $access->save();
        }

        if ($access->subscription === null) {
            $access->subscription()->create([
                'fee' => (int) round($access->baseMonthlyFee()),
                'status' => 1,
            ]);
            $access->load('subscription');
            $actions[] = __('Created plugin subscription row.');
        } elseif ((int) $access->subscription->status !== 1) {
            $access->subscription->status = 1;
            $access->subscription->save();
            $actions[] = __('Activated plugin subscription.');
        }

        return $this->resultForRow(
            type: UnifiedDueLedger::TYPE_PLUGIN,
            entityId: $access->id,
            billingPeriod: $billingPeriod,
            actions: $actions,
        );
    }

    /**
     * @return array{success: bool, on_due_list: bool, actions: list<string>, remaining_reasons: list<string>, message: string}
     */
    protected function makeEnterpriseAvailable(int $enterpriseId, MonthlyBillingPeriod $billingPeriod): array
    {
        $enterprise = Enterprise::query()->with('subscription')->find($enterpriseId);
        if ($enterprise === null) {
            return $this->failure(__('Enterprise not found.'));
        }

        $actions = [];
        $paidThrough = $billingPeriod->paidThrough();

        if ((int) ($enterprise->getAttributes()['status'] ?? 0) !== 1) {
            $enterprise->status = 1;
            $actions[] = __('Activated enterprise.');
        }

        if (filled($enterprise->paid_at)
            && Carbon::parse($enterprise->paid_at)->toDateTimeString() >= $paidThrough->toDateTimeString()) {
            $enterprise->paid_at = $this->previousPaidAt($billingPeriod)->toDateTimeString();
            $actions[] = __('Cleared this month’s paid marker.');
        }

        if ($actions !== []) {
            $enterprise->save();
        }

        if ($enterprise->subscription === null) {
            $fee = filled($enterprise->subscription_fee) && is_numeric($enterprise->subscription_fee)
                ? (float) $enterprise->subscription_fee
                : 299.0;

            $enterprise->subscription()->create([
                'fee' => (int) round($fee),
                'status' => 1,
            ]);
            $enterprise->load('subscription');
            $actions[] = __('Created enterprise subscription row.');
        } elseif ((int) $enterprise->subscription->status !== 1) {
            $enterprise->subscription->status = 1;
            $enterprise->subscription->save();
            $actions[] = __('Activated enterprise subscription.');
        }

        return $this->resultForRow(
            type: UnifiedDueLedger::TYPE_ENTERPRISE,
            entityId: $enterprise->id,
            billingPeriod: $billingPeriod,
            actions: $actions,
        );
    }

    /**
     * @return array{success: bool, on_due_list: bool, actions: list<string>, remaining_reasons: list<string>, message: string}
     */
    protected function makeShopUnavailable(int $shopId, MonthlyBillingPeriod $billingPeriod): array
    {
        $shop = Shop::query()->find($shopId);
        if ($shop === null) {
            return $this->failure(__('Shop not found.'));
        }

        $shop->paid_at = $billingPeriod->paidThrough();
        $shop->save();

        return $this->resultForUnavailable(
            type: UnifiedDueLedger::TYPE_SHOP,
            entityId: $shop->id,
            billingPeriod: $billingPeriod,
            actions: [__('Marked shop as paid for this billing month.')],
        );
    }

    /**
     * @return array{success: bool, on_due_list: bool, actions: list<string>, remaining_reasons: list<string>, message: string}
     */
    protected function makePluginUnavailable(int $accessId, MonthlyBillingPeriod $billingPeriod): array
    {
        $access = PaymentMethodAccess::query()->find($accessId);
        if ($access === null) {
            return $this->failure(__('Plugin not found.'));
        }

        $access->last_paid_at = $billingPeriod->paidThrough();
        $access->save();

        return $this->resultForUnavailable(
            type: UnifiedDueLedger::TYPE_PLUGIN,
            entityId: $access->id,
            billingPeriod: $billingPeriod,
            actions: [__('Marked plugin as paid for this billing month.')],
        );
    }

    /**
     * @return array{success: bool, on_due_list: bool, actions: list<string>, remaining_reasons: list<string>, message: string}
     */
    protected function makeEnterpriseUnavailable(int $enterpriseId, MonthlyBillingPeriod $billingPeriod): array
    {
        $enterprise = Enterprise::query()->find($enterpriseId);
        if ($enterprise === null) {
            return $this->failure(__('Enterprise not found.'));
        }

        $enterprise->paid_at = $billingPeriod->paidThrough()->toDateTimeString();
        $enterprise->save();

        return $this->resultForUnavailable(
            type: UnifiedDueLedger::TYPE_ENTERPRISE,
            entityId: $enterprise->id,
            billingPeriod: $billingPeriod,
            actions: [__('Marked enterprise as paid for this billing month.')],
        );
    }

    protected function previousPaidAt(MonthlyBillingPeriod $billingPeriod): Carbon
    {
        return $billingPeriod->start->copy()->subDay()->endOfDay();
    }

    /**
     * @param  list<string>  $actions
     * @return array{success: bool, on_due_list: bool, actions: list<string>, remaining_reasons: list<string>, message: string}
     */
    protected function resultForRow(
        string $type,
        int $entityId,
        MonthlyBillingPeriod $billingPeriod,
        array $actions,
    ): array {
        $row = app(UnifiedDueLedger::class)
            ->collect($billingPeriod, scope: UnifiedDueLedger::SCOPE_DUE)
            ->firstWhere('id', $type.'-'.$entityId);

        if ($row !== null) {
            return [
                'success' => true,
                'on_due_list' => true,
                'actions' => $actions,
                'remaining_reasons' => [],
                'message' => $actions === []
                    ? __('Already available on the due list.')
                    : __('Moved to the due list.'),
            ];
        }

        $unavailable = app(UnifiedDueLedger::class)
            ->collect($billingPeriod, scope: UnifiedDueLedger::SCOPE_UNAVAILABLE)
            ->firstWhere('id', $type.'-'.$entityId);

        $remaining = is_array($unavailable)
            ? array_values($unavailable['reasons'] ?? [])
            : [__('Still not eligible for the due list.')];

        if ($actions === []) {
            return [
                'success' => false,
                'on_due_list' => false,
                'actions' => [],
                'remaining_reasons' => $remaining,
                'message' => __('Nothing could be changed automatically.'),
            ];
        }

        return [
            'success' => false,
            'on_due_list' => false,
            'actions' => $actions,
            'remaining_reasons' => $remaining,
            'message' => __('Updated, but still unavailable.'),
        ];
    }

    /**
     * @param  list<string>  $actions
     * @return array{success: bool, on_due_list: bool, actions: list<string>, remaining_reasons: list<string>, message: string}
     */
    protected function resultForUnavailable(
        string $type,
        int $entityId,
        MonthlyBillingPeriod $billingPeriod,
        array $actions,
    ): array {
        $due = app(UnifiedDueLedger::class)
            ->collect($billingPeriod, scope: UnifiedDueLedger::SCOPE_DUE)
            ->firstWhere('id', $type.'-'.$entityId);

        if ($due === null) {
            return [
                'success' => true,
                'on_due_list' => false,
                'actions' => $actions,
                'remaining_reasons' => [],
                'message' => __('Moved to the unavailable list.'),
            ];
        }

        return [
            'success' => false,
            'on_due_list' => true,
            'actions' => $actions,
            'remaining_reasons' => array_values($due['reasons'] ?? []),
            'message' => __('Updated, but still on the due list.'),
        ];
    }

    /**
     * @return array{success: bool, on_due_list: bool, actions: list<string>, remaining_reasons: list<string>, message: string}
     */
    protected function failure(string $message): array
    {
        return [
            'success' => false,
            'on_due_list' => false,
            'actions' => [],
            'remaining_reasons' => [$message],
            'message' => $message,
        ];
    }
}
