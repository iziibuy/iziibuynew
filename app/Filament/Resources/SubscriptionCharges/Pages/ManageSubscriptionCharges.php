<?php

namespace App\Filament\Resources\SubscriptionCharges\Pages;

use App\Filament\Resources\SubscriptionCharges\SubscriptionChargeResource;
use Filament\Resources\Pages\ManageRecords;

class ManageSubscriptionCharges extends ManageRecords
{
    protected static string $resource = SubscriptionChargeResource::class;

    protected static ?string $title = 'Subscription Charges';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
