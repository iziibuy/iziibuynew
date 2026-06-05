<?php

namespace App\Filament\Resources\Shops\Pages;

use App\Filament\Resources\Shops\ShopResource;
use App\Models\Shop;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditShop extends EditRecord
{
    protected static string $resource = ShopResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Shop $shop */
        $shop = $this->getRecord();

        return [
            Action::make('sendPassword')
                ->label('Generate password and sent')
                ->icon(Heroicon::OutlinedKey)
                ->url(route('admin.send.shop.password', $shop)),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
