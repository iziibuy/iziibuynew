<?php

namespace App\Filament\Resources\Shops\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Shops\ShopResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Shop;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;

class ViewShop extends ViewRecord
{
    protected static string $resource = ShopResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var Shop $shop */
        $shop = $this->getRecord();

        $shop->loadCount([
            'orders',
            'products as parent_products_count' => fn ($query) => $query->whereNull('parent_id'),
        ]);

        $monthStart = Carbon::now()->startOfMonth();

        $shop->setAttribute('view_stats_total_earnings', (float) $shop->orders()->sum('total'));
        $shop->setAttribute('view_stats_month_orders', $shop->orders()->where('created_at', '>=', $monthStart)->count());
        $shop->setAttribute('view_stats_month_earnings', (float) $shop->orders()->where('created_at', '>=', $monthStart)->sum('total'));
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getRecordTitle();
    }

    protected function getHeaderActions(): array
    {
        /** @var Shop $shop */
        $shop = $this->getRecord();

        return [
            Action::make('visitShop')
                ->label(__('Visit Shop'))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->url(route('shop.home', ['user_name' => $shop->user_name]))
                ->openUrlInNewTab(),
            EditAction::make(),
            ActionGroup::make([
                Action::make('advanceEdit')
                    ->label(__('Advance Edit'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(ShopResource::getUrl('advance-edit', ['record' => $shop])),
                Action::make('productList')
                    ->label(__('Product List'))
                    ->icon(Heroicon::OutlinedCube)
                    ->url(ProductResource::getUrl('index').'?shop='.$shop->id),
                Action::make('exportProducts')
                    ->label(__('Export Products'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(route('admin.shop_product_export_by_admin', $shop))
                    ->openUrlInNewTab(),
                Action::make('editOwnerProfile')
                    ->label(__('Edit Owner Profile'))
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->url(UserResource::getUrl('edit', ['record' => $shop->user_id]))
                    ->visible(fn (): bool => $shop->user_id !== null),
                DeleteAction::make(),
            ])
                ->label(__('More actions'))
                ->icon(Heroicon::OutlinedEllipsisVertical)
                ->color('gray')
                ->button(),
        ];
    }
}
