<?php

namespace App\Filament\Resources\PaymentMethodAccesses\RelationManagers;

use App\Models\PaymentApi;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PaymentapisRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentapis';

    protected static ?string $title = 'Iziipay APIs';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->label(__('Source key'))
                    ->disabled()
                    ->dehydrated()
                    ->copyable()
                    ->maxLength(255)
                    ->visible(fn (?PaymentApi $record): bool => filled($record))
                    ->helperText(__('Used as source_key in the Iziipay JavaScript SDK and API requests.')),
                TextInput::make('domain')
                    ->label(__('Domain'))
                    ->url()
                    ->required()
                    ->maxLength(255),
                TextInput::make('success_redirect_url')
                    ->label(__('Success redirect URL'))
                    ->url()
                    ->required()
                    ->maxLength(255),
                TextInput::make('failed_redirect_url')
                    ->label(__('Failed redirect URL'))
                    ->url()
                    ->required()
                    ->maxLength(255),
                TextInput::make('cancel_callback_url')
                    ->label(__('Cancel callback URL'))
                    ->url()
                    ->maxLength(255),
                Toggle::make('status')
                    ->label(__('Active'))
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('domain')
            ->columns([
                TextColumn::make('key')
                    ->label(__('Source key'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('domain')
                    ->searchable(),
                TextColumn::make('success_redirect_url')
                    ->label(__('Success URL'))
                    ->toggleable(),
                TextColumn::make('failed_redirect_url')
                    ->label(__('Failed URL'))
                    ->toggleable(),
                TextColumn::make('cancel_callback_url')
                    ->label(__('Cancel callback'))
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('status')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('Create Iziipay API'))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['key'] = (string) Str::ulid();
                        $data['status'] = $data['status'] ?? true;

                        return $data;
                    }),
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
