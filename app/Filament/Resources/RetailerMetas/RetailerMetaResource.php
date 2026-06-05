<?php

namespace App\Filament\Resources\RetailerMetas;

use App\Filament\Pages\RetailerReportPage;
use App\Filament\Pages\RetailerWithdrawalsPage;
use App\Filament\Resources\RetailerMetas\Pages\CreateRetailer;
use App\Filament\Resources\RetailerMetas\Pages\EditRetailerMeta;
use App\Filament\Resources\RetailerMetas\Pages\ManageRetailerMetas;
use App\Filament\Resources\RetailerMetas\Pages\ViewRetailerMeta;
use App\Filament\Resources\Users\UserResource;
use App\Models\RetailerMeta;
use App\Models\User;
use BackedEnum;
use Error;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RetailerMetaResource extends Resource
{
    protected static ?string $model = RetailerMeta::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|\UnitEnum|null $navigationGroup = 'retailers';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Retailers';

    protected static ?string $modelLabel = 'Retailer';

    protected static ?string $pluralModelLabel = 'Retailers';

    protected static ?int $globalSearchSort = 25;

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['user.email', 'user.name', 'user.last_name', 'user.phone'];
    }

    /**
     * @return Builder<RetailerMeta>
     */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('user');
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        if (! $record instanceof RetailerMeta) {
            return null;
        }

        return $record->user?->name ?? $record->user?->email ?? (string) $record->getKey();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'retailerType', 'parent']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('tax')
                    ->numeric()
                    ->nullable(),
                TextInput::make('tax_number')
                    ->nullable(),
                TextInput::make('bank_account_number')
                    ->nullable(),
                TextInput::make('qr')
                    ->label('QR path')
                    ->nullable(),
                Select::make('type')
                    ->relationship('retailerType', 'label')
                    ->required(),
                Select::make('parent_id')
                    ->label('Parent user')
                    ->relationship('parent', 'email')
                    ->searchable()
                    ->nullable(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Overview'))
                    ->icon(Heroicon::OutlinedUser)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('user.name')
                            ->label(__('Name'))
                            ->weight('bold'),
                        TextEntry::make('user.email')
                            ->label(__('Email')),
                        TextEntry::make('user.phone')
                            ->label(__('Phone'))
                            ->placeholder('—'),
                        TextEntry::make('parent_display')
                            ->label(__('Parent'))
                            ->state(fn (RetailerMeta $record): string => self::formatParent($record))
                            ->placeholder('N/A'),
                        TextEntry::make('retailerType.label')
                            ->label(__('Type'))
                            ->placeholder('—'),
                        TextEntry::make('total_earning')
                            ->label(__('Total earning'))
                            ->state(fn (RetailerMeta $record): float => (float) ($record->user?->totalEarning() ?? 0))
                            ->numeric(decimalPlaces: 1)
                            ->suffix(' NOK'),
                        TextEntry::make('total_balance')
                            ->label(__('Total balance'))
                            ->state(fn (RetailerMeta $record): float => (float) ($record->user?->totalBalance() ?? 0))
                            ->numeric(decimalPlaces: 1)
                            ->suffix(' NOK'),
                    ]),
                Section::make(__('Banking & tax'))
                    ->icon(Heroicon::OutlinedBuildingLibrary)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('tax')
                            ->placeholder('—'),
                        TextEntry::make('tax_number')
                            ->placeholder('—'),
                        TextEntry::make('bank_account_number')
                            ->placeholder('—'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ViewColumn::make('qr_code')
                    ->label(__('QR Code'))
                    ->view('filament.tables.columns.retailer-qr-code'),
                TextColumn::make('user.name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label(__('Email'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('parent_display')
                    ->label(__('Parent'))
                    ->state(fn (RetailerMeta $record): string => self::formatParent($record))
                    ->html()
                    ->wrap(),
                TextColumn::make('user.phone')
                    ->label(__('Phone'))
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('total_earning')
                    ->label(__('Total Earning'))
                    ->state(fn (RetailerMeta $record): float => (float) ($record->user?->totalEarning() ?? 0))
                    ->numeric(decimalPlaces: 1)
                    ->sortable(false),
                TextColumn::make('total_balance')
                    ->label(__('Total Balance'))
                    ->state(fn (RetailerMeta $record): float => (float) ($record->user?->totalBalance() ?? 0))
                    ->numeric(decimalPlaces: 1)
                    ->sortable(false),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                ActionGroup::make([
                    Action::make('withdrawals')
                        ->label(__('Withdrawals'))
                        ->icon(Heroicon::OutlinedBanknotes)
                        ->url(fn (RetailerMeta $record): string => RetailerWithdrawalsPage::getUrl().'?user='.$record->user_id),
                    Action::make('withdrawBalance')
                        ->label(__('Withdraw Balance'))
                        ->icon(Heroicon::OutlinedArrowDownCircle)
                        ->modalHeading(__('Withdraw Balance'))
                        ->modalSubmitActionLabel(__('Withdraw'))
                        ->visible(fn (RetailerMeta $record): bool => $record->user_id !== null)
                        ->schema(fn (RetailerMeta $record): array => [
                            TextInput::make('amount')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->maxValue(max(0, (float) ($record->user?->totalBalance() ?? 0)))
                                ->helperText(__('Available balance: :balance NOK', ['balance' => $record->user?->totalBalance() ?? 0])),
                            TextInput::make('trnx_id')
                                ->label(__('Trnx ID'))
                                ->maxLength(50),
                            DatePicker::make('date')
                                ->required()
                                ->default(now()),
                        ])
                        ->action(function (RetailerMeta $record, array $data): void {
                            self::placeWithdrawal($record->user, $data);
                        }),
                    Action::make('report')
                        ->label(__('Report'))
                        ->icon(Heroicon::OutlinedChartBar)
                        ->url(fn (RetailerMeta $record): string => RetailerReportPage::getUrl(['user' => $record->user_id])),
                    Action::make('editProfile')
                        ->label(__('Edit Profile'))
                        ->icon(Heroicon::OutlinedUserCircle)
                        ->url(fn (RetailerMeta $record): string => UserResource::getUrl('edit', ['record' => $record->user_id]))
                        ->visible(fn (RetailerMeta $record): bool => $record->user_id !== null),
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make()
                        ->using(function (Model $record): bool {
                            if (! $record instanceof RetailerMeta) {
                                return false;
                            }

                            $user = $record->user;
                            $record->delete();
                            $user?->delete();

                            return true;
                        }),
                ])
                    ->label(__('Actions'))
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->color('gray')
                    ->button()
                    ->dropdownPlacement('bottom-end'),
            ])
            ->recordActionsColumnLabel(__('Actions'))
            ->toolbarActions([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRetailerMetas::route('/'),
            'create' => CreateRetailer::route('/create'),
            'view' => ViewRetailerMeta::route('/{record}'),
            'edit' => EditRetailerMeta::route('/{record}/edit'),
        ];
    }

    public static function formatParent(RetailerMeta $record): string
    {
        if ($record->parent_id === null) {
            return 'N/A';
        }

        $parent = $record->parent;

        if ($parent === null) {
            return 'N/A';
        }

        return implode('<br>', [
            'Id: '.$record->parent_id,
            'Name: '.trim($parent->name.' '.$parent->last_name),
            'Email: '.$parent->email,
        ]);
    }

    /**
     * @param  array{amount: int|float|string, trnx_id?: ?string, date: string}  $data
     */
    public static function placeWithdrawal(?User $user, array $data): void
    {
        if ($user === null) {
            Notification::make()
                ->title(__('Retailer user not found'))
                ->danger()
                ->send();

            return;
        }

        if ($user->totalBalance() <= 0) {
            Notification::make()
                ->title(__('No balance available to withdraw'))
                ->danger()
                ->send();

            return;
        }

        if ((int) $data['amount'] >= $user->totalBalance()) {
            Notification::make()
                ->title(__('Amount must be less than available balance'))
                ->danger()
                ->send();

            return;
        }

        try {
            $withdrawal = $user->withdraw((int) $data['amount']);
            $withdrawal->createMetas([
                'trnx_id' => $data['trnx_id'] ?? null,
                'date' => $data['date'],
            ]);

            Notification::make()
                ->title(__('Withdrawal request placed'))
                ->success()
                ->send();
        } catch (Exception|Error $e) {
            Notification::make()
                ->title($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
