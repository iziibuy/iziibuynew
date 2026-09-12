<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Filament\Resources\Charges\ChargeResource;
use App\Models\Charge;
use App\Models\Enterprise;
use App\Models\ExternalSubscription;
use App\Models\ExternalSubscriptionCharge;
use App\Models\MembershipCharge;
use App\Models\PaymentMethodAccess;
use App\Models\Shop;
use App\Models\SubscriptionCharge;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class UnifiedChargeLedger
{
    public const SOURCE_SHOP = 'shop_charge';

    public const SOURCE_SUBSCRIPTION = 'subscription_charge';

    public const SOURCE_EXTERNAL_SUBSCRIPTION = 'external_subscription_charge';

    public const SOURCE_MEMBERSHIP = 'membership_charge';

    /**
     * @return array<string, string>
     */
    public static function modelOptions(): array
    {
        return [
            'Shop' => __('Shop'),
            'Enterprise' => __('Enterprise'),
            'PaymentMethodAccess' => __('Plugin'),
            'ExternalSubscription' => __('Button subscription'),
            'Membership' => __('Membership'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sourceOptions(): array
    {
        return [
            self::SOURCE_SHOP => __('Shop charge'),
            self::SOURCE_SUBSCRIPTION => __('Subscription charge'),
            self::SOURCE_EXTERNAL_SUBSCRIPTION => __('Button subscription charge'),
            self::SOURCE_MEMBERSHIP => __('Membership charge'),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function collect(?array $filters = null, ?string $sortColumn = null, ?string $sortDirection = null): Collection
    {
        $filters ??= [];

        $rows = collect()
            ->concat($this->shopCharges())
            ->concat($this->subscriptionCharges())
            ->concat($this->externalSubscriptionCharges())
            ->concat($this->membershipCharges());

        $rows = $this->applyFilters($rows, $filters);

        $direction = strtolower((string) $sortDirection) === 'asc' ? 'asc' : 'desc';
        $column = filled($sortColumn) ? $sortColumn : 'created_at';

        $sorted = $direction === 'asc'
            ? $rows->sortBy($column, SORT_NATURAL)
            : $rows->sortByDesc($column, SORT_NATURAL);

        return $sorted->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $key): ?array
    {
        return $this->collect()
            ->first(fn (array $row): bool => (string) $row['id'] === $key);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function shopCharges(): Collection
    {
        return Charge::query()
            ->with(['shop.user'])
            ->latest('id')
            ->get()
            ->map(function (Charge $charge): array {
                $shop = $charge->shop;

                return $this->row(
                    key: self::SOURCE_SHOP.'-'.$charge->id,
                    source: self::SOURCE_SHOP,
                    modelType: 'Shop',
                    modelLabel: $shop?->user_name ?: ('Shop #'.$charge->shop_id),
                    modelId: $charge->shop_id,
                    amount: (float) $charge->amount,
                    currency: 'NOK',
                    status: (bool) $charge->status,
                    reference: (string) ($charge->order_id ?: '#'.$charge->id),
                    description: (string) ($charge->comment ?: ''),
                    entityName: $shop?->company_name ?: ($shop?->user_name ?: ''),
                    email: $shop?->contact_email ?: ($shop?->user?->email ?? ''),
                    createdAt: $charge->created_at,
                    extras: [
                        'payment_type' => $charge->payment_type,
                        'is_demo' => (bool) $charge->is_demo,
                        'last_four' => $charge->lastFour,
                        'details' => $this->prettyJson($charge->getAttributes()['details'] ?? null),
                        'payment_body' => $this->prettyJson($charge->getAttributes()['payment_body'] ?? null),
                        'view_url' => $this->safeChargeViewUrl($charge),
                    ],
                );
            });
    }

    protected function safeChargeViewUrl(Charge $charge): ?string
    {
        try {
            return ChargeResource::getUrl('view', ['record' => $charge]);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function subscriptionCharges(): Collection
    {
        return SubscriptionCharge::query()
            ->with(['subscription.subscribable'])
            ->latest('id')
            ->get()
            ->map(function (SubscriptionCharge $charge): array {
                $subscription = $charge->subscription;
                $subscribable = $subscription?->subscribable;
                $modelType = $this->basename($subscription?->subscribable_type);

                $entityName = match (true) {
                    $subscribable instanceof Shop => (string) ($subscribable->company_name ?: $subscribable->user_name),
                    $subscribable instanceof Enterprise => (string) ($subscribable->enterprise_name ?: $subscribable->name ?: ''),
                    $subscribable instanceof PaymentMethodAccess => (string) ($subscribable->company_name ?: ''),
                    default => class_basename((string) $subscription?->subscribable_type).' #'.($subscription?->subscribable_id ?? ''),
                };

                return $this->row(
                    key: self::SOURCE_SUBSCRIPTION.'-'.$charge->id,
                    source: self::SOURCE_SUBSCRIPTION,
                    modelType: $modelType !== '' ? $modelType : 'Unknown',
                    modelLabel: $entityName !== '' ? $entityName : ('Subscription #'.$charge->subscription_id),
                    modelId: $subscription?->subscribable_id,
                    amount: (float) $charge->amount,
                    currency: 'NOK',
                    status: (bool) $charge->status,
                    reference: (string) ($charge->quickpay_order_id ?: $charge->elavon_transaction_id ?: '#'.$charge->id),
                    description: __('Subscription charge'),
                    entityName: $entityName,
                    email: (string) (
                        data_get($subscribable, 'contact_email')
                        ?: data_get($subscribable, 'company_email')
                        ?: data_get($subscribable, 'email')
                        ?: ''
                    ),
                    createdAt: $charge->created_at,
                    extras: [
                        'subscription_id' => $charge->subscription_id,
                        'domain' => $charge->domain,
                        'last_four' => $charge->last4,
                        'payment_details' => $this->prettyJson($charge->getAttributes()['payment_details'] ?? null),
                        'charge_details' => $this->prettyJson($charge->getAttributes()['charge_details'] ?? null),
                    ],
                );
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function externalSubscriptionCharges(): Collection
    {
        return ExternalSubscriptionCharge::query()
            ->with(['subscription.paymentMethodAccess'])
            ->latest('id')
            ->get()
            ->map(function (ExternalSubscriptionCharge $charge): array {
                /** @var ExternalSubscription|null $subscription */
                $subscription = $charge->subscription;
                $plugin = $subscription?->paymentMethodAccess;

                return $this->row(
                    key: self::SOURCE_EXTERNAL_SUBSCRIPTION.'-'.$charge->id,
                    source: self::SOURCE_EXTERNAL_SUBSCRIPTION,
                    modelType: 'ExternalSubscription',
                    modelLabel: $plugin?->company_name
                        ?: ($subscription?->customer_name ?: ('Button sub #'.$charge->external_subscription_id)),
                    modelId: $charge->external_subscription_id,
                    amount: (float) $charge->amount,
                    currency: strtoupper((string) ($charge->currency ?: 'NOK')),
                    status: (bool) $charge->status,
                    reference: (string) ($charge->elavon_transaction_id ?: $charge->surfboard_transaction_id ?: '#'.$charge->id),
                    description: (string) ($charge->type ?: 'renewal'),
                    entityName: (string) ($subscription?->customer_name ?: $plugin?->company_name ?: ''),
                    email: (string) ($subscription?->customer_email ?: $plugin?->company_email ?: ''),
                    createdAt: $charge->created_at,
                    extras: [
                        'type' => $charge->type,
                        'failure_message' => $charge->failure_message,
                        'payment_details' => $this->prettyJson($charge->getAttributes()['payment_details'] ?? null),
                        'customer_email' => $subscription?->customer_email,
                        'plugin' => $plugin?->company_name,
                    ],
                );
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function membershipCharges(): Collection
    {
        return MembershipCharge::query()
            ->latest('id')
            ->get()
            ->map(function (MembershipCharge $charge): array {
                $shop = Shop::query()->find($charge->shop_id);

                return $this->row(
                    key: self::SOURCE_MEMBERSHIP.'-'.$charge->id,
                    source: self::SOURCE_MEMBERSHIP,
                    modelType: 'Membership',
                    modelLabel: $shop?->user_name ?: ('Membership #'.$charge->membership_id),
                    modelId: $charge->membership_id,
                    amount: (float) $charge->amount,
                    currency: 'NOK',
                    status: (bool) $charge->status,
                    reference: '#'.$charge->id,
                    description: __('Membership charge'),
                    entityName: (string) ($shop?->company_name ?: $shop?->user_name ?: ''),
                    email: (string) ($shop?->contact_email ?: ''),
                    createdAt: $charge->created_at,
                    extras: [
                        'shop_id' => $charge->shop_id,
                        'membership_id' => $charge->membership_id,
                    ],
                );
            });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected function applyFilters(Collection $rows, array $filters): Collection
    {
        $model = data_get($filters, 'model.value') ?? data_get($filters, 'model');
        if (filled($model) && is_string($model)) {
            $rows = $rows->where('model_type', $model)->values();
        }

        $source = data_get($filters, 'source.value') ?? data_get($filters, 'source');
        if (filled($source) && is_string($source)) {
            $rows = $rows->where('source', $source)->values();
        }

        $status = data_get($filters, 'status.value') ?? data_get($filters, 'status');
        if ($status === '1' || $status === 1 || $status === true || $status === 'paid') {
            $rows = $rows->where('status', true)->values();
        } elseif ($status === '0' || $status === 0 || $status === false || $status === 'unpaid') {
            $rows = $rows->where('status', false)->values();
        }

        $from = data_get($filters, 'date.from');
        if (filled($from)) {
            $fromDate = Carbon::parse((string) $from)->startOfDay();
            $rows = $rows->filter(fn (array $row): bool => Carbon::parse((string) $row['created_at'])->greaterThanOrEqualTo($fromDate))->values();
        }

        $until = data_get($filters, 'date.until');
        if (filled($until)) {
            $untilDate = Carbon::parse((string) $until)->endOfDay();
            $rows = $rows->filter(fn (array $row): bool => Carbon::parse((string) $row['created_at'])->lessThanOrEqualTo($untilDate))->values();
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $extras
     * @return array<string, mixed>
     */
    protected function row(
        string $key,
        string $source,
        string $modelType,
        string $modelLabel,
        mixed $modelId,
        float $amount,
        string $currency,
        bool $status,
        string $reference,
        string $description,
        string $entityName,
        string $email,
        mixed $createdAt,
        array $extras = [],
    ): array {
        return [
            'id' => $key,
            'source' => $source,
            'source_label' => self::sourceOptions()[$source] ?? $source,
            'model_type' => $modelType,
            'model_label' => $modelLabel,
            'model_id' => $modelId,
            'amount' => $amount,
            'currency' => $currency,
            'status' => $status,
            'status_label' => $status ? __('Paid') : __('Unpaid'),
            'reference' => $reference,
            'description' => $description,
            'entity_name' => $entityName,
            'email' => $email,
            'created_at' => optional($createdAt)?->toDateTimeString() ?? '',
            'extras' => $extras,
        ];
    }

    protected function basename(?string $class): string
    {
        if (blank($class)) {
            return '';
        }

        return class_basename($class);
    }

    protected function prettyJson(mixed $state): string
    {
        if ($state === null || $state === '') {
            return '';
        }

        if (is_array($state) || is_object($state)) {
            return (string) json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $decoded = json_decode((string) $state);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return (string) $state;
        }

        return (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
