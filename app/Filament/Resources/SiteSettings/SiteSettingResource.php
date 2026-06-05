<?php

namespace App\Filament\Resources\SiteSettings;

use App\Filament\Resources\SiteSettings\Pages\ManageSiteSettings;
use App\Filament\Tables\Filters\ResourceTableFilters;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class SiteSettingResource extends Resource
{
    protected static ?string $model = SiteSetting::class;

    protected static string|\UnitEnum|null $navigationGroup = 'site';

    protected static ?int $navigationSort = 5;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $modelLabel = 'setting';

    protected static ?string $pluralModelLabel = 'settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $recordTitleAttribute = 'key';

    protected static ?int $globalSearchSort = 90;

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['key', 'label'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->label(__('Key')),
                TextInput::make('label')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->options([
                        'text' => __('Text'),
                        'textarea' => __('Text Area'),
                        'number' => __('Number'),
                        'checkbox' => __('Checkbox'),
                        'file' => __('File'),
                        'image' => __('Image'),
                        'select_dropdown' => __('Select Dropdown'),
                    ])
                    ->required()
                    ->default('text')
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('value', null)),

                KeyValue::make('details')
                    ->label(__('Dropdown Options'))
                    ->keyLabel(__('Value'))
                    ->valueLabel(__('Label'))
                    ->addActionLabel(__('Add Option'))
                    ->visible(fn (Get $get): bool => $get('type') === 'select_dropdown'),

                TextInput::make('value')
                    ->label(__('Value'))
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('type') === 'text')
                    ->dehydrated(fn (Get $get): bool => $get('type') === 'text'),
                Textarea::make('value')
                    ->label(__('Value'))
                    ->rows(4)
                    ->visible(fn (Get $get): bool => $get('type') === 'textarea')
                    ->dehydrated(fn (Get $get): bool => $get('type') === 'textarea'),
                TextInput::make('value')
                    ->label(__('Value'))
                    ->numeric()
                    ->visible(fn (Get $get): bool => $get('type') === 'number')
                    ->dehydrated(fn (Get $get): bool => $get('type') === 'number'),
                Checkbox::make('value')
                    ->label(__('Enabled'))
                    ->visible(fn (Get $get): bool => $get('type') === 'checkbox')
                    ->dehydrated(fn (Get $get): bool => $get('type') === 'checkbox'),
                FileUpload::make('value')
                    ->label(__('File'))
                    ->disk('public')
                    ->directory('site-settings')
                    ->visible(fn (Get $get): bool => $get('type') === 'file')
                    ->dehydrated(fn (Get $get): bool => $get('type') === 'file'),
                FileUpload::make('value')
                    ->label(__('Image'))
                    ->image()
                    ->disk('public')
                    ->directory('site-settings')
                    ->visible(fn (Get $get): bool => $get('type') === 'image')
                    ->dehydrated(fn (Get $get): bool => $get('type') === 'image'),
                Select::make('value')
                    ->label(__('Value'))
                    ->options(fn (Get $get): array => $get('details') ?? [])
                    ->visible(fn (Get $get): bool => $get('type') === 'select_dropdown')
                    ->dehydrated(fn (Get $get): bool => $get('type') === 'select_dropdown'),

                TextInput::make('group_name')
                    ->default('general')
                    ->maxLength(255)
                    ->label(__('Group')),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('label')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('group_name')
                    ->label(__('Group'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->groups([
                Group::make('group_name')
                    ->label(__('Section'))
                    ->collapsible(),
            ])
            ->defaultGroup('group_name')
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('group_name')
                    ->label(__('Group'))
                    ->options(fn (): array => SiteSetting::query()
                        ->distinct()
                        ->orderBy('group_name')
                        ->pluck('group_name', 'group_name')
                        ->all()),
                ResourceTableFilters::select('type', [
                    'text' => __('Text'),
                    'textarea' => __('Text Area'),
                    'number' => __('Number'),
                    'checkbox' => __('Checkbox'),
                    'file' => __('File'),
                    'image' => __('Image'),
                    'select_dropdown' => __('Select Dropdown'),
                ], __('Type')),
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
            'index' => ManageSiteSettings::route('/'),
        ];
    }
}
