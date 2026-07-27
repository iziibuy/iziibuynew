<?php

namespace App\Filament\Resources\ExternalBookings\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ExternalBookingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Booking summary'))
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('id')
                            ->label(__('Booking #'))
                            ->weight('bold'),
                        TextEntry::make('ulid')
                            ->label('ULID')
                            ->copyable()
                            ->placeholder('-'),
                        TextEntry::make('booking_number')
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (?string $state): string => match (strtoupper((string) $state)) {
                                'COMPLETED' => 'success',
                                'PENDING' => 'warning',
                                'FAILED', 'CANCELED' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('payment_status')
                            ->badge()
                            ->color(fn (?string $state): string => match (strtoupper((string) $state)) {
                                'PAID' => 'success',
                                'PENDING' => 'warning',
                                default => 'gray',
                            }),
                        TextEntry::make('phone_number')
                            ->placeholder('-')
                            ->copyable(),
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

                Section::make(__('Amounts'))
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('subtotal')
                            ->formatStateUsing(fn ($state, $record): string => self::formatMoney($state, $record->currency))
                            ->placeholder('-'),
                        TextEntry::make('tax')
                            ->formatStateUsing(fn ($state, $record): string => self::formatMoney($state, $record->currency))
                            ->placeholder('-'),
                        TextEntry::make('total')
                            ->formatStateUsing(fn ($state, $record): string => self::formatMoney($state, $record->currency))
                            ->weight('bold')
                            ->placeholder('-'),
                        TextEntry::make('currency')
                            ->placeholder('-'),
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
                        TextEntry::make('elavon_transaction_id')
                            ->label(__('Elavon transaction ID'))
                            ->placeholder('-')
                            ->copyable()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Plugin'))
                    ->icon(Heroicon::OutlinedPuzzlePiece)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('paymentMethodAccess.company_name')
                            ->label(__('Plugin'))
                            ->placeholder('-'),
                        TextEntry::make('payment_method_access_id')
                            ->label(__('Plugin ID'))
                            ->placeholder('-'),
                        TextEntry::make('paymentMethodAccess.company_email')
                            ->label(__('Plugin email'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Customer details'))
                    ->icon(Heroicon::OutlinedUser)
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('customer_details')
                            ->label(__('Customer details'))
                            ->placeholder('-')
                            ->formatStateUsing(fn ($state): string => self::formatJson($state))
                            ->markdown()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Service details'))
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('service_details')
                            ->label(__('Service details'))
                            ->placeholder('-')
                            ->formatStateUsing(fn ($state): string => self::formatJson($state))
                            ->markdown()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected static function formatMoney(mixed $state, ?string $currency): string
    {
        if ($state === null || $state === '') {
            return '-';
        }

        $amount = is_numeric($state) ? (float) $state : (float) (string) $state;

        return number_format($amount, 2).' '.($currency ?: 'NOK');
    }

    protected static function formatJson(mixed $state): string
    {
        if (blank($state)) {
            return '-';
        }

        return is_string($state)
            ? $state
            : '```json'."\n".json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n".'```';
    }
}
