<?php

namespace App\Filament\Resources\Shops\RelationManagers;

use App\Filament\Resources\Charges\ChargeResource;
use App\Filament\Tables\Filters\ResourceTableFilters;
use App\Models\Charge;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChargesRelationManager extends RelationManager
{
    protected static string $relationship = 'charges';

    protected static ?string $title = 'Monthly subscription charges';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('order_id')
            ->recordUrl(fn (Charge $record): string => ChargeResource::getUrl('view', ['record' => $record]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('order_id')
                    ->label(__('Order id'))
                    ->searchable(),
                TextColumn::make('amount')
                    ->money('NOK')
                    ->sortable(),
                TextColumn::make('comment')
                    ->label(__('Type'))
                    ->placeholder('—'),
                TextColumn::make('payment_type')
                    ->label(__('Payment type'))
                    ->badge(),
                IconColumn::make('status')
                    ->label(__('Paid'))
                    ->boolean(),
                IconColumn::make('is_demo')
                    ->label(__('Demo'))
                    ->boolean(),
            ])
            ->filters([
                ResourceTableFilters::boolean('status', __('Paid')),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Charge $record): string => ChargeResource::getUrl('view', ['record' => $record])),
                DeleteAction::make(),
            ]);
    }
}
