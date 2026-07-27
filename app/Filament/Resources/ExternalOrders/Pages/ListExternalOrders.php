<?php

namespace App\Filament\Resources\ExternalOrders\Pages;

use App\Filament\Resources\ExternalOrders\ExternalOrderResource;
use Filament\Resources\Pages\ListRecords;

class ListExternalOrders extends ListRecords
{
    protected static string $resource = ExternalOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
