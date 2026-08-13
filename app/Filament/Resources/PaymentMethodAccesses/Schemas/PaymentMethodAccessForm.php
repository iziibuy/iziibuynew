<?php

namespace App\Filament\Resources\PaymentMethodAccesses\Schemas;

use App\Constants\Constants;
use App\Models\PaymentMethodAccess;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PaymentMethodAccessForm
{
    /**
     * @return array<int, string>
     */
    public static function metaFieldNames(): array
    {
        return [
            'elavon_merchant_alias',
            'elavon_public_key',
            'elavon_secret_key',
            'surfboard_terminalId',
            'surfboard_mit_terminalId',
            'surfboard_merchantId',
            'surfboard_storeId',
        ];
    }

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
                            ->unique(PaymentMethodAccess::class, 'company_domain', ignoreRecord: true)
                            ->columnSpanFull(),
                        TextInput::make('company_registration')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('contract_url')
                            ->label(__('Contract URL'))
                            ->url()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Address'))
                    ->columns(3)
                    ->schema([
                        TextInput::make('company_address.city')
                            ->label(__('City'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('company_address.street')
                            ->label(__('Street'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('company_address.zip')
                            ->label(__('Zip'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('company_address.contact_number')
                            ->label(__('words.company_phone'))
                            ->tel()
                            ->maxLength(255),
                        Select::make('company_address.country')
                            ->label(__('words.invoice_country'))
                            ->options(array_combine(Constants::COUNTRIES, Constants::COUNTRIES))
                            ->searchable()
                            ->columnSpan(2),
                    ]),
                Section::make(__('Payment & gateway'))
                    ->description(__('Choose the gateway and enter its API credentials below.'))
                    ->columns(2)
                    ->schema([
                        Select::make('paymentMethod')
                            ->label(__('Payment method'))
                            ->options([
                                'quickpay' => 'QuickPay',
                                'elavon' => 'Elavon',
                                'surfboard' => 'Surfboard',
                            ])
                            ->required()
                            ->live()
                            ->columnSpanFull(),
                        Select::make('site_mode')
                            ->label(__('words.site_mode'))
                            ->options([
                                'live' => 'live',
                                'test' => 'test',
                            ])
                            ->default('live'),
                        TextInput::make('quickpay_api_key')
                            ->label(__('words.shop_api_kay'))
                            ->maxLength(255)
                            ->unique(PaymentMethodAccess::class, 'quickpay_api_key', ignoreRecord: true)
                            ->copyable()
                            ->visible(fn (Get $get): bool => $get('paymentMethod') === 'quickpay'),
                        TextInput::make('quickpay_secret_key')
                            ->label(__('words.shop_secrate_key'))
                            ->maxLength(255)
                            ->unique(PaymentMethodAccess::class, 'quickpay_secret_key', ignoreRecord: true)
                            ->copyable()
                            ->visible(fn (Get $get): bool => $get('paymentMethod') === 'quickpay'),
                        TextInput::make('elavon_merchant_alias')
                            ->label(__('words.elavon_merchant_alias'))
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $get('paymentMethod') === 'elavon'),
                        TextInput::make('elavon_public_key')
                            ->label(__('words.elavon_public_key'))
                            ->maxLength(255)
                            ->copyable()
                            ->visible(fn (Get $get): bool => $get('paymentMethod') === 'elavon'),
                        TextInput::make('elavon_secret_key')
                            ->label(__('words.elavon_secret_key'))
                            ->maxLength(255)
                            ->copyable()
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => $get('paymentMethod') === 'elavon'),
                        TextInput::make('surfboard_terminalId')
                            ->label(__('words.surfboard_terminalId'))
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $get('paymentMethod') === 'surfboard'),
                        TextInput::make('surfboard_mit_terminalId')
                            ->label(__('Surfboard MIT terminal ID'))
                            ->helperText(__('MerchantInitiated terminal used for subscription renewals. Leave blank to clear.'))
                            ->maxLength(255)
                            ->nullable()
                            ->dehydrated(fn (Get $get): bool => str_contains((string) $get('paymentMethod'), 'surfboard'))
                            ->visible(fn (Get $get): bool => str_contains((string) $get('paymentMethod'), 'surfboard')),
                        TextInput::make('surfboard_merchantId')
                            ->label(__('words.surfboard_merchantId'))
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $get('paymentMethod') === 'surfboard'),
                        TextInput::make('surfboard_storeId')
                            ->label(__('words.surfboard_storeId'))
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $get('paymentMethod') === 'surfboard'),
                    ]),
                Section::make(__('Subscription & plugin'))
                    ->description(__('Plugin access, billing, and subscription settings.'))
                    ->columns(2)
                    ->schema([
                        Select::make('user_id')
                            ->relationship('user', 'email')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('key')
                            ->label(__('Plugin key'))
                            ->required()
                            ->maxLength(255)
                            ->unique(PaymentMethodAccess::class, 'key', ignoreRecord: true)
                            ->copyable()
                            ->helperText(__('Used in Iziipay API URLs: /api/iziipay/create-payment/{plugin_key}')),
                        Select::make('subscriptionMethod')
                            ->label(__('Subscription method'))
                            ->options([
                                'quickpay' => 'QuickPay',
                                'elavon' => 'Elavon',
                                'surfboard' => 'Surfboard',
                            ])
                            ->required(),
                        TextInput::make('fee')
                            ->numeric()
                            ->default(0),
                        TextInput::make('shopperId')
                            ->label(__('Shopper ID'))
                            ->maxLength(255),
                        DateTimePicker::make('last_paid_at')
                            ->label(__('Last paid at'))
                            ->native(false),
                    ]),
                Section::make(__('Platform subscription (Elavon)'))
                    ->description(__('Controls which iziibuy platform Elavon credentials are used when this plugin subscribes. Demo uses sandbox keys from .env; live uses production keys from .env.'))
                    ->schema([
                        Toggle::make('is_demo')
                            ->label(__('Demo subscription (Elavon sandbox)'))
                            ->helperText(__('Uses ELAVON_ENTERPRISE_SANDBOX_* from .env. When off, uses ELAVON_ENTERPRISE_* production keys.'))
                            ->default(false),
                    ])
                    ->columnSpanFull(),
                Section::make(__('Status'))
                    ->columns(4)
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
