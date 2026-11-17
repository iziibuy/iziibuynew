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
     * Entities the monthly charge commands would attempt to bill for the given period.
     *
     * @param  array<string, mixed>|null  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function collect(
        ?MonthlyBillingPeriod $billingPeriod = null,
        ?array $filters = null,
        ?string $sortColumn = null,
        ?string $sortDirection = null,
    ): Collection {
        $billingPeriod ??= MonthlyBillingPeriod::resolve();
        $filters ??= [];

        $rows = collect()
            ->concat($this->dueShops($billingPeriod))
            ->concat($this->duePlugins($billingPeriod))
            ->concat($this->dueEnterprises($billingPeriod));

        $rows = $this->applyFilters($rows, $filters);

        $direction = strtolower((string) $sortDirection) === 'asc' ? 'asc' : 'desc';
        $column = filled($sortColumn) ? $sortColumn : 'company';

        $sorted = $direction === 'asc'
            ? $rows->sortBy($column, SORT_NATURAL)
            : $rows->sortByDesc($column, SORT_NATURAL);

        return $sorted->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function dueShops(MonthlyBillingPeriod $billingPeriod): Collection
    {
        $paidThrough = $billingPeriod->paidThrough()->toDateTimeString();

        return Shop::query()
            ->with(['user', 'users', 'metas'])
            ->where('status', 1)
            ->where(fn (Builder $query) => $query
                ->whereNull('paid_at')
                ->orWhere('paid_at', '<', $paidThrough))
            ->orderBy('id')
            ->get()
            ->filter(function (Shop $shop): bool {
                if ($shop->requiresElavonResubscription()) {
                    return false;
                }

                // Elavon-native plans are billed by Elavon, not monthly:charge.
                return ! $shop->usesElavonNativeSubscription();
            })
            ->map(function (Shop $shop) use ($billingPeriod): array {
                $owner = $shop->user;

                return $this->row(
                    type: self::TYPE_SHOP,
                    id: $shop->id,
                    company: (string) ($shop->company_name ?: $shop->name ?: $shop->user_name ?: 'Shop #'.$shop->id),
                    name: trim((string) (($owner?->name ?? '').' '.($owner?->last_name ?? ''))) ?: (string) ($owner?->email ?? '-'),
                    email: (string) ($shop->contact_email ?: $owner?->email ?: '-'),
                    monthlyPrice: (float) ($shop->monthly_cost ?? 0),
                    dueAmount: (float) $shop->subscriptionFeeFull(),
                    lastPaidAt: $shop->paid_at?->toDateTimeString(),
                    billingPeriod: $billingPeriod->label(),
                    userId: $owner?->id,
                );
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function duePlugins(MonthlyBillingPeriod $billingPeriod): Collection
    {
        $paidThrough = $billingPeriod->paidThrough()->toDateTimeString();

        return PaymentMethodAccess::query()
            ->with(['user', 'subscription'])
            ->where('status', 1)
            ->where(fn (Builder $query) => $query
                ->whereNull('last_paid_at')
                ->orWhere('last_paid_at', '<', $paidThrough))
            ->where(fn (Builder $query) => $query
                ->whereNull('trial_ends_at')
                ->orWhereNotNull('trial_charged_at'))
            ->orderBy('id')
            ->get()
            ->filter(fn (PaymentMethodAccess $access): bool => (int) ($access->subscription?->status ?? 0) === 1)
            ->map(function (PaymentMethodAccess $access) use ($billingPeriod): array {
                $owner = $access->user;

                return $this->row(
                    type: self::TYPE_PLUGIN,
                    id: $access->id,
                    company: (string) ($access->company_name ?: 'Plugin #'.$access->id),
                    name: trim((string) (($owner?->name ?? '').' '.($owner?->last_name ?? ''))) ?: (string) ($owner?->email ?? '-'),
                    email: (string) ($access->company_email ?: $owner?->email ?: '-'),
                    monthlyPrice: $access->baseMonthlyFee(),
                    dueAmount: $access->monthlyRenewalFee(),
                    lastPaidAt: $access->last_paid_at?->toDateTimeString(),
                    billingPeriod: $billingPeriod->label(),
                    userId: $owner?->id,
                );
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function dueEnterprises(MonthlyBillingPeriod $billingPeriod): Collection
    {
        $paidThrough = $billingPeriod->paidThrough();

        return Enterprise::query()
            ->with('subscription')
            ->where('status', 1)
            ->where(fn (Builder $query) => $query
                ->whereNull('paid_at')
                ->orWhere('paid_at', '<', $paidThrough))
            ->orderBy('id')
            ->get()
            ->filter(fn (Enterprise $enterprise): bool => $enterprise->subscription !== null
                && filled($enterprise->subscription_id)
                && (int) $enterprise->subscription->status === 1)
            ->map(function (Enterprise $enterprise) use ($billingPeriod): array {
                $fee = $this->resolveEnterpriseFee($enterprise);

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
                );
            })
            ->values();
    }

    /**
     * Prefer stored fees; avoid remote details() HTTP calls on the due list.
     */
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

        return $rows;
    }
}
