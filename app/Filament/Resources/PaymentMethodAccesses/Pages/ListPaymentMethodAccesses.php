<?php

namespace App\Filament\Resources\PaymentMethodAccesses\Pages;

use App\Filament\Resources\PaymentMethodAccesses\PaymentMethodAccessResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPaymentMethodAccesses extends ListRecords
{
    protected static string $resource = PaymentMethodAccessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
