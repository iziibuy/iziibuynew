<?php

namespace App\Filament\Resources\PaymentMethodAccesses\RelationManagers;

use App\Filament\Resources\ExternalOrders\ExternalOrderResource;
use App\Filament\Tables\Filters\ResourceTableFilters;
use App\Models\ExternalOrder;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExternalOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'externalOrders';

    protected static ?string $title = 'Button payment orders';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->recordUrl(fn (ExternalOrder $record): string => ExternalOrderResource::getUrl('view', ['record' => $record]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('customer_name')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('customer_email')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('-'),
                TextColumn::make('amount')
                    ->money(fn (ExternalOrder $record) => $record->currency ?: 'NOK')
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
                TextColumn::make('orderId')
                    ->label(__('Merchant order'))
                    ->searchable()
                    ->toggleable(),
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
                    ->url(fn (ExternalOrder $record): string => ExternalOrderResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
