<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Shops\ShopResource;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecordTitle();
    }

    protected function getHeaderActions(): array
    {
        /** @var Product $product */
        $product = $this->getRecord();

        return [
            Action::make('visitProduct')
                ->label(__('View on shop'))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->url(fn (): ?string => $product->shop ? $product->path() : null)
                ->openUrlInNewTab()
                ->visible(fn (): bool => $product->shop !== null && filled($product->slug)),
            Action::make('viewShop')
                ->label(__('View shop'))
                ->icon(Heroicon::OutlinedBuildingStorefront)
                ->url(fn (): ?string => $product->shop_id
                    ? ShopResource::getUrl('view', ['record' => $product->shop_id])
                    : null)
                ->visible(fn (): bool => $product->shop_id !== null),
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
