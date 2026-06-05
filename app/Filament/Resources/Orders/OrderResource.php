<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Filament\Tables\Filters\ResourceTableFilters;
use App\Models\Order;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|\UnitEnum|null $navigationGroup = 'commerce';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?int $globalSearchSort = 5;

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['id', 'payment_id', 'discount_code', 'shop.user_name'];
    }

    /**
     * @return Builder<Order>
     */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('shop');
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        return __('Order').' #'.$record->getKey();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('store_id')
                    ->numeric(),
                TextInput::make('user_id')
                    ->numeric(),
                TextInput::make('shop_id')
                    ->required()
                    ->numeric(),
                TextInput::make('referral_code')
                    ->numeric(),
                TextInput::make('payment_id'),
                Textarea::make('payment_url')
                    ->columnSpanFull(),
                TextInput::make('discount')
                    ->numeric(),
                TextInput::make('discount_code'),
                TextInput::make('subtotal')
                    ->numeric(),
                TextInput::make('tax')
                    ->numeric(),
                TextInput::make('shipping_cost')
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('shipping_method'),
                TextInput::make('total')
                    ->numeric(),
                TextInput::make('payment_method'),
                Toggle::make('status')
                    ->required(),
                TextInput::make('refund')
                    ->numeric(),
                TextInput::make('payment_status')
                    ->numeric(),
                TextInput::make('is_company')
                    ->numeric()
                    ->default(0),
                TextInput::make('currency')
                    ->required()
                    ->default('NOK'),
                TextInput::make('type')
                    ->numeric()
                    ->default(0),
                DatePicker::make('paid_at'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $statusLabels = [
            0 => __('words.status_pending'),
            1 => __('words.status_paid'),
            2 => __('words.status_shipped'),
            3 => __('words.status_canceled'),
            4 => __('words.not_delivered'),
            5 => __('words.delivered'),
        ];

        return $schema
            ->components([
                Section::make(__('Order summary'))
                    ->icon(Heroicon::OutlinedShoppingBag)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('id')
                            ->label(__('Order #'))
                            ->weight('bold'),
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge()
                            ->formatStateUsing(function ($state) use ($statusLabels): string {
                                $key = (int) $state;

                                return $statusLabels[$key] ?? (string) $state;
                            })
                            ->color(function ($state): string {
                                $key = (int) $state;

                                return match ($key) {
                                    1, 5 => 'success',
                                    0, 2 => 'warning',
                                    3, 4 => 'danger',
                                    default => 'gray',
                                };
                            }),
                        TextEntry::make('currency')
                            ->placeholder('-'),
                        TextEntry::make('shop.user_name')
                            ->label(__('Shop'))
                            ->placeholder('-'),
                        TextEntry::make('user.email')
                            ->label(__('Customer email'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Amounts'))
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('subtotal')
                            ->money('NOK')
                            ->placeholder('-'),
                        TextEntry::make('tax')
                            ->money('NOK')
                            ->placeholder('-'),
                        TextEntry::make('shipping_cost')
                            ->money('NOK')
                            ->placeholder('-'),
                        TextEntry::make('discount')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('total')
                            ->money('NOK')
                            ->weight('bold')
                            ->placeholder('-'),
                        TextEntry::make('refund')
                            ->money('NOK')
                            ->placeholder('-'),
                    ]),
                Section::make(__('Payment'))
                    ->icon(Heroicon::OutlinedCreditCard)
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        TextEntry::make('payment_id')
                            ->placeholder('-'),
                        TextEntry::make('payment_method')
                            ->placeholder('-'),
                        TextEntry::make('payment_url')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('payment_status')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('paid_at')
                            ->dateTime()
                            ->placeholder('-'),
                    ]),
                Section::make(__('Meta'))
                    ->icon(Heroicon::OutlinedInformationCircle)
                    ->columns(3)
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('store_id')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('user_id')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('shop_id')
                            ->numeric(),
                        TextEntry::make('referral_code')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('discount_code')
                            ->placeholder('-'),
                        TextEntry::make('shipping_method')
                            ->placeholder('-'),
                        TextEntry::make('is_company')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('type')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->placeholder('-'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('shop.user_name')
                    ->label(__('Shop'))
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('total')
                    ->money('NOK')
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(function ($state): string {
                        $labels = [
                            0 => __('words.status_pending'),
                            1 => __('words.status_paid'),
                            2 => __('words.status_shipped'),
                            3 => __('words.status_canceled'),
                            4 => __('words.not_delivered'),
                            5 => __('words.delivered'),
                        ];
                        $key = (int) $state;

                        return $labels[$key] ?? (string) $state;
                    })
                    ->color(function ($state): string {
                        $key = (int) $state;

                        return match ($key) {
                            1, 5 => 'success',
                            0, 2 => 'warning',
                            3, 4 => 'danger',
                            default => 'gray',
                        };
                    }),
                TextColumn::make('currency')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                ColumnGroup::make(__('Payment'))
                    ->columns([
                        TextColumn::make('payment_method')
                            ->toggleable(isToggledHiddenByDefault: true)
                            ->searchable(),
                        TextColumn::make('payment_id')
                            ->searchable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('payment_status')
                            ->numeric()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('paid_at')
                            ->date()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                    ]),
                ColumnGroup::make(__('IDs'))
                    ->columns([
                        TextColumn::make('store_id')
                            ->numeric()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('user_id')
                            ->numeric()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('shop_id')
                            ->numeric()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('referral_code')
                            ->numeric()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                    ]),
                ColumnGroup::make(__('Amounts'))
                    ->columns([
                        TextColumn::make('subtotal')
                            ->numeric()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('tax')
                            ->numeric()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('shipping_cost')
                            ->money('NOK')
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('shipping_method')
                            ->searchable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('discount')
                            ->numeric()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('discount_code')
                            ->searchable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('refund')
                            ->numeric()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                    ]),
                ColumnGroup::make(__('Meta'))
                    ->columns([
                        TextColumn::make('is_company')
                            ->numeric()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('type')
                            ->numeric()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('updated_at')
                            ->dateTime()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                ResourceTableFilters::orderStatus(),
                ResourceTableFilters::shop(),
                ResourceTableFilters::select('payment_method', [
                    'quickpay' => 'QuickPay',
                    'elavon' => 'Elavon',
                    'surfboard' => 'Surfboard',
                    'vipps' => 'Vipps',
                ], __('Payment method')),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalWidth('5xl')
                    ->modalHeading(fn (Order $record): string => __('Order').' #'.$record->getKey()),
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
            'index' => ManageOrders::route('/'),
        ];
    }
}
