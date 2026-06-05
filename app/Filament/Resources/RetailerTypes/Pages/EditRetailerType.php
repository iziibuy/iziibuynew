<?php

namespace App\Filament\Resources\RetailerTypes\Pages;

use App\Filament\Resources\RetailerTypes\RetailerTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRetailerType extends EditRecord
{
    protected static string $resource = RetailerTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
