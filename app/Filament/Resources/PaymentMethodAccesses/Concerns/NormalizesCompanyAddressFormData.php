<?php

namespace App\Filament\Resources\PaymentMethodAccesses\Concerns;

use App\Filament\Resources\PaymentMethodAccesses\Schemas\PaymentMethodAccessForm;
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

        foreach (PaymentMethodAccessForm::metaFieldNames() as $field) {
            $data[$field] = $this->getRecord()->{$field};
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->prepareDataForPersistence($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->prepareDataForPersistence($data);
    }

    protected function afterCreate(): void
    {
        $this->persistMetaFields();
    }

    protected function afterSave(): void
    {
        $this->persistMetaFields();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareDataForPersistence(array $data): array
    {
        if (isset($data['company_address']) && is_array($data['company_address'])) {
            $data['company_address'] = array_filter(
                $data['company_address'],
                fn (mixed $value): bool => $value !== null && $value !== ''
            );
        }

        foreach (PaymentMethodAccessForm::metaFieldNames() as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    private function persistMetaFields(): void
    {
        $meta = [];

        foreach (PaymentMethodAccessForm::metaFieldNames() as $field) {
            $value = $this->data[$field] ?? null;

            if ($value !== null && $value !== '') {
                $meta[$field] = $value;
            }
        }

        if ($meta !== []) {
            $this->record->createMetas($meta);
        }
    }
}
