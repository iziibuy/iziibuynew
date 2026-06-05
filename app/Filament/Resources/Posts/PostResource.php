<?php

namespace App\Filament\Resources\Posts;

use App\Filament\Resources\Posts\Pages\ManagePosts;
use App\Filament\Tables\Filters\ResourceTableFilters;
use App\Models\Post;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static string|\UnitEnum|null $navigationGroup = 'site';

    protected static ?int $navigationSort = 15;

    protected static ?string $navigationLabel = 'Posts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $globalSearchSort = 52;

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'slug', 'seo_title'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('author_id')
                    ->relationship('author', 'name')
                    ->default(fn () => auth()->id())
                    ->required(),
                Select::make('category_id')
                    ->relationship('postCategory', 'name')
                    ->searchable()
                    ->preload()
                    ->label(__('Post category')),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                TextInput::make('seo_title')
                    ->maxLength(255),
                RichEditor::make('excerpt')
                    ->columnSpanFull(),
                RichEditor::make('body')
                    ->columnSpanFull(),
                FileUpload::make('image')
                    ->image()
                    ->directory('posts'),
                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Textarea::make('meta_description')
                    ->columnSpanFull(),
                Textarea::make('meta_keywords')
                    ->columnSpanFull(),
                Select::make('status')
                    ->options([
                        'DRAFT' => __('Draft'),
                        'PUBLISHED' => __('Published'),
                        'PENDING' => __('Pending'),
                    ])
                    ->required()
                    ->default('DRAFT'),
                Toggle::make('featured')
                    ->label(__('Featured')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('author.name')
                    ->label(__('Author'))
                    ->searchable(),
                TextColumn::make('postCategory.name')
                    ->label(__('Category'))
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable(),
                ImageColumn::make('image'),
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge(),
                IconColumn::make('featured')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                ResourceTableFilters::select('status', [
                    'DRAFT' => __('Draft'),
                    'PUBLISHED' => __('Published'),
                    'PENDING' => __('Pending'),
                    'ARCHIVED' => __('Archived'),
                ], __('Status')),
                SelectFilter::make('category_id')
                    ->label(__('Category'))
                    ->relationship('postCategory', 'name')
                    ->searchable()
                    ->preload(),
                ResourceTableFilters::boolean('featured', __('Featured')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('New post')),
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
            'index' => ManagePosts::route('/'),
        ];
    }
}
