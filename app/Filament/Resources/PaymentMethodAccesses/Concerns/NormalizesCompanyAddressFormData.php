<?php

namespace App\Filament\Resources\PaymentMethodAccesses\Concerns;

use App\Models\PaymentMethodAccess;

trait NormalizesCompanyAddressFormData
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['company_address'] = PaymentMethodAccess::companyAddressToFormState($data['company_address'] ?? null);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->normalizeCompanyAddressForSave($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->normalizeCompanyAddressForSave($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeCompanyAddressForSave(array $data): array
    {
        if (! isset($data['company_address']) || ! is_array($data['company_address'])) {
            return $data;
        }

        $data['company_address'] = array_filter(
            $data['company_address'],
            fn (mixed $value): bool => $value !== null && $value !== ''
        );

        return $data;
    }
}
