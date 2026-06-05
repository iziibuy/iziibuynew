<?php

namespace App\Filament\Resources\Languages;

use App\Filament\Resources\Languages\Pages\ManageLanguages;
use App\Filament\Tables\Filters\ResourceTableFilters;
use App\Models\Language;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LanguageResource extends Resource
{
    protected static ?string $model = Language::class;

    protected static string|\UnitEnum|null $navigationGroup = 'support';

    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static ?string $recordTitleAttribute = 'key';

    protected static ?int $globalSearchSort = 80;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key'),
                Textarea::make('english')
                    ->columnSpanFull(),
                Textarea::make('spanish')
                    ->columnSpanFull(),
                Textarea::make('norwegian')
                    ->columnSpanFull(),
                Toggle::make('shopCanEdit')
                    ->required(),
                Textarea::make('english_options')
                    ->columnSpanFull(),
                Textarea::make('spanish_options')
                    ->columnSpanFull(),
                Textarea::make('norwegian_options')
                    ->columnSpanFull(),
                TextInput::make('help'),
                Textarea::make('swedish')
                    ->columnSpanFull(),
                Textarea::make('swedish_options')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('shopCanEdit')
                    ->boolean(),
                TextColumn::make('help')
                    ->searchable(),
            ])
            ->filters([
                ResourceTableFilters::boolean('shopCanEdit', __('Shop can edit')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLanguages::route('/'),
        ];
    }
}
