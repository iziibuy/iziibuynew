<?php

namespace App\Filament\Resources\Charges;

use App\Filament\Resources\Charges\Pages\ManageCharges;
use App\Filament\Resources\Charges\Pages\ViewCharge;
use App\Filament\Resources\Charges\Schemas\ChargeInfolist;
use App\Filament\Tables\Filters\ResourceTableFilters;
use App\Models\Charge;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ChargeResource extends Resource
{
    protected static ?string $model = Charge::class;

    protected static string|\UnitEnum|null $navigationGroup = 'commerce';

    protected static ?int $navigationSort = 40;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $recordTitleAttribute = 'order_id';

    protected static ?int $globalSearchSort = 45;

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['order_id', 'payment_type', 'details', 'comment'];
    }

    /**
     * @return Builder<Charge>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('shop');

        if (request()->has('demo')) {
            $query->where('is_demo', request()->boolean('demo'));
        }

        return $query;
    }

    public static function infolist(Schema $schema): Schema
    {
        return ChargeInfolist::configure($schema);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('shop_id')
                    ->required()
                    ->numeric(),
                TextInput::make('order_id')
                    ->required(),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                RichEditor::make('details')
                    ->columnSpanFull(),
                RichEditor::make('comment')
                    ->columnSpanFull(),
                Toggle::make('status')
                    ->required(),
                Toggle::make('is_demo')
                    ->required(),
                Select::make('payment_type')
                    ->options(['Unresolved' => 'Unresolved', 'Test' => 'Test', 'Real' => 'Real'])
                    ->required(),
                Textarea::make('payment_body')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('shop_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('order_id')
                    ->searchable(),
                TextColumn::make('amount')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('status')
                    ->boolean(),
                IconColumn::make('is_demo')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_type')
                    ->badge(),
            ])
            ->filters([
                ResourceTableFilters::shop(),
                ResourceTableFilters::select('payment_type', [
                    'Unresolved' => 'Unresolved',
                    'Test' => 'Test',
                    'Real' => 'Real',
                ], __('Payment type')),
                ResourceTableFilters::boolean('status', __('Paid')),
            ])
            ->recordActions([
                ViewAction::make(),
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
            'index' => ManageCharges::route('/'),
            'view' => ViewCharge::route('/{record}'),
        ];
    }
}
