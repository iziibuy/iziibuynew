<?php

namespace App\Filament\Resources\Products;

use App\Facades\IziibuyFacades;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Filament\Tables\Filters\ResourceTableFilters;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|\UnitEnum|null $navigationGroup = 'commerce';

    protected static ?int $navigationSort = 30;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $globalSearchSort = 40;

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'sku', 'item', 'ean'];
    }

    /**
     * @return Builder<Product>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('shop');

        $shopId = request()->integer('shop');

        if ($shopId > 0) {
            $query->where('shop_id', $shopId);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Overview'))
                    ->icon(Heroicon::OutlinedCube)
                    ->columns(3)
                    ->schema([
                        Select::make('shop_id')
                            ->relationship('shop', 'user_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->label(__('Shop')),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        TextInput::make('sku')
                            ->label('SKU')
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->maxLength(255),
                        TextInput::make('item')
                            ->maxLength(255),
                        TextInput::make('ean')
                            ->maxLength(255),
                        TextInput::make('parent_id')
                            ->numeric()
                            ->label(__('Parent product id')),
                        Toggle::make('status')
                            ->label(__('Published'))
                            ->default(true),
                        Toggle::make('featured'),
                    ]),
                Section::make(__('Pricing & stock'))
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->columns(3)
                    ->schema([
                        TextInput::make('price')
                            ->numeric()
                            ->prefix('NOK')
                            ->required(),
                        TextInput::make('saleprice')
                            ->numeric()
                            ->prefix('NOK'),
                        TextInput::make('retailerprice')
                            ->numeric()
                            ->prefix('NOK'),
                        TextInput::make('retailersaleprice')
                            ->numeric()
                            ->prefix('NOK'),
                        TextInput::make('tax')
                            ->numeric()
                            ->suffix('%'),
                        TextInput::make('discount')
                            ->numeric(),
                        TextInput::make('quantity')
                            ->numeric()
                            ->default(0),
                        TextInput::make('sale_count')
                            ->numeric()
                            ->default(1),
                    ]),
                Section::make(__('Content'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->schema([
                        RichEditor::make('description')
                            ->columnSpanFull(),
                        RichEditor::make('details')
                            ->columnSpanFull(),
                        Textarea::make('areas')
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Media'))
                    ->icon(Heroicon::OutlinedPhoto)
                    ->schema([
                        FileUpload::make('image')
                            ->image()
                            ->directory('products')
                            ->columnSpanFull(),
                        Textarea::make('images')
                            ->label(__('Additional images (JSON)'))
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Dimensions & variations'))
                    ->icon(Heroicon::OutlinedSquare3Stack3d)
                    ->columns(3)
                    ->collapsed()
                    ->schema([
                        Toggle::make('is_variable'),
                        TextInput::make('variation'),
                        TextInput::make('length')
                            ->numeric(),
                        TextInput::make('width')
                            ->numeric(),
                        TextInput::make('height')
                            ->numeric(),
                        TextInput::make('weight')
                            ->numeric(),
                    ]),
                Section::make(__('Other'))
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->columns(3)
                    ->collapsed()
                    ->schema([
                        TextInput::make('view')
                            ->numeric()
                            ->default(0)
                            ->label(__('Views')),
                        TextInput::make('order_no')
                            ->numeric(),
                        TextInput::make('qrcode'),
                        Toggle::make('pin'),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Overview'))
                    ->icon(Heroicon::OutlinedCube)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')
                            ->weight('bold')
                            ->columnSpanFull(),
                        TextEntry::make('shop.user_name')
                            ->label(__('Shop'))
                            ->placeholder('—'),
                        TextEntry::make('sku')
                            ->label('SKU')
                            ->badge()
                            ->placeholder('—'),
                        TextEntry::make('slug')
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('item')
                            ->placeholder('—'),
                        TextEntry::make('ean')
                            ->placeholder('—'),
                        TextEntry::make('parent_id')
                            ->label(__('Parent product id'))
                            ->numeric()
                            ->placeholder('—'),
                        IconEntry::make('status')
                            ->label(__('Published'))
                            ->boolean()
                            ->placeholder('—'),
                        IconEntry::make('featured')
                            ->boolean()
                            ->placeholder('—'),
                        TextEntry::make('view')
                            ->numeric()
                            ->label(__('Views')),
                    ]),
                Section::make(__('Pricing & stock'))
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('price')
                            ->money('NOK')
                            ->placeholder('—'),
                        TextEntry::make('saleprice')
                            ->money('NOK')
                            ->placeholder('—'),
                        TextEntry::make('retailerprice')
                            ->money('NOK')
                            ->placeholder('—'),
                        TextEntry::make('retailersaleprice')
                            ->money('NOK')
                            ->placeholder('—'),
                        TextEntry::make('quantity')
                            ->numeric()
                            ->placeholder('—'),
                        TextEntry::make('sale_count')
                            ->numeric()
                            ->placeholder('—'),
                        TextEntry::make('tax')
                            ->suffix('%')
                            ->placeholder('—'),
                        TextEntry::make('discount')
                            ->numeric()
                            ->placeholder('—'),
                    ]),
                Section::make(__('Content'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->collapsible()
                    ->schema([
                        TextEntry::make('description')
                            ->placeholder('—')
                            ->prose()
                            ->columnSpanFull(),
                        TextEntry::make('details')
                            ->placeholder('—')
                            ->prose()
                            ->columnSpanFull(),
                        TextEntry::make('areas')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Media'))
                    ->icon(Heroicon::OutlinedPhoto)
                    ->schema([
                        ImageEntry::make('image')
                            ->getStateUsing(fn (Product $record): ?string => filled($record->image) ? IziibuyFacades::image($record->image) : null)
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('images')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Dimensions & variations'))
                    ->icon(Heroicon::OutlinedSquare3Stack3d)
                    ->columns(3)
                    ->collapsed()
                    ->schema([
                        IconEntry::make('is_variable')
                            ->boolean()
                            ->placeholder('—'),
                        TextEntry::make('variation')
                            ->placeholder('—'),
                        TextEntry::make('length')
                            ->numeric()
                            ->placeholder('—'),
                        TextEntry::make('width')
                            ->numeric()
                            ->placeholder('—'),
                        TextEntry::make('height')
                            ->numeric()
                            ->placeholder('—'),
                        TextEntry::make('weight')
                            ->numeric()
                            ->placeholder('—'),
                        TextEntry::make('qrcode')
                            ->placeholder('—'),
                        TextEntry::make('order_no')
                            ->numeric()
                            ->placeholder('—'),
                        IconEntry::make('pin')
                            ->boolean()
                            ->placeholder('—'),
                    ]),
                Section::make(__('Timestamps'))
                    ->icon(Heroicon::OutlinedClock)
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->placeholder('—'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->getStateUsing(fn (Product $record): ?string => filled($record->image) ? IziibuyFacades::image($record->image) : null),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('shop.user_name')
                    ->label(__('Shop'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
                TextColumn::make('price')
                    ->money('NOK')
                    ->sortable(),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('status')
                    ->label(__('Published'))
                    ->boolean(),
                IconColumn::make('featured')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('item')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ean')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('saleprice')
                    ->money('NOK')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('view')
                    ->label(__('Views'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                ResourceTableFilters::shop(),
                ResourceTableFilters::boolean('status', __('Published')),
                ResourceTableFilters::boolean('featured', __('Featured')),
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
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'view' => ViewProduct::route('/{record}'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
