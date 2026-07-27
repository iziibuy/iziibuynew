<?php

namespace App\Filament\Resources\ExternalOrders\Pages;

use App\Filament\Resources\ExternalOrders\ExternalOrderResource;
use App\Models\ExternalOrder;
use App\Services\ExternalOrderGatewayInspector;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\View as ViewFacade;

class ViewExternalOrder extends ViewRecord
{
    protected static string $resource = ExternalOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('fetchGatewayDetails')
                ->label(__('Fetch gateway details'))
                ->icon('heroicon-o-cloud-arrow-down')
                ->color('primary')
                ->modalHeading(fn (ExternalOrder $record): string => __('Gateway analysis').' #'.$record->getKey())
                ->modalDescription(__('Live data from Elavon or Surfboard for this order.'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('Close'))
                ->modalWidth('5xl')
                ->modalContent(function (ExternalOrder $record) {
                    $result = app(ExternalOrderGatewayInspector::class)->inspect($record);

                    if (! ($result['success'] ?? false)) {
                        Notification::make()
                            ->title(__('Gateway lookup incomplete'))
                            ->body($result['error'] ?? __('No gateway data returned.'))
                            ->warning()
                            ->send();
                    }

                    return ViewFacade::make('filament.external-orders.gateway-analysis', [
                        'result' => $result,
                    ]);
                }),
            EditAction::make(),
        ];
    }
}
