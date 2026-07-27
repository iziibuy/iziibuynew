<?php

namespace App\Filament\Resources\ExternalOrders\Pages;

use App\Filament\Resources\ExternalOrders\ExternalOrderResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditExternalOrder extends EditRecord
{
    protected static string $resource = ExternalOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
