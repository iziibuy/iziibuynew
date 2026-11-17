<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Filament\Resources\PaymentMethodAccesses\PaymentMethodAccessResource;
use App\Filament\Resources\Shops\ShopResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Enterprise;
use App\Models\PaymentMethodAccess;
use App\Models\Shop;
use App\Services\Subscription\MonthlyBillingPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class UnifiedDueLedger
{
    public const TYPE_SHOP = 'shop';

    public const TYPE_PLUGIN = 'plugin';

    public const TYPE_ENTERPRISE = 'enterprise';

    public const SCOPE_DUE = 'due';

    public const SCOPE_UNAVAILABLE = 'unavailable';

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_SHOP => __('Shop'),
            self::TYPE_PLUGIN => __('Plugin'),
            self::TYPE_ENTERPRISE => __('Subscription'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function scopeOptions(): array
    {
        return [
            self::SCOPE_DUE => __('Due'),
            self::SCOPE_UNAVAILABLE => __('Unavailable'),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function collect(
        ?MonthlyBillingPeriod $billingPeriod = null,
        ?array $filters = null,
        ?string $sortColumn = null,
        ?string $sortDirection = null,
        string $scope = self::SCOPE_DUE,
    ): Collection {
        $billingPeriod ??= MonthlyBillingPeriod::resolve();
        $filters ??= [];
        $scope = $scope === self::SCOPE_UNAVAILABLE ? self::SCOPE_UNAVAILABLE : self::SCOPE_DUE;

        $rows = $scope === self::SCOPE_UNAVAILABLE
            ? $this->unavailableRows($billingPeriod)
            : $this->dueRows($billingPeriod);

        $rows = $this->applyFilters($rows, $filters);

        $direction = strtolower((string) $sortDirection) === 'asc' ? 'asc' : 'desc';
        $column = filled($sortColumn) ? $sortColumn : 'company';

        $sorted = $direction === 'asc'
            ? $rows->sortBy($column, SORT_NATURAL)
            : $rows->sortByDesc($column, SORT_NATURAL);

        return $sorted->values();
    }

    /**
     * @return array{due: int, unavailable: int}
     */
    public function counts(?MonthlyBillingPeriod $billingPeriod = null, ?array $filters = null): array
    {
        $billingPeriod ??= MonthlyBillingPeriod::resolve();
        $filters ??= [];

        return [
            self::SCOPE_DUE => $this->applyFilters($this->dueRows($billingPeriod), $filters)->count(),
            self::SCOPE_UNAVAILABLE => $this->applyFilters($this->unavailableRows($billingPeriod), $filters)->count(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function dueRows(MonthlyBillingPeriod $billingPeriod): Collection
    {
        return collect()
            ->concat($this->shopCandidates($billingPeriod)->where('on_due_list', true)->values())
            ->concat($this->pluginCandidates($billingPeriod)->where('on_due_list', true)->values())
            ->concat($this->enterpriseCandidates($billingPeriod)->where('on_due_list', true)->values())
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function unavailableRows(MonthlyBillingPeriod $billingPeriod): Collection
    {
        return collect()
            ->concat($this->shopCandidates($billingPeriod)->where('on_due_list', false)->values())
            ->concat($this->pluginCandidates($billingPeriod)->where('on_due_list', false)->values())
            ->concat($this->enterpriseCandidates($billingPeriod)->where('on_due_list', false)->values())
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function shopCandidates(MonthlyBillingPeriod $billingPeriod): Collection
    {
        $paidThrough = $billingPeriod->paidThrough();

        return Shop::query()
            ->with(['user', 'users', 'metas'])
            ->where(fn (Builder $query) => $query
                ->where('status', 1)
                ->orWhereNotNull('paid_at')
                ->orWhereNotNull('subscription_id')
                ->orWhereNotNull('elavon_subscription_id'))
            ->orderBy('id')
            ->get()
            ->map(function (Shop $shop) use ($billingPeriod, $paidThrough): array {
                $reasons = [];
                $rawStatus = (int) ($shop->getAttributes()['status'] ?? 0);

                if ($rawStatus !== 1) {
                    $reasons[] = __('Inactive (status=:status).', ['status' => $rawStatus]);
                }

                if ($shop->paid_at !== null
                    && $shop->paid_at->toDateTimeString() >= $paidThrough->toDateTimeString()) {
                    $reasons[] = __('Already paid for this billing month.');
                }

                if ($shop->requiresElavonResubscription()) {
                    $reasons[] = __('Needs Elavon resubscription.');
                }

                if ($shop->usesElavonNativeSubscription()) {
                    $reasons[] = __('Elavon-native subscription (billed by Elavon).');
                }

                $onDueList = $reasons === [];
                $breakdown = $this->shopBreakdown($shop, $billingPeriod);
                $owner = $shop->user;

                return $this->row(
                    type: self::TYPE_SHOP,
                    id: $shop->id,
                    company: (string) ($shop->company_name ?: $shop->name ?: $shop->user_name ?: 'Shop #'.$shop->id),
                    name: trim((string) (($owner?->name ?? '').' '.($owner?->last_name ?? ''))) ?: (string) ($owner?->email ?? '-'),
                    email: (string) ($shop->contact_email ?: $owner?->email ?: '-'),
                    monthlyPrice: (float) ($shop->monthly_cost ?? 0),
                    dueAmount: (float) ($breakdown['total'] ?? 0),
                    lastPaidAt: $shop->paid_at?->toDateTimeString(),
                    billingPeriod: $billingPeriod->label(),
                    userId: $owner?->id,
                    onDueList: $onDueList,
                    reasons: $onDueList
                        ? [__('On the due list.')]
                        : $reasons,
                    breakdown: $breakdown,
                );
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function pluginCandidates(MonthlyBillingPeriod $billingPeriod): Collection
    {
        $paidThrough = $billingPeriod->paidThrough();

        return PaymentMethodAccess::query()
            ->with(['user', 'subscription'])
            ->where(fn (Builder $query) => $query
                ->where('status', 1)
                ->orWhereNotNull('last_paid_at')
                ->orWhereNotNull('trial_ends_at')
                ->orWhereHas('subscription'))
            ->orderBy('id')
            ->get()
            ->map(function (PaymentMethodAccess $access) use ($billingPeriod, $paidThrough): array {
                $reasons = [];
                $rawStatus = (int) ($access->getAttributes()['status'] ?? 0);

                if ($rawStatus !== 1) {
                    $reasons[] = __('Inactive (status=:status).', ['status' => $rawStatus]);
                }

                if ($access->last_paid_at !== null
                    && $access->last_paid_at->toDateTimeString() >= $paidThrough->toDateTimeString()) {
                    $reasons[] = __('Already paid for this billing month.');
                }

                if ($access->trial_ends_at !== null && $access->trial_charged_at === null) {
                    $reasons[] = __('Free trial unsettled.');
                }

                if ($access->subscription === null) {
                    $reasons[] = __('Missing subscription row.');
                } elseif ((int) $access->subscription->status !== 1) {
                    $reasons[] = __('Subscription inactive (status=:status).', [
                        'status' => (int) $access->subscription->status,
                    ]);
                }

                $onDueList = $reasons === [];
                $breakdown = $this->pluginBreakdown($access, $billingPeriod);
                $owner = $access->user;

                return $this->row(
                    type: self::TYPE_PLUGIN,
                    id: $access->id,
                    company: (string) ($access->company_name ?: 'Plugin #'.$access->id),
                    name: trim((string) (($owner?->name ?? '').' '.($owner?->last_name ?? ''))) ?: (string) ($owner?->email ?? '-'),
                    email: (string) ($access->company_email ?: $owner?->email ?: '-'),
                    monthlyPrice: $access->baseMonthlyFee(),
                    dueAmount: (float) ($breakdown['total'] ?? 0),
                    lastPaidAt: $access->last_paid_at?->toDateTimeString(),
                    billingPeriod: $billingPeriod->label(),
                    userId: $owner?->id,
                    onDueList: $onDueList,
                    reasons: $onDueList
                        ? [__('On the due list.')]
                        : $reasons,
                    breakdown: $breakdown,
                );
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function enterpriseCandidates(MonthlyBillingPeriod $billingPeriod): Collection
    {
        $paidThrough = $billingPeriod->paidThrough();

        return Enterprise::query()
            ->with('subscription')
            ->where(fn (Builder $query) => $query
                ->where('status', 1)
                ->orWhereNotNull('paid_at')
                ->orWhereNotNull('subscription_id'))
            ->orderBy('id')
            ->get()
            ->map(function (Enterprise $enterprise) use ($billingPeriod, $paidThrough): array {
                $reasons = [];
                $rawStatus = (int) ($enterprise->getAttributes()['status'] ?? 0);

                if ($rawStatus !== 1) {
                    $reasons[] = __('Inactive (status=:status).', ['status' => $rawStatus]);
                }

                if (filled($enterprise->paid_at)
                    && Carbon::parse($enterprise->paid_at)->toDateTimeString() >= $paidThrough->toDateTimeString()) {
                    $reasons[] = __('Already paid for this billing month.');
                }

                if (! filled($enterprise->subscription_id)) {
                    $reasons[] = __('Missing stored card (subscription_id).');
                }

                if ($enterprise->subscription === null) {
                    $reasons[] = __('Missing subscription row.');
                } elseif ((int) $enterprise->subscription->status !== 1) {
                    $reasons[] = __('Subscription inactive (status=:status).', [
                        'status' => (int) $enterprise->subscription->status,
                    ]);
                }

                $onDueList = $reasons === [];
                $breakdown = $this->enterpriseBreakdown($enterprise, $billingPeriod);
                $fee = (float) ($breakdown['total'] ?? 0);

                return $this->row(
                    type: self::TYPE_ENTERPRISE,
                    id: $enterprise->id,
                    company: (string) ($enterprise->enterprise_name ?: $enterprise->domain ?: 'Enterprise #'.$enterprise->id),
                    name: (string) ($enterprise->domain ?: '-'),
                    email: '-',
                    monthlyPrice: $fee,
                    dueAmount: $fee,
                    lastPaidAt: filled($enterprise->paid_at)
                        ? Carbon::parse($enterprise->paid_at)->toDateTimeString()
                        : null,
                    billingPeriod: $billingPeriod->label(),
                    userId: null,
                    onDueList: $onDueList,
                    reasons: $onDueList
                        ? [__('On the due list.')]
                        : $reasons,
                    breakdown: $breakdown,
                );
            })
            ->values();
    }

    /**
     * @return array{summary: string, period: string, lines: list<array{label: string, amount: float, note: ?string}>, total: float, currency: string}
     */
    protected function shopBreakdown(Shop $shop, MonthlyBillingPeriod $billingPeriod): array
    {
        $shop->loadMissing('users');

        $lines = [];
        $establishment = 0.0;
        if ((int) $shop->establishment === 0) {
            $establishment = (float) $shop->establishment_cost;
            $lines[] = [
                'label' => __('Establishment fee'),
                'amount' => $establishment,
                'note' => __('One-time opening fee'),
            ];
        }

        $monthly = (float) $shop->monthly_cost;
        $lines[] = [
            'label' => __('Monthly fee'),
            'amount' => $monthly,
            'note' => null,
        ];

        $managers = 0.0;
        if ($shop->users->count() > 1) {
            $managers = (float) $shop->singleUserCost();
            $lines[] = [
                'label' => __('Extra managers'),
                'amount' => $managers,
                'note' => __(':count users', ['count' => $shop->users->count()]),
            ];
        }

        $net = $establishment + $monthly + $managers;
        $taxRate = $shop->registrationTax();
        $tax = $net * ($taxRate / 100);
        if ($tax > 0) {
            $lines[] = [
                'label' => __('VAT (:rate%)', ['rate' => rtrim(rtrim(number_format($taxRate, 2), '0'), '.')]),
                'amount' => round($tax, 2),
                'note' => null,
            ];
        }

        $total = round($net + $tax, 2);

        return [
            'summary' => __('Shop renewal charge for :company', [
                'company' => (string) ($shop->company_name ?: $shop->user_name ?: 'Shop #'.$shop->id),
            ]),
            'period' => $billingPeriod->label(),
            'lines' => $lines,
            'total' => $total,
            'currency' => 'NOK',
        ];
    }

    /**
     * @return array{summary: string, period: string, lines: list<array{label: string, amount: float, note: ?string}>, total: float, currency: string}
     */
    protected function pluginBreakdown(PaymentMethodAccess $access, MonthlyBillingPeriod $billingPeriod): array
    {
        $base = $access->baseMonthlyFee();
        $vat = round($base * 0.25, 2);
        $total = round($base + $vat, 2);

        return [
            'summary' => __('Plugin renewal charge for :company', [
                'company' => (string) ($access->company_name ?: 'Plugin #'.$access->id),
            ]),
            'period' => $billingPeriod->label(),
            'lines' => [
                [
                    'label' => __('Monthly fee'),
                    'amount' => $base,
                    'note' => __('Excluding VAT'),
                ],
                [
                    'label' => __('VAT (25%)'),
                    'amount' => $vat,
                    'note' => null,
                ],
            ],
            'total' => $total,
            'currency' => 'NOK',
        ];
    }

    /**
     * @return array{summary: string, period: string, lines: list<array{label: string, amount: float, note: ?string}>, total: float, currency: string}
     */
    protected function enterpriseBreakdown(Enterprise $enterprise, MonthlyBillingPeriod $billingPeriod): array
    {
        $fee = $this->resolveEnterpriseFee($enterprise);

        return [
            'summary' => __('Enterprise subscription charge for :company', [
                'company' => (string) ($enterprise->enterprise_name ?: $enterprise->domain ?: 'Enterprise #'.$enterprise->id),
            ]),
            'period' => $billingPeriod->label(),
            'lines' => [
                [
                    'label' => __('Subscription fee'),
                    'amount' => $fee,
                    'note' => __('Stored subscription / enterprise fee'),
                ],
            ],
            'total' => $fee,
            'currency' => 'NOK',
        ];
    }

    protected function resolveEnterpriseFee(Enterprise $enterprise): float
    {
        $subscriptionFee = $enterprise->subscription?->fee;
        if (filled($subscriptionFee) && is_numeric($subscriptionFee) && (float) $subscriptionFee > 0) {
            return (float) $subscriptionFee;
        }

        $enterpriseFee = $enterprise->subscription_fee ?? null;
        if (filled($enterpriseFee) && is_numeric($enterpriseFee) && (float) $enterpriseFee > 0) {
            return (float) $enterpriseFee;
        }

        return 299.0;
    }

    /**
     * @param  list<string>  $reasons
     * @param  array{summary: string, period: string, lines: list<array{label: string, amount: float, note: ?string}>, total: float, currency: string}  $breakdown
     * @return array<string, mixed>
     */
    protected function row(
        string $type,
        int $id,
        string $company,
        string $name,
        string $email,
        float $monthlyPrice,
        float $dueAmount,
        ?string $lastPaidAt,
        string $billingPeriod,
        ?int $userId,
        bool $onDueList,
        array $reasons,
        array $breakdown,
    ): array {
        return [
            'id' => $type.'-'.$id,
            'type' => $type,
            'type_label' => self::typeOptions()[$type] ?? $type,
            'entity_id' => $id,
            'company' => $company,
            'name' => $name,
            'email' => $email,
            'monthly_price' => $monthlyPrice,
            'due_amount' => $dueAmount,
            'last_paid_at' => $lastPaidAt,
            'billing_period' => $billingPeriod,
            'user_id' => $userId,
            'on_due_list' => $onDueList,
            'unavailable_reason' => $onDueList ? null : implode(' ', $reasons),
            'reasons' => $reasons,
            'breakdown' => $breakdown,
            'entity_url' => $this->entityUrl($type, $id),
            'owner_url' => $this->ownerUrl($userId),
        ];
    }

    protected function entityUrl(string $type, int $id): ?string
    {
        try {
            return match ($type) {
                self::TYPE_SHOP => ShopResource::getUrl('view', ['record' => $id]),
                self::TYPE_PLUGIN => PaymentMethodAccessResource::getUrl('view', ['record' => $id]),
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }

    protected function ownerUrl(?int $userId): ?string
    {
        if (! filled($userId)) {
            return null;
        }

        try {
            return UserResource::getUrl('edit', ['record' => $userId]);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected function applyFilters(Collection $rows, array $filters): Collection
    {
        $type = data_get($filters, 'type.value');
        if ($type === null && array_key_exists('type', $filters) && ! is_array($filters['type'])) {
            $type = $filters['type'];
        }
        if (is_string($type) && filled($type) && $type !== 'all') {
            $rows = $rows->where('type', $type)->values();
        }

        $company = data_get($filters, 'company.company')
            ?? data_get($filters, 'company.value')
            ?? data_get($filters, 'company');
        if (is_string($company) && filled($company)) {
            $needle = mb_strtolower($company);
            $rows = $rows->filter(
                fn (array $row): bool => str_contains(mb_strtolower((string) ($row['company'] ?? '')), $needle)
            )->values();
        }

        $name = data_get($filters, 'name.name')
            ?? data_get($filters, 'name.value')
            ?? data_get($filters, 'name');
        if (is_string($name) && filled($name)) {
            $needle = mb_strtolower($name);
            $rows = $rows->filter(
                fn (array $row): bool => str_contains(mb_strtolower((string) ($row['name'] ?? '')), $needle)
            )->values();
        }

        $email = data_get($filters, 'email.email')
            ?? data_get($filters, 'email.value')
            ?? data_get($filters, 'email');
        if (is_string($email) && filled($email)) {
            $needle = mb_strtolower($email);
            $rows = $rows->filter(
                fn (array $row): bool => str_contains(mb_strtolower((string) ($row['email'] ?? '')), $needle)
            )->values();
        }

        return $rows;
    }
}
