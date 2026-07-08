<?php

namespace App\Filament\Resources\PaymentMethodAccesses\Pages;

use App\Filament\Resources\PaymentMethodAccesses\Concerns\NormalizesCompanyAddressFormData;
use App\Filament\Resources\PaymentMethodAccesses\PaymentMethodAccessResource;
use App\Models\PaymentMethodAccess;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditPaymentMethodAccess extends EditRecord
{
    use NormalizesCompanyAddressFormData;

    protected static string $resource = PaymentMethodAccessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regeneratePluginKey')
                ->label(__('Regenerate plugin key'))
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('Regenerate plugin key?'))
                ->modalDescription(__('API endpoints using the current plugin key must be updated after regeneration.'))
                ->action(function (): void {
                    /** @var PaymentMethodAccess $record */
                    $record = $this->getRecord();
                    $record->update(['key' => (string) Str::uuid()]);

                    $this->refreshFormData(['key']);

                    Notification::make()
                        ->title(__('Plugin key regenerated'))
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
