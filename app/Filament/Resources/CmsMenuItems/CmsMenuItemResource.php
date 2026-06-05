<?php

declare(strict_types=1);

namespace App\Filament\Resources\CmsMenuItems;

use App\Enums\MenuContext;
use App\Enums\MenuLinkType;
use App\Filament\Resources\CmsMenuItems\Pages\ManageCmsMenuItems;
use App\Filament\Resources\CmsMenuItems\Pages\MenuBuilder;
use App\Filament\Tables\Filters\ResourceTableFilters;
use App\Models\CmsMenu;
use App\Models\CmsMenuItem;
use App\Services\Cms\FilamentResourceRegistry;
use BackedEnum;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Route;

class CmsMenuItemResource extends Resource
{
    protected static ?string $model = CmsMenuItem::class;

    protected static string|\UnitEnum|null $navigationGroup = 'site';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'menu item';

    protected static ?string $pluralModelLabel = 'menu items';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $globalSearchSort = 71;

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'url', 'menu.name'];
    }

    /**
     * @return Builder<CmsMenuItem>
     */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('menu');
    }

    public static function form(Schema $schema): Schema
    {
        $registry = app(FilamentResourceRegistry::class);

        return $schema
            ->components([
                Select::make('cms_menu_id')
                    ->relationship('menu', 'name')
                    ->required()
                    ->preload()
                    ->searchable()
                    ->label(__('Menu')),
                Select::make('parent_id')
                    ->relationship('parent', 'title', modifyQueryUsing: fn ($query) => $query->orderBy('sort_order'))
                    ->searchable()
                    ->preload()
                    ->label(__('Parent item')),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Select::make('link_type')
                    ->options(MenuLinkType::class)
                    ->default(MenuLinkType::Url)
                    ->required()
                    ->live(),
                TextInput::make('url')
                    ->maxLength(2048)
                    ->label(__('URL or path'))
                    ->visible(fn (Get $get): bool => $get('link_type') === MenuLinkType::Url->value || $get('link_type') === MenuLinkType::Url),
                Select::make('route_name')
                    ->label(__('Named route'))
                    ->searchable()
                    ->options(fn (): array => collect(Route::getRoutes()->getRoutesByName())
                        ->keys()
                        ->sort()
                        ->mapWithKeys(fn (string $name): array => [$name => $name])
                        ->all())
                    ->visible(fn (Get $get): bool => $get('link_type') === MenuLinkType::Route->value || $get('link_type') === MenuLinkType::Route),
                Select::make('resource_class')
                    ->label(__('Filament resource'))
                    ->searchable()
                    ->options($registry->resourceOptions())
                    ->visible(fn (Get $get): bool => $get('link_type') === MenuLinkType::Resource->value || $get('link_type') === MenuLinkType::Resource),
                Select::make('navigation_group')
                    ->label(__('Admin navigation group'))
                    ->options($registry->navigationGroupOptions())
                    ->searchable()
                    ->visible(fn (Get $get, $record): bool => static::menuIsAdmin($get, $record)),
                TextInput::make('icon')
                    ->maxLength(255)
                    ->placeholder('heroicon-o-home')
                    ->helperText(__('Heroicon name for admin panel items.')),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label(__('Active'))
                    ->default(true),
                Toggle::make('open_new_tab')
                    ->label(__('Open in new tab')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('menu.name')
                    ->label(__('Menu'))
                    ->sortable(),
                TextColumn::make('parent.title')
                    ->label(__('Parent')),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('link_type')
                    ->badge(),
                TextColumn::make('url')
                    ->limit(40),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
                IconColumn::make('open_new_tab')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('cms_menu_id')
                    ->label(__('Menu'))
                    ->relationship('menu', 'name')
                    ->searchable()
                    ->preload(),
                ResourceTableFilters::menuLinkType(),
                ResourceTableFilters::boolean('is_active', __('Active')),
                ResourceTableFilters::boolean('open_new_tab', __('Open in new tab')),
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
            'index' => ManageCmsMenuItems::route('/'),
            'menu-builder' => MenuBuilder::route('/menu-builder'),
        ];
    }

    protected static function menuIsAdmin(Get $get, mixed $record): bool
    {
        $menuId = $get('cms_menu_id') ?? $record?->cms_menu_id;

        if (! $menuId) {
            return false;
        }

        return CmsMenu::query()
            ->whereKey($menuId)
            ->where('context', MenuContext::Admin)
            ->exists();
    }
}
