<?php

namespace App\Filament\Resources\PaymentMethodAccesses\RelationManagers;

use App\Models\PaymentApi;
use App\Models\PaymentMethodAccess;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                    ->required()
                    ->maxLength(255)
                    ->unique(PaymentApi::class, 'key', ignoreRecord: true)
                    ->copyable()
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
                Select::make('elavon_link_mode')
                    ->label(__('Elavon payment link'))
                    ->options([
                        PaymentApi::ELAVON_LINK_MODE_HOSTED => __('Hosted payment page (Elavon HPP redirect)'),
                        PaymentApi::ELAVON_LINK_MODE_CHECKOUTJS => __('Own CheckoutJS page (card fields on our payment page)'),
                    ])
                    ->default(PaymentApi::ELAVON_LINK_MODE_HOSTED)
                    ->native(false)
                    ->helperText(__('Hosted sends customers to Elavon. CheckoutJS sends your branded payment page using Elavon Hosted Fields.'))
                    ->visible(fn (): bool => $this->getOwnerRecord() instanceof PaymentMethodAccess
                        && $this->getOwnerRecord()->paymentMethod === 'elavon'),
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
                TextColumn::make('elavon_link_mode')
                    ->label(__('Elavon link'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        PaymentApi::ELAVON_LINK_MODE_CHECKOUTJS => __('CheckoutJS'),
                        default => __('Hosted'),
                    })
                    ->color(fn (?string $state): string => $state === PaymentApi::ELAVON_LINK_MODE_CHECKOUTJS ? 'info' : 'gray')
                    ->visible(fn (): bool => $this->getOwnerRecord() instanceof PaymentMethodAccess
                        && $this->getOwnerRecord()->paymentMethod === 'elavon'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('Create Iziipay API'))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['key'] = filled($data['key'] ?? null)
                            ? $data['key']
                            : PaymentApi::generateKey();
                        $data['status'] = $data['status'] ?? true;

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('regenerateKey')
                    ->label(__('Regenerate key'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(__('Regenerate source key?'))
                    ->modalDescription(__('Integrations using the current source_key must be updated after regeneration.'))
                    ->action(function (PaymentApi $record): void {
                        $record->update(['key' => PaymentApi::generateKey()]);

                        Notification::make()
                            ->title(__('Source key regenerated'))
                            ->success()
                            ->send();
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
