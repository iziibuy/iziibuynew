<?php

declare(strict_types=1);

namespace App\Services\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Filtered unified charge rows shared by the All charges table and CSV export.
 */
class AllChargesQuery
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
        bool $lightweight = false,
    ): Collection {
        $rows = app(UnifiedChargeLedger::class)->collect(
            filters: $filters,
            sortColumn: $sortColumn,
            sortDirection: $sortDirection,
            lightweight: $lightweight,
        );

        if (blank($search)) {
            return $rows;
        }

        $needle = Str::lower($search);

        return $rows->filter(function (array $row) use ($needle): bool {
            $haystack = Str::lower(implode(' ', [
                $row['reference'] ?? '',
                $row['entity_name'] ?? '',
                $row['email'] ?? '',
                $row['model_label'] ?? '',
                $row['description'] ?? '',
                $row['source_label'] ?? '',
            ]));

            return str_contains($haystack, $needle);
        })->values();
    }

    /**
     * @return array{
     *     filters: array<string, mixed>|null,
     *     sortColumn: ?string,
     *     sortDirection: ?string,
     *     search: ?string
     * }
     */
    public function stateFromRequest(Request $request): array
    {
        $filters = $request->input('filters');
        if (! is_array($filters)) {
            $filters = null;
        }

        $sortColumn = $request->string('sortColumn')->toString();
        $sortColumn = filled($sortColumn) ? $sortColumn : null;

        $sortDirection = $request->string('sortDirection')->toString();
        $sortDirection = filled($sortDirection) ? $sortDirection : null;

        $search = $request->string('search')->toString();

        return [
            'filters' => $filters,
            'sortColumn' => $sortColumn,
            'sortDirection' => $sortDirection,
            'search' => filled($search) ? $search : null,
        ];
    }
}
