<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Services\Subscription\MonthlyBillingPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Filtered due-bill rows shared by the Due bills Filament table.
 */
class DueBillsQuery
{
    /**
     * @param  array<string, mixed>|null  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(
        ?array $filters,
        ?string $sortColumn,
        ?string $sortDirection,
        ?string $search,
        ?MonthlyBillingPeriod $billingPeriod = null,
        string $scope = UnifiedDueLedger::SCOPE_DUE,
    ): Collection {
        $billingPeriod ??= $this->billingPeriodFromFilters($filters);

        $rows = app(UnifiedDueLedger::class)->collect(
            billingPeriod: $billingPeriod,
            filters: $filters,
            sortColumn: $sortColumn,
            sortDirection: $sortDirection,
            scope: $scope,
        );

        if (blank($search)) {
            return $rows;
        }

        $needle = Str::lower($search);

        return $rows->filter(function (array $row) use ($needle): bool {
            $haystack = Str::lower(implode(' ', [
                $row['company'] ?? '',
                $row['name'] ?? '',
                $row['email'] ?? '',
                $row['type_label'] ?? '',
                $row['unavailable_reason'] ?? '',
                (string) ($row['entity_id'] ?? ''),
                (string) ($row['user_id'] ?? ''),
            ]));

            return str_contains($haystack, $needle);
        })->values();
    }

    /**
     * @param  array<string, mixed>|null  $filters
     * @return array{due: int, unavailable: int}
     */
    public function counts(?array $filters = null, ?MonthlyBillingPeriod $billingPeriod = null): array
    {
        $billingPeriod ??= $this->billingPeriodFromFilters($filters);

        return app(UnifiedDueLedger::class)->counts($billingPeriod, $filters);
    }

    /**
     * @param  array<string, mixed>|null  $filters
     */
    public function billingPeriodFromFilters(?array $filters): MonthlyBillingPeriod
    {
        $month = data_get($filters, 'month.value')
            ?? data_get($filters, 'month')
            ?? null;

        if (is_array($month)) {
            $month = $month['month'] ?? null;
        }

        return MonthlyBillingPeriod::resolve(is_string($month) && filled($month) ? $month : null);
    }
}
