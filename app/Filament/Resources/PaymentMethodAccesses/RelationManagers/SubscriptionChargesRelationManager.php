<?php

namespace App\Filament\Resources\PaymentMethodAccesses\RelationManagers;

use App\Filament\Tables\Filters\ResourceTableFilters;
use App\Models\SubscriptionCharge;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionChargesRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptionCharges';

    protected static ?string $title = 'Monthly subscription charges';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('quickpay_order_id')
                    ->label(__('Order id'))
                    ->getStateUsing(fn (SubscriptionCharge $record): string => (string) (
                        $record->quickpay_order_id
                        ?: $record->elavon_transaction_id
                        ?: '-'
                    )),
                TextColumn::make('amount')
                    ->money('NOK')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Paid'))
                    ->formatStateUsing(fn ($state): string => $state ? __('Yes') : __('No'))
                    ->badge()
                    ->color(fn ($state): string => $state ? 'success' : 'danger'),
                TextColumn::make('last4')
                    ->label(__('Last four')),
            ])
            ->filters([
                ResourceTableFilters::boolean('status', __('Paid')),
            ])
            ->recordActions([]);
    }
}
