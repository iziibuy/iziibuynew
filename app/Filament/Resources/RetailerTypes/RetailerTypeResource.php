<?php

namespace App\Filament\Resources\RetailerTypes;

use App\Filament\Resources\RetailerTypes\Pages\CreateRetailerType;
use App\Filament\Resources\RetailerTypes\Pages\EditRetailerType;
use App\Filament\Resources\RetailerTypes\Pages\ListRetailerTypes;
use App\Models\RetailerType;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RetailerTypeResource extends Resource
{
    protected static ?string $model = RetailerType::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Retailer types';

    protected static ?string $modelLabel = 'Retailer type';

    protected static ?string $pluralModelLabel = 'Retailer types';

    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General')
                    ->schema([
                        TextInput::make('label')
                            ->required()
                            ->maxLength(255),
                    ]),
                Section::make('Commission')
                    ->schema([
                        TextInput::make('one_time_pay_out')
                            ->label('One time payout')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('NOK'),
                        TextInput::make('commission_from_recurring_payments')
                            ->label('Commission from recurring payments')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('%'),
                        TextInput::make('commission_from_sales')
                            ->label('Commission from sales')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('%'),
                    ])
                    ->columns(3),
                Section::make('Sub-retailer commission')
                    ->schema([
                        TextInput::make('sub_retailer_one_time_pay_out')
                            ->label('Sub-retailer one time payout')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('NOK'),
                        TextInput::make('sub_retailer_commission_from_recurring_payments')
                            ->label('Sub-retailer commission from recurring payments')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('%'),
                        TextInput::make('sub_retailer_commission_from_sales')
                            ->label('Sub-retailer commission from sales')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('%'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('one_time_pay_out')
                    ->label('One time payout')
                    ->numeric()
                    ->suffix(' NOK')
                    ->sortable(),
                TextColumn::make('commission_from_recurring_payments')
                    ->label('Recurring %')
                    ->numeric()
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('commission_from_sales')
                    ->label('Sales %')
                    ->numeric()
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('label')
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
            'index' => ListRetailerTypes::route('/'),
            'create' => CreateRetailerType::route('/create'),
            'edit' => EditRetailerType::route('/{record}/edit'),
        ];
    }
}
