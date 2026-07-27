<?php

namespace App\Filament\Resources\ExternalBookings\Tables;

use App\Filament\Tables\Filters\ResourceTableFilters;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExternalBookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('booking_number')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('paymentMethodAccess.company_name')
                    ->label(__('Plugin'))
                    ->searchable()
                    ->toggleable()
                    ->placeholder('-'),
                TextColumn::make('phone_number')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('total')
                    ->formatStateUsing(fn ($state, $record): string => number_format((float) $state, 2).' '.($record->currency ?: 'NOK'))
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => match (strtoupper((string) $state)) {
                        'COMPLETED' => 'success',
                        'PENDING' => 'warning',
                        'FAILED', 'CANCELED' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('payment_status')
                    ->badge()
                    ->color(fn (?string $state): string => match (strtoupper((string) $state)) {
                        'PAID' => 'success',
                        'PENDING' => 'warning',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('payment_method')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('payment_id')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(20),
                TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                ResourceTableFilters::select('status', [
                    'PENDING' => 'PENDING',
                    'COMPLETED' => 'COMPLETED',
                    'FAILED' => 'FAILED',
                    'CANCELED' => 'CANCELED',
                ], __('Status')),
                ResourceTableFilters::select('payment_status', [
                    'PENDING' => 'PENDING',
                    'PAID' => 'PAID',
                ], __('Payment status')),
                ResourceTableFilters::select('payment_method', [
                    'elavon' => 'Elavon',
                    'surfboard' => 'Surfboard',
                ], __('Payment method')),
                Filter::make('plugin')
                    ->form([
                        Select::make('payment_method_access_id')
                            ->label(__('Plugin'))
                            ->relationship('paymentMethodAccess', 'company_name')
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['payment_method_access_id'] ?? null,
                            fn (Builder $query, $value): Builder => $query->where('payment_method_access_id', $value)
                        );
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
