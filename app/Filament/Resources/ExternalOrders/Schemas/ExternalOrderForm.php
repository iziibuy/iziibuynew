<?php

namespace App\Filament\Resources\ExternalOrders\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExternalOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Status & payment'))
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->options([
                                'PENDING' => 'PENDING',
                                'FAILED' => 'FAILED',
                                'COMPLETED' => 'COMPLETED',
                                'CANCELED' => 'CANCELED',
                            ])
                            ->required(),
                        TextInput::make('payment_method')
                            ->required(),
                        TextInput::make('payment_id'),
                        TextInput::make('payment_url')
                            ->url()
                            ->columnSpanFull(),
                        DateTimePicker::make('paid_at'),
                        Textarea::make('response')
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Customer'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('customer_name'),
                        TextInput::make('customer_email')
                            ->email(),
                        TextInput::make('customer_phone')
                            ->tel(),
                        TextInput::make('customer_company'),
                        TextInput::make('customer_country'),
                        TextInput::make('customer_post_code'),
                        TextInput::make('customer_address')
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Amounts'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('amount')
                            ->numeric()
                            ->required(),
                        TextInput::make('currency')
                            ->required(),
                        TextInput::make('taxValue'),
                        TextInput::make('taxTotal'),
                        TextInput::make('orderId'),
                        TextInput::make('description')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
