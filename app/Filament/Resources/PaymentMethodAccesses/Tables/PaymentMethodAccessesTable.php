<?php

namespace App\Filament\Resources\PaymentMethodAccesses\Tables;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Tables\Filters\ResourceTableFilters;
use App\Models\PaymentMethodAccess;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentMethodAccessesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')
                    ->label(__('Company'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('company_email')
                    ->label(__('Email'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('company_domain')
                    ->label(__('Domain'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('user.email')
                    ->label(__('Owner'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('paymentMethod')
                    ->label(__('Gateway'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('fee')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('status')
                    ->boolean(),
                IconColumn::make('is_demo')
                    ->label(__('Demo'))
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('last_paid_at')
                    ->label(__('Last paid'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                ResourceTableFilters::boolean('status', __('Active')),
                ResourceTableFilters::boolean('is_demo', __('Demo / Live')),
                ResourceTableFilters::select('paymentMethod', [
                    'quickpay' => 'QuickPay',
                    'elavon' => 'Elavon',
                    'surfboard' => 'Surfboard',
                ], __('Gateway')),
            ])
            ->recordActions([
                Action::make('editOwner')
                    ->label(__('Edit owner'))
                    ->icon('heroicon-o-user')
                    ->visible(fn (PaymentMethodAccess $record): bool => $record->user_id !== null)
                    ->url(fn (PaymentMethodAccess $record): string => UserResource::getUrl('edit', ['record' => $record->user_id])),
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
