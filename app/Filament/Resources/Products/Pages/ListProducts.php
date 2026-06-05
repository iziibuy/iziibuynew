<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Shop;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        $shopId = request()->integer('shop');

        if ($shopId <= 0) {
            return null;
        }

        $shop = Shop::query()->find($shopId);

        if ($shop === null) {
            return null;
        }

        return __('Showing products for :shop', ['shop' => $shop->user_name]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
