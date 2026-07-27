<?php

namespace App\Filament\Resources\ExternalBookings;

use App\Filament\Resources\ExternalBookings\Pages\EditExternalBooking;
use App\Filament\Resources\ExternalBookings\Pages\ListExternalBookings;
use App\Filament\Resources\ExternalBookings\Pages\ViewExternalBooking;
use App\Filament\Resources\ExternalBookings\Schemas\ExternalBookingForm;
use App\Filament\Resources\ExternalBookings\Schemas\ExternalBookingInfolist;
use App\Filament\Resources\ExternalBookings\Tables\ExternalBookingsTable;
use App\Models\ExternalBooking;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExternalBookingResource extends Resource
{
    protected static ?string $model = ExternalBooking::class;

    protected static string|\UnitEnum|null $navigationGroup = 'commerce';

    protected static ?int $navigationSort = 16;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Plugin bookings';

    protected static ?string $modelLabel = 'Plugin booking';

    protected static ?string $pluralModelLabel = 'Plugin bookings';

    protected static ?string $recordTitleAttribute = 'booking_number';

    protected static ?string $slug = 'plugin-bookings';

    protected static ?int $globalSearchSort = 21;

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return [
            'id',
            'ulid',
            'booking_number',
            'phone_number',
            'payment_id',
        ];
    }

    /**
     * @return Builder<ExternalBooking>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['paymentMethodAccess']);
    }

    /**
     * @return Builder<ExternalBooking>
     */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['paymentMethodAccess']);
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        /** @var ExternalBooking $record */
        return $record->booking_number
            ? (string) $record->booking_number
            : __('Booking').' #'.$record->getKey();
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var ExternalBooking $record */
        return array_filter([
            __('Phone') => $record->phone_number,
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
        return ExternalBookingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ExternalBookingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExternalBookingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExternalBookings::route('/'),
            'view' => ViewExternalBooking::route('/{record}'),
            'edit' => EditExternalBooking::route('/{record}/edit'),
        ];
    }
}
