<?php

namespace App\Filament\Resources\RetailerTypes\Pages;

use App\Filament\Resources\RetailerTypes\RetailerTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRetailerTypes extends ListRecords
{
    protected static string $resource = RetailerTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
