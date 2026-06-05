<?php

namespace App\Filament\Resources\PaymentMethodAccesses\Pages;

use App\Filament\Resources\PaymentMethodAccesses\PaymentMethodAccessResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentMethodAccess extends CreateRecord
{
    protected static string $resource = PaymentMethodAccessResource::class;
}
