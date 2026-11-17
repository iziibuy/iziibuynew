<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\PaymentMethodAccess;
use App\Models\Shop;
use App\Models\User;
use App\Services\Subscription\MonthlyBillingPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DueBillOwnerInspector
{
    public const STATUS_DUE = 'due';

    public const STATUS_NOT_DUE = 'not_due';

    public const STATUS_MISSING = 'missing';

    /**
     * Explain whether this owner's shop/plugin accounts are on the due list, and why not if they aren't.
     *
     * @return array{
     *     user_id: int,
     *     user: array{id: int, name: string, email: string}|null,
     *     billing_period: string,
     *     entities: list<array<string, mixed>>
     * }
     */
    public function inspect(int $userId, ?MonthlyBillingPeriod $billingPeriod = null): array
    {
        $billingPeriod ??= MonthlyBillingPeriod::resolve();
        $user = User::query()->find($userId);

        $entities = collect()
            ->concat($this->inspectShops($userId, $billingPeriod))
            ->concat($this->inspectPlugins($userId, $billingPeriod))
            ->values()
            ->all();

        if ($entities === []) {
            $entities[] = [
                'type' => null,
                'type_label' => __('Account'),
                'status' => self::STATUS_MISSING,
                'status_label' => __('Not found'),
                'on_due_list' => false,
                'company' => null,
                'entity_id' => null,
                'due_amount' => null,
                'reasons' => [
                    __('No shop or plugin is owned by user #:id.', ['id' => $userId]),
                ],
            ];
        }

        return [
            'user_id' => $userId,
            'user' => $user === null ? null : [
                'id' => $user->id,
                'name' => trim($user->name.' '.($user->last_name ?? '')),
                'email' => (string) $user->email,
            ],
            'billing_period' => $billingPeriod->label(),
            'entities' => $entities,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function inspectShops(int $userId, MonthlyBillingPeriod $billingPeriod): Collection
    {
        $paidThrough = $billingPeriod->paidThrough();

        return Shop::query()
            ->with(['user', 'users', 'metas'])
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get()
            ->map(function (Shop $shop) use ($billingPeriod, $paidThrough): array {
                $reasons = [];
                $rawStatus = (int) ($shop->getAttributes()['status'] ?? 0);

                if ($rawStatus !== 1) {
                    $reasons[] = __('Shop status is inactive (status=:status). Monthly charge only includes active shops.', [
                        'status' => $rawStatus,
                    ]);
                }

                // Match monthly:charge SQL: paid_at < paidThrough()->toDateTimeString()
                if ($shop->paid_at !== null
                    && $shop->paid_at->toDateTimeString() >= $paidThrough->toDateTimeString()) {
                    $reasons[] = __('Already paid for this billing month (paid_at=:paid_at, paid through=:paid_through).', [
                        'paid_at' => $shop->paid_at->toDateTimeString(),
                        'paid_through' => $paidThrough->toDateTimeString(),
                    ]);
                }

                if ($shop->requiresElavonResubscription()) {
                    $reasons[] = __('Needs Elavon resubscription (subscriptionMethod=:method). Cron deactivates these instead of charging.', [
                        'method' => (string) ($shop->getAttributes()['subscriptionMethod'] ?? ''),
                    ]);
                }

                if ($shop->usesElavonNativeSubscription()) {
                    $reasons[] = __('Uses Elavon-native subscription (elavon_subscription_id set). Billed by Elavon, not monthly:charge.');
                }

                $onDueList = $reasons === [];

                return [
                    'type' => UnifiedDueLedger::TYPE_SHOP,
                    'type_label' => UnifiedDueLedger::typeOptions()[UnifiedDueLedger::TYPE_SHOP],
                    'status' => $onDueList ? self::STATUS_DUE : self::STATUS_NOT_DUE,
                    'status_label' => $onDueList ? __('On due list') : __('Not billed this run'),
                    'on_due_list' => $onDueList,
                    'company' => (string) ($shop->company_name ?: $shop->name ?: $shop->user_name ?: 'Shop #'.$shop->id),
                    'entity_id' => $shop->id,
                    'due_amount' => $onDueList ? (float) $shop->subscriptionFeeFull() : null,
                    'last_paid_at' => $shop->paid_at?->toDateTimeString(),
                    'reasons' => $onDueList
                        ? [__('This shop is on the due list for :period.', ['period' => $billingPeriod->label()])]
                        : $reasons,
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function inspectPlugins(int $userId, MonthlyBillingPeriod $billingPeriod): Collection
    {
        $paidThrough = $billingPeriod->paidThrough();

        return PaymentMethodAccess::query()
            ->with(['user', 'subscription'])
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get()
            ->map(function (PaymentMethodAccess $access) use ($billingPeriod, $paidThrough): array {
                $reasons = [];
                $rawStatus = (int) ($access->getAttributes()['status'] ?? 0);

                if ($rawStatus !== 1) {
                    $reasons[] = __('Plugin status is inactive (status=:status). Monthly charge only includes active plugins.', [
                        'status' => $rawStatus,
                    ]);
                }

                // Match payment-method-access:charge SQL: last_paid_at < paidThrough()->toDateTimeString()
                if ($access->last_paid_at !== null
                    && $access->last_paid_at->toDateTimeString() >= $paidThrough->toDateTimeString()) {
                    $reasons[] = __('Already paid for this billing month (last_paid_at=:paid_at, paid through=:paid_through).', [
                        'paid_at' => $access->last_paid_at->toDateTimeString(),
                        'paid_through' => $paidThrough->toDateTimeString(),
                    ]);
                }

                // Matches due query: trial_ends_at set AND trial_charged_at null → excluded.
                if ($access->trial_ends_at !== null && $access->trial_charged_at === null) {
                    $reasons[] = __('Free trial is unsettled (trial_ends_at=:ends_at). Excluded until the trial is charged/settled.', [
                        'ends_at' => $access->trial_ends_at instanceof Carbon
                            ? $access->trial_ends_at->toDateTimeString()
                            : (string) $access->trial_ends_at,
                    ]);
                }

                if ($access->subscription === null) {
                    $reasons[] = __('Missing subscription row. Cron skips plugins without an active subscription.');
                } elseif ((int) $access->subscription->status !== 1) {
                    $reasons[] = __('Subscription status is inactive (status=:status). Cron only charges when subscription.status=1.', [
                        'status' => (int) $access->subscription->status,
                    ]);
                }

                $onDueList = $reasons === [];

                return [
                    'type' => UnifiedDueLedger::TYPE_PLUGIN,
                    'type_label' => UnifiedDueLedger::typeOptions()[UnifiedDueLedger::TYPE_PLUGIN],
                    'status' => $onDueList ? self::STATUS_DUE : self::STATUS_NOT_DUE,
                    'status_label' => $onDueList ? __('On due list') : __('Not billed this run'),
                    'on_due_list' => $onDueList,
                    'company' => (string) ($access->company_name ?: 'Plugin #'.$access->id),
                    'entity_id' => $access->id,
                    'due_amount' => $onDueList ? $access->monthlyRenewalFee() : null,
                    'last_paid_at' => $access->last_paid_at?->toDateTimeString(),
                    'reasons' => $onDueList
                        ? [__('This plugin is on the due list for :period.', ['period' => $billingPeriod->label()])]
                        : $reasons,
                ];
            });
    }
}
