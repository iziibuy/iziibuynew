<?php

namespace App\Filament\Resources\PaymentMethodAccesses\Pages;

use App\Filament\Resources\PaymentMethodAccesses\PaymentMethodAccessResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPaymentMethodAccess extends ViewRecord
{
    protected static string $resource = PaymentMethodAccessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
