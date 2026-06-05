<?php

namespace App\Filament\Resources\Coupons\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CouponsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('Code'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('shop.user_name')
                    ->label(__('Shop'))
                    ->placeholder(__('Platform'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('discount')
                    ->label(__('Discount'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('expire_at')
                    ->label(__('Expire at'))
                    ->date()
                    ->sortable(),
                TextColumn::make('limit')
                    ->label(__('Limit'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('minimum_cart')
                    ->label(__('Minimum cart'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('used')
                    ->label(__('Used'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('expire_at', 'desc')
            ->filters([
                SelectFilter::make('shop_id')
                    ->label(__('Shop'))
                    ->relationship('shop', 'user_name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
