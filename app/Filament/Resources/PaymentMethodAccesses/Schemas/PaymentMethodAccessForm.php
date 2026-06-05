<?php

namespace App\Filament\Resources\PaymentMethodAccesses\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentMethodAccessForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Company'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('company_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('company_email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('company_domain')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('company_registration')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('company_address')
                            ->required()
                            ->maxLength(255),
                    ]),
                Section::make(__('Subscription'))
                    ->columns(2)
                    ->schema([
                        Select::make('user_id')
                            ->relationship('user', 'email')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('key')
                            ->label(__('Plugin key'))
                            ->maxLength(255),
                        TextInput::make('fee')
                            ->numeric()
                            ->default(0),
                        Select::make('paymentMethod')
                            ->label(__('Payment method'))
                            ->options([
                                'quickpay' => 'QuickPay',
                                'elavon' => 'Elavon',
                                'surfboard' => 'Surfboard',
                            ])
                            ->required(),
                        Select::make('subscriptionMethod')
                            ->label(__('Subscription method'))
                            ->options([
                                'quickpay' => 'QuickPay',
                                'elavon' => 'Elavon',
                                'surfboard' => 'Surfboard',
                            ])
                            ->required(),
                        DateTimePicker::make('last_paid_at')
                            ->label(__('Last paid at'))
                            ->native(false),
                    ]),
                Section::make(__('Status'))
                    ->columns(3)
                    ->schema([
                        Toggle::make('status')
                            ->label(__('Active')),
                        Toggle::make('contract_signed')
                            ->label(__('Contract signed')),
                        Toggle::make('contract_status')
                            ->label(__('Contract status')),
                        Toggle::make('kyc_status')
                            ->label(__('KYC status')),
                    ]),
            ]);
    }
}
