<?php

namespace App\Filament\Resources\Users;

use App\Facades\IziibuyFacades;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\UnitEnum|null $navigationGroup = 'people';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $globalSearchSort = 20;

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'last_name', 'phone'];
    }

    /**
     * @return Builder<User>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['role', 'metas']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('last_name'),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('tax_id'),
                TextInput::make('shop_id')
                    ->numeric(),
                Select::make('role_id')
                    ->relationship('role', 'name'),
                TextInput::make('avatar')
                    ->default('users/default.png'),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->unique(User::class, 'email', ignoreRecord: true),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->maxLength(255),
                TextInput::make('pt_package_id')
                    ->numeric(),
                TextInput::make('pt_trainer_id')
                    ->numeric(),
                TextInput::make('pt_package_price')
                    ->numeric()
                    ->prefix('$'),
                Textarea::make('pt_package_purchase_history')
                    ->columnSpanFull(),
                Toggle::make('pt_free_tier')
                    ->required(),
                TextInput::make('service_type')
                    ->required()
                    ->default('both'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('last_name')
                    ->placeholder('-'),
                TextEntry::make('phone')
                    ->placeholder('-'),
                TextEntry::make('tax_id')
                    ->placeholder('-'),
                TextEntry::make('shop_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('role.name')
                    ->label('Role')
                    ->placeholder('-'),
                TextEntry::make('avatar')
                    ->placeholder('-'),
                TextEntry::make('email')
                    ->label('Email address'),
                TextEntry::make('email_verified_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('pt_package_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('pt_trainer_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('pt_package_price')
                    ->money()
                    ->placeholder('-'),
                TextEntry::make('pt_package_purchase_history')
                    ->placeholder('-')
                    ->columnSpanFull(),
                IconEntry::make('pt_free_tier')
                    ->boolean(),
                TextEntry::make('service_type'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('last_name')
                    ->label(__('Last name'))
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label(__('Phone'))
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('tax_id')
                    ->label(__('Tax id'))
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label(__('Created at'))
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                TextColumn::make('role.name')
                    ->label(__('Role'))
                    ->formatStateUsing(fn (?string $state): string => match (strtolower((string) $state)) {
                        'user' => __('Customer'),
                        default => ucfirst((string) $state),
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->state(function (User $record): ?string {
                        $status = $record->metas->firstWhere('column_name', 'status')?->column_value;

                        return filled($status) ? (string) $status : null;
                    })
                    ->placeholder('—'),
                ImageColumn::make('avatar')
                    ->label(__('Avatar'))
                    ->circular()
                    ->getStateUsing(fn (User $record): ?string => filled($record->avatar) ? IziibuyFacades::image($record->avatar) : null),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('role_id')
                    ->label(__('Role'))
                    ->relationship('role', 'name')
                    ->searchable()
                    ->preload(),
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
            'index' => ManageUsers::route('/'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
