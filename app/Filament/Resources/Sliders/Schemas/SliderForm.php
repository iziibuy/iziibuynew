<?php

namespace App\Filament\Resources\Sliders\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SliderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('shop_id')
                    ->relationship('shop', 'user_name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label(__('Shop')),
                FileUpload::make('image')
                    ->image()
                    ->directory('sliders')
                    ->required(),
                TextInput::make('heading')
                    ->maxLength(255),
                Textarea::make('text')
                    ->columnSpanFull(),
                TextInput::make('url')
                    ->url()
                    ->maxLength(2048),
                TextInput::make('button')
                    ->maxLength(255),
            ]);
    }
}
