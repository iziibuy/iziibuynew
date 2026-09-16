<?php

namespace App\Filament\Resources\PaymentMethodAccesses\Schemas;

use App\Filament\Resources\Users\UserResource;
use App\Models\PaymentMethodAccess;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PaymentMethodAccessInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Plugin summary'))
                    ->icon(Heroicon::OutlinedPuzzlePiece)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('company_name')
                            ->label(__('Company'))
                            ->weight('bold'),
                        TextEntry::make('company_email')
                            ->label(__('Email'))
                            ->copyable()
                            ->placeholder('-'),
                        TextEntry::make('company_domain')
                            ->label(__('Domain'))
                            ->url(fn (?string $state): ?string => filled($state) ? $state : null)
                            ->openUrlInNewTab()
                            ->placeholder('-'),
                        TextEntry::make('status')
                            ->label(__('Active'))
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? __('Active') : __('Inactive'))
                            ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                        TextEntry::make('paymentMethod')
                            ->label(__('Gateway'))
                            ->badge()
                            ->placeholder('-'),
                        IconEntry::make('is_demo')
                            ->label(__('Demo'))
                            ->boolean(),
                        TextEntry::make('elavonResubscriptionMessage')
                            ->label(__('Notice'))
                            ->placeholder('-')
                            ->color('danger')
                            ->columnSpanFull(),
                        TextEntry::make('key')
                            ->label(__('Key'))
                            ->copyable()
                            ->placeholder('-'),
                        TextEntry::make('kyc_status')
                            ->label(__('KYC status'))
                            ->badge()
                            ->placeholder('-'),
                        TextEntry::make('contract_status')
                            ->label(__('Contract status'))
                            ->badge()
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->placeholder('-'),
                    ]),

                Section::make(__('Owner'))
                    ->icon(Heroicon::OutlinedUser)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.email')
                            ->label(__('Owner'))
                            ->placeholder('-')
                            ->url(fn (PaymentMethodAccess $record): ?string => $record->user_id
                                ? UserResource::getUrl('edit', ['record' => $record->user_id])
                                : null)
                            ->color(fn (PaymentMethodAccess $record): ?string => $record->user_id ? 'primary' : null),
                        TextEntry::make('addressFull')
                            ->label(__('Address'))
                            ->placeholder('-'),
                    ]),

                Section::make(__('Billing'))
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('fee')
                            ->label(__('Monthly fee'))
                            ->money('NOK'),
                        TextEntry::make('last_paid_at')
                            ->label(__('Last paid at'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('subscriptionMethod')
                            ->label(__('Subscription method'))
                            ->placeholder('-'),
                        TextEntry::make('subscription.status')
                            ->label(__('Platform subscription'))
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => $state ? __('Active') : __('Inactive'))
                            ->color(fn (?string $state): string => $state ? 'success' : 'danger'),
                        TextEntry::make('subscription.key')
                            ->label(__('Stored card'))
                            ->placeholder('-'),
                        TextEntry::make('subscription.paid_at')
                            ->label(__('Subscription paid at'))
                            ->dateTime()
                            ->placeholder('-'),
                    ]),
            ]);
    }
}
