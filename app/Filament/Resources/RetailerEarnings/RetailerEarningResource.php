<?php

namespace App\Filament\Resources\RetailerEarnings;

use App\Filament\Resources\RetailerEarnings\Pages\ManageRetailerEarnings;
use App\Filament\Tables\Filters\ResourceTableFilters;
use App\Models\RetailerEarning;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RetailerEarningResource extends Resource
{
    protected static ?string $model = RetailerEarning::class;

    protected static string|\UnitEnum|null $navigationGroup = 'retailers';

    protected static ?int $navigationSort = 30;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?int $globalSearchSort = 75;

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['id', 'method', 'comments', 'transaction_type', 'shop.user_name', 'user.email'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['user', 'shop']);
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        return __('Retailer earning').' #'.$record->getKey();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                TextInput::make('shop_id')
                    ->required()
                    ->numeric(),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                TextInput::make('method')
                    ->required(),
                RichEditor::make('comments')
                    ->columnSpanFull(),
                Select::make('transaction_type')
                    ->options(['Add' => 'Add', 'Remove' => 'Remove'])
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('shop_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('method')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('transaction_type')
                    ->badge(),
            ])
            ->filters([
                ResourceTableFilters::shop(),
                ResourceTableFilters::select('transaction_type', [
                    'Add' => 'Add',
                    'Remove' => 'Remove',
                ], __('Transaction type')),
                ResourceTableFilters::select('method', [
                    'one_time_pay_out' => __('One time payout'),
                    'commission_from_recurring_payments' => __('Recurring commission'),
                    'commission_from_sales' => __('Sales commission'),
                ], __('Method')),
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
            'index' => ManageRetailerEarnings::route('/'),
        ];
    }
}
