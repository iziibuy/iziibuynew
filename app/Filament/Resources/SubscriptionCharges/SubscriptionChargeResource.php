<?php

namespace App\Filament\Resources\SubscriptionCharges;

use App\Filament\Resources\SubscriptionCharges\Pages\ManageSubscriptionCharges;
use App\Filament\Resources\SubscriptionCharges\Tables\SubscriptionChargesTable;
use App\Models\SubscriptionCharge;
use BackedEnum;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class SubscriptionChargeResource extends Resource
{
    protected static ?string $model = SubscriptionCharge::class;

    protected static string|\UnitEnum|null $navigationGroup = 'commerce';

    protected static ?int $navigationSort = 50;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $recordTitleAttribute = 'quickpay_order_id';

    protected static ?int $globalSearchSort = 43;

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['id', 'quickpay_order_id', 'subscription_id'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        $quickpay = $record->getAttribute('quickpay_order_id');
        if (filled($quickpay)) {
            return (string) $quickpay;
        }

        return __('Subscription charge').' #'.$record->getKey();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('subscription_id')
                    ->required()
                    ->numeric(),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                Toggle::make('status')
                    ->required(),
                TextInput::make('quickpay_order_id'),
                RichEditor::make('payment_details')
                    ->columnSpanFull(),
                RichEditor::make('charge_details')
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('subscription_id')
                    ->numeric(),
                TextEntry::make('amount')
                    ->numeric(),
                IconEntry::make('status')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('quickpay_order_id')
                    ->placeholder('-'),
                TextEntry::make('payment_details')
                    ->placeholder('-')
                    ->prose()
                    ->columnSpanFull(),
                TextEntry::make('charge_details')
                    ->placeholder('-')
                    ->prose()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return SubscriptionChargesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSubscriptionCharges::route('/'),
        ];
    }
}
