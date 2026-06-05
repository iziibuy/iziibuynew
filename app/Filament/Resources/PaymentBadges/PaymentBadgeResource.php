<?php

namespace App\Filament\Resources\PaymentBadges;

use App\Facades\IziibuyFacades;
use App\Filament\Resources\PaymentBadges\Pages\ManagePaymentBadges;
use App\Models\PaymentMethod;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentBadgeResource extends Resource
{
    protected static ?string $model = PaymentMethod::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|\UnitEnum|null $navigationGroup = 'site';

    protected static ?int $navigationSort = 41;

    protected static ?string $navigationLabel = 'Payment badges';

    protected static ?string $modelLabel = 'payment badge';

    protected static ?string $pluralModelLabel = 'payment badges';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'payment-badges';

    protected static ?int $globalSearchSort = 82;

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                FileUpload::make('image')
                    ->image()
                    ->directory('payment-methods')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                ImageColumn::make('image')
                    ->getStateUsing(fn (PaymentMethod $record): ?string => filled($record->image) ? IziibuyFacades::image($record->image) : null),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('image_status')
                    ->label(__('Image'))
                    ->options([
                        'with' => __('With image'),
                        'without' => __('Without image'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'with' => $query->whereNotNull('image')->where('image', '!=', ''),
                            'without' => $query->where(fn (Builder $query): Builder => $query->whereNull('image')->orWhere('image', '')),
                            default => $query,
                        };
                    }),
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
            'index' => ManagePaymentBadges::route('/'),
        ];
    }
}
