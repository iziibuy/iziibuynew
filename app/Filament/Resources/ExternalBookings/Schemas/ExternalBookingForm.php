<?php

namespace App\Filament\Resources\ExternalBookings\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExternalBookingForm
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
                                'COMPLETED' => 'COMPLETED',
                                'FAILED' => 'FAILED',
                                'CANCELED' => 'CANCELED',
                            ])
                            ->required(),
                        Select::make('payment_status')
                            ->options([
                                'PENDING' => 'PENDING',
                                'PAID' => 'PAID',
                            ])
                            ->required(),
                        TextInput::make('payment_method')
                            ->required(),
                        TextInput::make('payment_id'),
                        TextInput::make('payment_url')
                            ->url()
                            ->columnSpanFull(),
                        DateTimePicker::make('paid_at'),
                    ]),
                Section::make(__('Booking'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('booking_number'),
                        TextInput::make('phone_number')
                            ->tel(),
                        TextInput::make('subtotal')
                            ->numeric(),
                        TextInput::make('tax')
                            ->numeric(),
                        TextInput::make('total')
                            ->numeric()
                            ->required(),
                        TextInput::make('currency')
                            ->required(),
                    ]),
            ]);
    }
}
