<?php

namespace App\Filament\Resources\ExternalBookings\Pages;

use App\Filament\Resources\ExternalBookings\ExternalBookingResource;
use App\Models\ExternalBooking;
use App\Services\ExternalBookingGatewayInspector;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\View as ViewFacade;

class ViewExternalBooking extends ViewRecord
{
    protected static string $resource = ExternalBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('fetchGatewayDetails')
                ->label(__('Fetch gateway details'))
                ->icon('heroicon-o-cloud-arrow-down')
                ->color('primary')
                ->modalHeading(fn (ExternalBooking $record): string => __('Gateway analysis').' #'.$record->getKey())
                ->modalDescription(__('Live data from Elavon or Surfboard for this booking.'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('Close'))
                ->modalWidth('5xl')
                ->modalContent(function (ExternalBooking $record) {
                    $result = app(ExternalBookingGatewayInspector::class)->inspect($record);

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
