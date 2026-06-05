<?php

namespace App\Filament\Resources\Shops;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Shops\Pages\AdvanceEditShop;
use App\Filament\Resources\Shops\Pages\CreateShop;
use App\Filament\Resources\Shops\Pages\EditShop;
use App\Filament\Resources\Shops\Pages\ListShops;
use App\Filament\Resources\Shops\Pages\ViewShop;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Tables\Filters\ResourceTableFilters;
use App\Models\Shop;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ShopResource extends Resource
{
    protected static ?string $model = Shop::class;

    protected static string|\UnitEnum|null $navigationGroup = 'commerce';

    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $recordTitleAttribute = 'user_name';

    protected static ?int $globalSearchSort = 35;

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['user_name', 'area', 'payment_order_id', 'subscription_id', 'shopperId'];
    }

    /**
     * @return Builder<Shop>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['user.paymentMethodAccess']);

        if (request()->boolean('active')) {
            $query->where('is_demo', false);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                TextInput::make('retailer_id')
                    ->numeric(),
                TextInput::make('user_name')
                    ->required(),
                RichEditor::make('terms')
                    ->columnSpanFull(),
                TextInput::make('payment_order_id'),
                TextInput::make('tax')
                    ->numeric(),
                Toggle::make('status'),
                TextInput::make('subscription_id')
                    ->required(),
                Textarea::make('payment_url')
                    ->columnSpanFull(),
                Toggle::make('establishment'),
                TextInput::make('establishment_cost')
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('monthly_cost')
                    ->numeric()
                    ->prefix('$'),
                Toggle::make('service_establishment'),
                TextInput::make('service_establishment_cost')
                    ->numeric()
                    ->default(0)
                    ->prefix('$'),
                TextInput::make('service_monthly_fee')
                    ->numeric()
                    ->default(0),
                Toggle::make('can_provide_service'),
                TextInput::make('per_user_fee')
                    ->numeric(),
                TextInput::make('locations'),
                TextInput::make('selling_location_mode')
                    ->numeric(),
                Toggle::make('contract_signed'),
                Toggle::make('contract_status'),
                TextInput::make('default_currency')
                    ->default('NOK'),
                Textarea::make('currencies')
                    ->columnSpanFull(),
                Textarea::make('country')
                    ->columnSpanFull(),
                Textarea::make('default_language')
                    ->columnSpanFull(),
                Textarea::make('contract_url')
                    ->columnSpanFull(),
                TextInput::make('area_id')
                    ->numeric(),
                Toggle::make('store_as_pickup_point'),
                DateTimePicker::make('paid_at'),
                TextInput::make('area'),
                Toggle::make('is_demo')
                    ->required(),
                TextInput::make('previous_retailer')
                    ->numeric(),
                DatePicker::make('retailer_joined_at'),
                DatePicker::make('previous_retailer_suspended_at'),
                TextInput::make('shopperId'),
                TextInput::make('subscriptionMethod')
                    ->required()
                    ->default('quickpay'),
                TextInput::make('paymentMethod')
                    ->required()
                    ->default('quickpay'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Overview'))
                    ->icon(Heroicon::OutlinedBuildingStorefront)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('user_name')
                            ->label(__('Shop slug'))
                            ->weight('bold')
                            ->copyable()
                            ->columnSpanFull(),
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->formatStateUsing(fn (?bool $state): string => $state ? __('Active') : __('Deactive'))
                            ->badge()
                            ->color(fn (?bool $state): string => $state ? 'success' : 'danger'),
                        TextEntry::make('is_demo')
                            ->label(__('Demo / Live'))
                            ->formatStateUsing(fn (bool $state): string => $state ? __('Demo') : __('Live'))
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'warning' : 'success'),
                        TextEntry::make('paymentMethod')
                            ->label(__('Payment method'))
                            ->placeholder('—'),
                        TextEntry::make('user_id')
                            ->label(__('User id'))
                            ->numeric(),
                        TextEntry::make('retailer_id')
                            ->label(__('Retailer id'))
                            ->numeric()
                            ->placeholder('—'),
                        TextEntry::make('area')
                            ->placeholder('—'),
                        TextEntry::make('area_id')
                            ->label(__('Area id'))
                            ->numeric()
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label(__('Created at'))
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('updated_at')
                            ->label(__('Updated at'))
                            ->dateTime()
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Shop statistics'))
                    ->icon(Heroicon::OutlinedChartBar)
                    ->columns([
                        'default' => 2,
                        'md' => 3,
                        'xl' => 5,
                    ])
                    ->schema([
                        TextEntry::make('orders_count')
                            ->label(__('Total orders'))
                            ->numeric()
                            ->weight('bold'),
                        TextEntry::make('view_stats_total_earnings')
                            ->label(__('Total earnings'))
                            ->money('NOK')
                            ->weight('bold'),
                        TextEntry::make('parent_products_count')
                            ->label(__('Total products'))
                            ->numeric()
                            ->weight('bold'),
                        TextEntry::make('view_stats_month_orders')
                            ->label(__('This month orders'))
                            ->numeric()
                            ->weight('bold'),
                        TextEntry::make('view_stats_month_earnings')
                            ->label(__('This month earnings'))
                            ->money('NOK')
                            ->weight('bold'),
                    ]),
                Section::make(__('Subscription & billing'))
                    ->icon(Heroicon::OutlinedCreditCard)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('subscription_id')
                            ->label(__('Subscription id'))
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('subscriptionMethod')
                            ->label(__('Subscription method'))
                            ->placeholder('—'),
                        TextEntry::make('payment_order_id')
                            ->label(__('Payment order id'))
                            ->placeholder('—'),
                        TextEntry::make('monthly_cost')
                            ->money('NOK')
                            ->placeholder('—'),
                        TextEntry::make('tax')
                            ->numeric()
                            ->placeholder('—'),
                        TextEntry::make('paid_at')
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('payment_url')
                            ->label(__('Payment url'))
                            ->placeholder('—')
                            ->limit(60)
                            ->url(fn (?string $state): ?string => filled($state) ? $state : null)
                            ->openUrlInNewTab()
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Fees & services'))
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->columns(3)
                    ->collapsed()
                    ->schema([
                        IconEntry::make('establishment')
                            ->boolean()
                            ->placeholder('—'),
                        TextEntry::make('establishment_cost')
                            ->money('NOK')
                            ->placeholder('—'),
                        IconEntry::make('service_establishment')
                            ->boolean()
                            ->placeholder('—'),
                        TextEntry::make('service_establishment_cost')
                            ->money('NOK')
                            ->placeholder('—'),
                        TextEntry::make('service_monthly_fee')
                            ->numeric()
                            ->placeholder('—'),
                        IconEntry::make('can_provide_service')
                            ->boolean()
                            ->placeholder('—'),
                        TextEntry::make('per_user_fee')
                            ->numeric()
                            ->placeholder('—'),
                    ]),
                Section::make(__('Contract & location'))
                    ->icon(Heroicon::OutlinedGlobeAlt)
                    ->columns(3)
                    ->collapsed()
                    ->schema([
                        IconEntry::make('contract_signed')
                            ->boolean()
                            ->placeholder('—'),
                        IconEntry::make('contract_status')
                            ->boolean()
                            ->placeholder('—'),
                        TextEntry::make('default_currency')
                            ->placeholder('—'),
                        TextEntry::make('default_language')
                            ->placeholder('—'),
                        TextEntry::make('country')
                            ->placeholder('—'),
                        TextEntry::make('selling_location_mode')
                            ->numeric()
                            ->placeholder('—'),
                        TextEntry::make('locations')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('currencies')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('contract_url')
                            ->placeholder('—')
                            ->limit(60)
                            ->url(fn (?string $state): ?string => filled($state) ? $state : null)
                            ->openUrlInNewTab()
                            ->columnSpanFull(),
                        IconEntry::make('store_as_pickup_point')
                            ->boolean()
                            ->placeholder('—'),
                    ]),
                Section::make(__('Retailer history'))
                    ->icon(Heroicon::OutlinedClock)
                    ->columns(3)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('previous_retailer')
                            ->numeric()
                            ->placeholder('—'),
                        TextEntry::make('retailer_joined_at')
                            ->date()
                            ->placeholder('—'),
                        TextEntry::make('previous_retailer_suspended_at')
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('shopperId')
                            ->label(__('Shopper id'))
                            ->placeholder('—'),
                    ]),
                Section::make(__('Terms'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('terms')
                            ->placeholder('—')
                            ->prose()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ViewColumn::make('qr_code')
                    ->label(__('Qr Code'))
                    ->view('filament.tables.columns.shop-qr-code'),
                TextColumn::make('previous_retailer_suspended_at')
                    ->label(__('Previous Retailer Suspended At'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('user_id')
                    ->label(__('User Id'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('user_name')
                    ->label(__('Shop Slug'))
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->formatStateUsing(fn (?bool $state): string => $state ? __('Active') : __('Deactive'))
                    ->badge()
                    ->color(fn (?bool $state): string => $state ? 'success' : 'danger')
                    ->sortable(),
                TextColumn::make('subscription_id')
                    ->label(__('Subscription Id'))
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('area_id')
                    ->label(__('Area Id'))
                    ->numeric()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('area')
                    ->label(__('Area'))
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('is_demo')
                    ->label(__('Demo / Live'))
                    ->formatStateUsing(fn (bool $state): string => $state ? __('Demo') : __('Live'))
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'warning' : 'success')
                    ->sortable(),
                TextColumn::make('kyc_status')
                    ->label(__('Kyc Status'))
                    ->state(function (Shop $record): string {
                        if ($record->needKYC) {
                            return __('Pending');
                        }

                        $kycStatus = $record->user?->paymentMethodAccess?->kyc_status;

                        if ($kycStatus === true || $kycStatus === 1) {
                            return __('Approved');
                        }

                        return '—';
                    })
                    ->placeholder('—'),
                TextColumn::make('paymentMethod')
                    ->label(__('PaymentMethod'))
                    ->searchable()
                    ->placeholder('—'),
                ColumnGroup::make(__('More details'))
                    ->columns([
                        TextColumn::make('monthly_cost')
                            ->money('NOK')
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('retailer_id')
                            ->numeric()
                            ->sortable()
                            ->placeholder('—')
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('payment_order_id')
                            ->searchable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('shopperId')
                            ->label('Shopper ID')
                            ->searchable()
                            ->toggleable(isToggledHiddenByDefault: true),
                    ]),
                ColumnGroup::make(__('Fees & services'))
                    ->columns([
                        TextColumn::make('tax')
                            ->numeric()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        IconColumn::make('establishment')
                            ->boolean()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('establishment_cost')
                            ->money('NOK')
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        IconColumn::make('service_establishment')
                            ->boolean()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('service_establishment_cost')
                            ->money('NOK')
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('service_monthly_fee')
                            ->numeric()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        IconColumn::make('can_provide_service')
                            ->boolean()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('per_user_fee')
                            ->numeric()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('paid_at')
                            ->dateTime()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                    ]),
                ColumnGroup::make(__('Locations & selling'))
                    ->columns([
                        TextColumn::make('locations')
                            ->searchable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('selling_location_mode')
                            ->numeric()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        IconColumn::make('store_as_pickup_point')
                            ->boolean()
                            ->toggleable(isToggledHiddenByDefault: true),
                    ]),
                ColumnGroup::make(__('Contract & currency'))
                    ->columns([
                        IconColumn::make('contract_signed')
                            ->boolean()
                            ->toggleable(isToggledHiddenByDefault: true),
                        IconColumn::make('contract_status')
                            ->boolean()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('default_currency')
                            ->searchable()
                            ->toggleable(isToggledHiddenByDefault: true),
                    ]),
                ColumnGroup::make(__('Retailer history'))
                    ->columns([
                        TextColumn::make('previous_retailer')
                            ->numeric()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('retailer_joined_at')
                            ->date()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                    ]),
                ColumnGroup::make(__('Integration'))
                    ->columns([
                        TextColumn::make('subscriptionMethod')
                            ->searchable()
                            ->toggleable(isToggledHiddenByDefault: true),
                    ]),
                ColumnGroup::make(__('Timestamps'))
                    ->columns([
                        TextColumn::make('updated_at')
                            ->dateTime()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                    ]),
            ])
            ->filters([
                ResourceTableFilters::boolean('is_demo', __('Demo / Live')),
                ResourceTableFilters::boolean('status', __('Active')),
                ResourceTableFilters::shopArea(),
                ResourceTableFilters::select('paymentMethod', [
                    'quickpay' => 'QuickPay',
                    'elavon' => 'Elavon',
                    'surfboard' => 'Surfboard',
                ], __('Payment method')),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('advanceEdit')
                        ->label('Advance Edit')
                        ->icon(Heroicon::OutlinedPencilSquare)
                        ->url(fn (Shop $record): string => ShopResource::getUrl('advance-edit', ['record' => $record])),
                    Action::make('visitShop')
                        ->label('Visit Shop')
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->url(fn (Shop $record): string => route('shop.home', ['user_name' => $record->user_name]))
                        ->openUrlInNewTab(),
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                    Action::make('exportProducts')
                        ->label('Export Products')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->url(fn (Shop $record): string => route('admin.shop_product_export_by_admin', $record))
                        ->openUrlInNewTab(),
                    Action::make('editOwnerProfile')
                        ->label('Edit Owner Profile')
                        ->icon(Heroicon::OutlinedUserCircle)
                        ->color('danger')
                        ->url(fn (Shop $record): string => UserResource::getUrl('edit', ['record' => $record->user_id]))
                        ->openUrlInNewTab()
                        ->visible(fn (Shop $record): bool => $record->user_id !== null),
                    Action::make('productList')
                        ->label('Product List')
                        ->icon(Heroicon::OutlinedCube)
                        ->color('warning')
                        ->url(fn (Shop $record): string => ProductResource::getUrl('index').'?shop='.$record->id)
                        ->openUrlInNewTab(),
                ])
                    ->label(__('Actions'))
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->color('gray')
                    ->button()
                    ->dropdownPlacement('bottom-end'),
            ])
            ->recordActionsColumnLabel(__('Actions'))
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShops::route('/'),
            'create' => CreateShop::route('/create'),
            'view' => ViewShop::route('/{record}'),
            'edit' => EditShop::route('/{record}/edit'),
            'advance-edit' => AdvanceEditShop::route('/{record}/advance-edit'),
        ];
    }
}
