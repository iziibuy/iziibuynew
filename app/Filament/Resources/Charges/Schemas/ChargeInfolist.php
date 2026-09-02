<?php

namespace App\Filament\Resources\Charges\Schemas;

use App\Filament\Resources\Shops\ShopResource;
use App\Models\Charge;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ChargeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Charge summary'))
                    ->icon(Heroicon::OutlinedCreditCard)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('id')
                            ->label(__('Charge #'))
                            ->weight('bold'),
                        TextEntry::make('order_id')
                            ->label(__('Order ID'))
                            ->copyable()
                            ->placeholder('-'),
                        TextEntry::make('amount')
                            ->money('NOK')
                            ->weight('bold'),
                        TextEntry::make('status')
                            ->label(__('Paid'))
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? __('Paid') : __('Unpaid'))
                            ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                        TextEntry::make('payment_type')
                            ->label(__('Payment type'))
                            ->badge()
                            ->placeholder('-')
                            ->color(fn (?string $state): string => match ($state) {
                                'Real' => 'success',
                                'Test' => 'warning',
                                'Unresolved' => 'danger',
                                default => 'gray',
                            }),
                        IconEntry::make('is_demo')
                            ->label(__('Demo'))
                            ->boolean(),
                        TextEntry::make('comment')
                            ->label(__('Type'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('created_at')
                            ->label(__('Charged at'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->placeholder('-'),
                    ]),

                Section::make(__('Shop'))
                    ->icon(Heroicon::OutlinedBuildingStorefront)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('shop.user_name')
                            ->label(__('Shop'))
                            ->placeholder('-')
                            ->url(fn (Charge $record): ?string => $record->shop
                                ? ShopResource::getUrl('view', ['record' => $record->shop])
                                : null)
                            ->color(fn (Charge $record): ?string => $record->shop ? 'primary' : null),
                        TextEntry::make('shop_id')
                            ->label(__('Shop ID')),
                        IconEntry::make('shop.status')
                            ->label(__('Shop active'))
                            ->boolean(),
                        TextEntry::make('shop.subscriptionMethod')
                            ->label(__('Subscription method'))
                            ->placeholder('-'),
                        TextEntry::make('shop.monthly_cost')
                            ->label(__('Shop monthly cost'))
                            ->money('NOK')
                            ->placeholder('-'),
                        TextEntry::make('shop.paid_at')
                            ->label(__('Shop last paid at'))
                            ->dateTime()
                            ->placeholder('-'),
                    ]),

                Section::make(__('Payment'))
                    ->icon(Heroicon::OutlinedCreditCard)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('lastFour')
                            ->label(__('Card last 4'))
                            ->placeholder('-'),
                        TextEntry::make('paymentBodyObj.metadata.brand')
                            ->label(__('Card brand'))
                            ->placeholder('-')
                            ->badge(),
                        TextEntry::make('paymentBodyObj.state')
                            ->label(__('Gateway state'))
                            ->placeholder('-')
                            ->badge(),
                        TextEntry::make('paymentBodyObj.currency')
                            ->label(__('Gateway currency'))
                            ->placeholder('-'),
                    ]),

                Section::make(__('Raw gateway payload'))
                    ->icon(Heroicon::OutlinedCodeBracket)
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('payment_body')
                            ->hiddenLabel()
                            ->placeholder('-')
                            ->formatStateUsing(fn (?string $state): string => self::prettyJson($state))
                            ->markdown()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Charge snapshot / details'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('details')
                            ->hiddenLabel()
                            ->placeholder('-')
                            ->formatStateUsing(fn (?string $state): string => self::prettyJson($state))
                            ->markdown()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected static function prettyJson(?string $state): string
    {
        if (blank($state)) {
            return '-';
        }

        $decoded = json_decode($state);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $state;
        }

        return '```json'.PHP_EOL.json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL.'```';
    }
}
