<?php

namespace App\Filament\Resources\SitePlugins;

use App\Filament\Resources\SitePlugins\Pages\ManageSitePlugins;
use App\Filament\Tables\Filters\ResourceTableFilters;
use App\Models\SitePlugin;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SitePluginResource extends Resource
{
    protected static ?string $model = SitePlugin::class;

    protected static string|\UnitEnum|null $navigationGroup = 'site';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Plugins';

    protected static ?string $modelLabel = 'plugin';

    protected static ?string $pluralModelLabel = 'plugins';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $globalSearchSort = 84;

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Toggle::make('is_enabled')
                    ->label(__('Enabled')),
                RichEditor::make('description')
                    ->columnSpanFull(),
                Textarea::make('config')
                    ->columnSpanFull()
                    ->rows(6)
                    ->helperText(__('JSON object, optional'))
                    ->formatStateUsing(function ($state): string {
                        if ($state === null || $state === [] || $state === '') {
                            return '';
                        }
                        if (is_array($state)) {
                            return json_encode($state, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES) ?: '';
                        }

                        return (string) $state;
                    })
                    ->dehydrateStateUsing(function (?string $state): ?array {
                        if ($state === null || trim($state) === '') {
                            return null;
                        }

                        $decoded = json_decode($state, true);

                        return is_array($decoded) ? $decoded : null;
                    }),
                TextInput::make('version')
                    ->maxLength(50),
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
                IconColumn::make('is_enabled')
                    ->boolean(),
                TextColumn::make('version'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                ResourceTableFilters::boolean('is_enabled', __('Enabled')),
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
            'index' => ManageSitePlugins::route('/'),
        ];
    }
}
