<?php

namespace App\Filament\Resources\PaymentMethodAccesses\Pages;

use App\Filament\Resources\PaymentMethodAccesses\Concerns\NormalizesCompanyAddressFormData;
use App\Filament\Resources\PaymentMethodAccesses\PaymentMethodAccessResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentMethodAccess extends CreateRecord
{
    use NormalizesCompanyAddressFormData;

    protected static string $resource = PaymentMethodAccessResource::class;
}
