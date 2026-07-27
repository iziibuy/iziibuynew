<?php

namespace App\Filament\Resources\ExternalOrders;

use App\Filament\Resources\ExternalOrders\Pages\EditExternalOrder;
use App\Filament\Resources\ExternalOrders\Pages\ListExternalOrders;
use App\Filament\Resources\ExternalOrders\Pages\ViewExternalOrder;
use App\Filament\Resources\ExternalOrders\Schemas\ExternalOrderForm;
use App\Filament\Resources\ExternalOrders\Schemas\ExternalOrderInfolist;
use App\Filament\Resources\ExternalOrders\Tables\ExternalOrdersTable;
use App\Models\ExternalOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExternalOrderResource extends Resource
{
    protected static ?string $model = ExternalOrder::class;

    protected static string|\UnitEnum|null $navigationGroup = 'commerce';

    protected static ?int $navigationSort = 15;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Button payments';

    protected static ?string $modelLabel = 'Button payment order';

    protected static ?string $pluralModelLabel = 'Button payment orders';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $slug = 'button-payment-orders';

    protected static ?int $globalSearchSort = 20;

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return [
            'id',
            'uuid',
            'orderId',
            'payment_id',
            'customer_name',
            'customer_email',
            'description',
        ];
    }

    /**
     * @return Builder<ExternalOrder>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['paymentMethodAccess', 'paymentApi']);
    }

    /**
     * @return Builder<ExternalOrder>
     */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['paymentMethodAccess', 'paymentApi']);
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        return __('Button order').' #'.$record->getKey();
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var ExternalOrder $record */
        return array_filter([
            __('Customer') => $record->customer_name,
            __('Status') => $record->status,
            __('Plugin') => $record->paymentMethodAccess?->company_name,
        ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ExternalOrderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ExternalOrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExternalOrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExternalOrders::route('/'),
            'view' => ViewExternalOrder::route('/{record}'),
            'edit' => EditExternalOrder::route('/{record}/edit'),
        ];
    }
}
