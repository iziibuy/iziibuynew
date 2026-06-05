<?php

declare(strict_types=1);

namespace App\Filament\Resources\CmsMenus;

use App\Enums\MenuContext;
use App\Filament\Resources\CmsMenuItems\Pages\MenuBuilder;
use App\Filament\Resources\CmsMenus\Pages\ManageCmsMenus;
use App\Filament\Tables\Filters\ResourceTableFilters;
use App\Models\CmsMenu;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CmsMenuResource extends Resource
{
    protected static ?string $model = CmsMenu::class;

    protected static string|\UnitEnum|null $navigationGroup = 'site';

    protected static ?int $navigationSort = 19;

    protected static ?string $navigationLabel = 'Menus';

    protected static ?string $modelLabel = 'menu';

    protected static ?string $pluralModelLabel = 'menus';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $globalSearchSort = 70;

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'location'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, callable $set, Get $get): void {
                        if (filled($get('slug'))) {
                            return;
                        }

                        $set('slug', Str::slug((string) $state));
                    }),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('context')
                    ->options(MenuContext::class)
                    ->default(MenuContext::Frontend)
                    ->required()
                    ->live(),
                TextInput::make('location')
                    ->maxLength(255)
                    ->placeholder('header, footer, …')
                    ->label(__('Location'))
                    ->helperText(__('For frontend menus: where to render (e.g. header).')),
                Toggle::make('is_active')
                    ->label(__('Active'))
                    ->default(true),
                Toggle::make('replaces_panel_navigation')
                    ->label(__('Replace admin sidebar'))
                    ->helperText(__('When enabled, this admin menu becomes the entire Filament sidebar (only one menu should use this).'))
                    ->visible(fn (Get $get): bool => ($get('context') ?? MenuContext::Frontend->value) === MenuContext::Admin->value
                        || $get('context') === MenuContext::Admin),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('context')
                    ->badge(),
                TextColumn::make('location'),
                IconColumn::make('is_active')
                    ->boolean(),
                IconColumn::make('replaces_panel_navigation')
                    ->label(__('Replaces sidebar'))
                    ->boolean(),
                TextColumn::make('all_items_count')
                    ->counts('allItems')
                    ->label(__('Items')),
            ])
            ->filters([
                ResourceTableFilters::menuContext(),
                ResourceTableFilters::boolean('is_active', __('Active')),
                ResourceTableFilters::boolean('replaces_panel_navigation', __('Replaces sidebar')),
            ])
            ->recordActions([
                Action::make('build')
                    ->label(__('Open builder'))
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->url(fn (CmsMenu $record): string => MenuBuilder::getUrl([
                        'context' => $record->context->value,
                        'menuId' => $record->id,
                    ])),
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
            'index' => ManageCmsMenus::route('/'),
        ];
    }
}
