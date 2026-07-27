<?php

namespace App\Filament\Resources\ExternalBookings\Pages;

use App\Filament\Resources\ExternalBookings\ExternalBookingResource;
use Filament\Resources\Pages\ListRecords;

class ListExternalBookings extends ListRecords
{
    protected static string $resource = ExternalBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
