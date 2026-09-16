<?php

namespace App\Filament\Resources\PaymentMethodAccesses\RelationManagers;

use App\Filament\Tables\Filters\ResourceTableFilters;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExternalSubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'externalSubscriptions';

    protected static ?string $title = 'Customer subscriptions';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('charges'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
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
                TextColumn::make('interval_days')
                    ->label(__('Every (days)'))
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => match (strtolower((string) $state)) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'canceled', 'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('charges_count')
                    ->label(__('Charges'))
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('next_charge_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('canceled_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                ResourceTableFilters::select('status', [
                    'active' => 'Active',
                    'pending' => 'Pending',
                    'canceled' => 'Canceled',
                    'failed' => 'Failed',
                ], __('Status')),
            ])
            ->recordActions([]);
    }
}
