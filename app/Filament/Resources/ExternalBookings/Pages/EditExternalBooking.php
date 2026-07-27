<?php

namespace App\Filament\Resources\ExternalBookings\Pages;

use App\Filament\Resources\ExternalBookings\ExternalBookingResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditExternalBooking extends EditRecord
{
    protected static string $resource = ExternalBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
