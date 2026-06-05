<?php

declare(strict_types=1);

namespace App\Filament\Tables\Filters;

use App\Enums\MenuContext;
use App\Enums\MenuLinkType;
use App\Models\Shop;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;

final class ResourceTableFilters
{
    public static function shop(): SelectFilter
    {
        return SelectFilter::make('shop_id')
            ->label(__('Shop'))
            ->relationship('shop', 'user_name')
            ->searchable()
            ->preload();
    }

    public static function boolean(string $field, ?string $label = null): TernaryFilter
    {
        return TernaryFilter::make($field)
            ->label($label ?? __(ucfirst(str_replace('_', ' ', $field))));
    }

    /**
     * @param  array<int|string, string>  $options
     */
    public static function select(string $field, array $options, ?string $label = null): SelectFilter
    {
        return SelectFilter::make($field)
            ->label($label ?? __(ucfirst(str_replace('_', ' ', $field))))
            ->options($options);
    }

    public static function orderStatus(): SelectFilter
    {
        return self::select('status', [
            0 => __('words.status_pending'),
            1 => __('words.status_paid'),
            2 => __('words.status_shipped'),
            3 => __('words.status_canceled'),
            4 => __('words.not_delivered'),
            5 => __('words.delivered'),
        ], __('Status'));
    }

    public static function shopArea(): SelectFilter
    {
        return SelectFilter::make('area')
            ->label(__('Area'))
            ->options(fn (): array => Shop::query()
                ->whereNotNull('area')
                ->where('area', '!=', '')
                ->distinct()
                ->orderBy('area')
                ->pluck('area', 'area')
                ->all())
            ->searchable();
    }

    public static function couponScope(): SelectFilter
    {
        return SelectFilter::make('scope')
            ->label(__('Type'))
            ->options([
                'platform' => __('Platform'),
                'shop' => __('Shop'),
            ])
            ->query(function (Builder $query, array $data): Builder {
                return match ($data['value'] ?? null) {
                    'platform' => $query->whereNull('shop_id'),
                    'shop' => $query->whereNotNull('shop_id'),
                    default => $query,
                };
            });
    }

    public static function menuContext(): SelectFilter
    {
        return self::select('context', [
            MenuContext::Frontend->value => MenuContext::Frontend->label(),
            MenuContext::Admin->value => MenuContext::Admin->label(),
        ], __('Context'));
    }

    public static function menuLinkType(): SelectFilter
    {
        return self::select('link_type', [
            MenuLinkType::Url->value => __('URL'),
            MenuLinkType::Route->value => __('Route'),
            MenuLinkType::Resource->value => __('Resource'),
        ], __('Link type'));
    }
}
