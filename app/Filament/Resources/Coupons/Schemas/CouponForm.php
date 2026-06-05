<?php

namespace App\Filament\Resources\Coupons\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CouponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Coupon details'))
                    ->columns(2)
                    ->schema([
                        Select::make('shop_id')
                            ->relationship('shop', 'user_name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->label(__('Shop'))
                            ->placeholder(__('Platform coupon'))
                            ->helperText(__('Leave empty for platform-wide shop signup coupons.'))
                            ->columnSpanFull(),
                        TextInput::make('code')
                            ->label(__('Code'))
                            ->required()
                            ->maxLength(30),
                        TextInput::make('discount')
                            ->label(__('Discount amount'))
                            ->numeric()
                            ->required()
                            ->minValue(0),
                        DatePicker::make('expire_at')
                            ->label(__('Expire at'))
                            ->required()
                            ->native(false),
                        TextInput::make('limit')
                            ->label(__('Usage limit'))
                            ->numeric()
                            ->required()
                            ->minValue(0),
                        TextInput::make('minimum_cart')
                            ->label(__('Minimum cart'))
                            ->numeric()
                            ->required()
                            ->minValue(0),
                    ]),
            ]);
    }
}
