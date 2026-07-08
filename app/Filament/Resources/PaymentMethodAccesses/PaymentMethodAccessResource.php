<?php

namespace App\Filament\Resources\PaymentMethodAccesses;

use App\Filament\Resources\PaymentMethodAccesses\Pages\CreatePaymentMethodAccess;
use App\Filament\Resources\PaymentMethodAccesses\Pages\EditPaymentMethodAccess;
use App\Filament\Resources\PaymentMethodAccesses\Pages\ListPaymentMethodAccesses;
use App\Filament\Resources\PaymentMethodAccesses\RelationManagers\PaymentapisRelationManager;
use App\Filament\Resources\PaymentMethodAccesses\Schemas\PaymentMethodAccessForm;
use App\Filament\Resources\PaymentMethodAccesses\Tables\PaymentMethodAccessesTable;
use App\Models\PaymentMethodAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentMethodAccessResource extends Resource
{
    protected static ?string $model = PaymentMethodAccess::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?string $navigationLabel = 'Plugins';

    protected static ?string $modelLabel = 'Plugin';

    protected static ?string $pluralModelLabel = 'Plugins';

    protected static ?string $recordTitleAttribute = 'company_name';

    protected static ?string $slug = 'plugins';

    protected static ?int $globalSearchSort = 84;

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['company_name', 'company_email', 'company_domain', 'key'];
    }

    /**
     * @return Builder<PaymentMethodAccess>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'paymentapis']);
    }

    public static function form(Schema $schema): Schema
    {
        return PaymentMethodAccessForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentMethodAccessesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PaymentapisRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentMethodAccesses::route('/'),
            'create' => CreatePaymentMethodAccess::route('/create'),
            'edit' => EditPaymentMethodAccess::route('/{record}/edit'),
        ];
    }
}
