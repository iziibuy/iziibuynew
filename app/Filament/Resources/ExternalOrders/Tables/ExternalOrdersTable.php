<?php

namespace App\Filament\Resources\ExternalOrders\Tables;

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

class ExternalOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('paymentMethodAccess.company_name')
                    ->label(__('Plugin'))
                    ->searchable()
                    ->toggleable()
                    ->placeholder('-'),
                TextColumn::make('customer_name')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('customer_email')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('-'),
                TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency ?: 'NOK')
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
                TextColumn::make('payment_method')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('orderId')
                    ->label(__('Merchant order'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('payment_id')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(20),
                TextColumn::make('description')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
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
