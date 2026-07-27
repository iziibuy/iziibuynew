<?php

namespace App\Filament\Resources\ExternalOrders\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ExternalOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Order summary'))
                    ->icon(Heroicon::OutlinedShoppingBag)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('id')
                            ->label(__('Order #'))
                            ->weight('bold'),
                        TextEntry::make('uuid')
                            ->label('UUID')
                            ->copyable()
                            ->placeholder('-'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (?string $state): string => match (strtoupper((string) $state)) {
                                'COMPLETED' => 'success',
                                'PENDING' => 'warning',
                                'FAILED', 'CANCELED' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('amount')
                            ->money(fn ($record) => $record->currency ?: 'NOK')
                            ->weight('bold'),
                        TextEntry::make('currency')
                            ->placeholder('-'),
                        TextEntry::make('orderId')
                            ->label(__('Merchant order ID'))
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('description')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('paid_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->placeholder('-'),
                    ]),

                Section::make(__('Customer'))
                    ->icon(Heroicon::OutlinedUser)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('customer_name')
                            ->placeholder('-'),
                        TextEntry::make('customer_email')
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('customer_phone')
                            ->placeholder('-'),
                        TextEntry::make('customer_company')
                            ->placeholder('-'),
                        TextEntry::make('customer_country')
                            ->placeholder('-'),
                        TextEntry::make('customer_post_code')
                            ->placeholder('-'),
                        TextEntry::make('customer_address')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Payment'))
                    ->icon(Heroicon::OutlinedCreditCard)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('payment_method')
                            ->badge()
                            ->placeholder('-'),
                        TextEntry::make('payment_id')
                            ->label(__('Gateway payment / session ID'))
                            ->placeholder('-')
                            ->copyable()
                            ->columnSpanFull(),
                        TextEntry::make('payment_url')
                            ->placeholder('-')
                            ->url(fn (?string $state): ?string => filled($state) ? $state : null)
                            ->openUrlInNewTab()
                            ->columnSpanFull(),
                        TextEntry::make('taxValue')
                            ->placeholder('-'),
                        TextEntry::make('taxTotal')
                            ->placeholder('-'),
                        TextEntry::make('response')
                            ->label(__('Stored gateway response'))
                            ->placeholder('-')
                            ->columnSpanFull()
                            ->prose(),
                    ]),

                Section::make(__('Plugin / button'))
                    ->icon(Heroicon::OutlinedPuzzlePiece)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('paymentMethodAccess.company_name')
                            ->label(__('Plugin'))
                            ->placeholder('-'),
                        TextEntry::make('payment_method_access_id')
                            ->label(__('Plugin ID'))
                            ->placeholder('-'),
                        TextEntry::make('paymentApi.domain')
                            ->label(__('Button domain'))
                            ->placeholder('-'),
                        TextEntry::make('api_id')
                            ->label(__('Payment API ID'))
                            ->placeholder('-'),
                        TextEntry::make('paymentApi.key')
                            ->label(__('Source key'))
                            ->placeholder('-')
                            ->copyable()
                            ->columnSpanFull(),
                        TextEntry::make('source_url')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('success_redirect_url')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('failed_redirect_url')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('group')
                            ->placeholder('-'),
                    ]),

                Section::make(__('Items / raw payload'))
                    ->icon(Heroicon::OutlinedCodeBracket)
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('items')
                            ->label(__('Items'))
                            ->placeholder('-')
                            ->formatStateUsing(function ($state): string {
                                if (blank($state)) {
                                    return '-';
                                }

                                return is_string($state)
                                    ? $state
                                    : json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                            })
                            ->markdown()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
