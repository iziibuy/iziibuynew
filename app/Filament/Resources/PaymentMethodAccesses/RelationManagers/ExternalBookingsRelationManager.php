<?php

namespace App\Filament\Resources\PaymentMethodAccesses\RelationManagers;

use App\Filament\Resources\ExternalBookings\ExternalBookingResource;
use App\Filament\Tables\Filters\ResourceTableFilters;
use App\Models\ExternalBooking;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExternalBookingsRelationManager extends RelationManager
{
    protected static string $relationship = 'externalBookings';

    protected static ?string $title = 'Bookings';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('booking_number')
            ->recordUrl(fn (ExternalBooking $record): string => ExternalBookingResource::getUrl('view', ['record' => $record]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('booking_number')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('phone_number')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('total')
                    ->formatStateUsing(fn ($state, ExternalBooking $record): string => number_format((float) $state, 2).' '.($record->currency ?: 'NOK'))
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => match (strtoupper((string) $state)) {
                        'COMPLETED' => 'success',
                        'PENDING' => 'warning',
                        'FAILED', 'CANCELED' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('payment_status')
                    ->badge()
                    ->color(fn (?string $state): string => match (strtoupper((string) $state)) {
                        'PAID' => 'success',
                        'PENDING' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                ResourceTableFilters::select('status', [
                    'PENDING' => 'PENDING',
                    'COMPLETED' => 'COMPLETED',
                    'FAILED' => 'FAILED',
                    'CANCELED' => 'CANCELED',
                ], __('Status')),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (ExternalBooking $record): string => ExternalBookingResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
