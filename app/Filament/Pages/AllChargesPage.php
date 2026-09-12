<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\Admin\UnifiedChargeLedger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

class AllChargesPage extends Page implements HasTable
{
    use InteractsWithTable {
        makeTable as makeBaseTable;
    }

    protected static ?string $slug = 'all-charges';

    protected static ?string $navigationLabel = 'All charges';

    protected static ?int $navigationSort = 35;

    protected static string|\UnitEnum|null $navigationGroup = 'commerce';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $title = 'All charges';

    /**
     * @var array<string, mixed> | null
     */
    #[Url(as: 'filters')]
    public ?array $tableFilters = null;

    #[Url(as: 'sort')]
    public ?string $tableSort = null;

    /**
     * @var ?string
     */
    #[Url(as: 'search')]
    public $tableSearch = '';

    public function getTitle(): string|Htmlable
    {
        return __('All charges');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Shop, subscription, plugin, and button subscription charges in one place.');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (
                ?array $filters,
                int|string $page,
                int|string $recordsPerPage,
                ?string $sortColumn,
                ?string $sortDirection,
                ?string $search,
            ): LengthAwarePaginator {
                $rows = app(UnifiedChargeLedger::class)->collect($filters, $sortColumn, $sortDirection);

                if (filled($search)) {
                    $needle = Str::lower((string) $search);
                    $rows = $rows->filter(function (array $row) use ($needle): bool {
                        $haystack = Str::lower(implode(' ', [
                            $row['reference'] ?? '',
                            $row['entity_name'] ?? '',
                            $row['email'] ?? '',
                            $row['model_label'] ?? '',
                            $row['description'] ?? '',
                            $row['source_label'] ?? '',
                        ]));

                        return str_contains($haystack, $needle);
                    })->values();
                }

                $perPage = $recordsPerPage === 'all'
                    ? max($rows->count(), 1)
                    : max((int) $recordsPerPage, 1);
                $currentPage = max((int) $page, 1);

                return new LengthAwarePaginator(
                    items: $rows->forPage($currentPage, $perPage)->values()->all(),
                    total: $rows->count(),
                    perPage: $perPage,
                    currentPage: $currentPage,
                );
            })
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('source_label')
                    ->label(__('Source'))
                    ->badge()
                    ->color(fn (string $state, array $record): string => match ($record['source'] ?? '') {
                        UnifiedChargeLedger::SOURCE_SHOP => 'primary',
                        UnifiedChargeLedger::SOURCE_SUBSCRIPTION => 'info',
                        UnifiedChargeLedger::SOURCE_EXTERNAL_SUBSCRIPTION => 'warning',
                        UnifiedChargeLedger::SOURCE_MEMBERSHIP => 'gray',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('model_type')
                    ->label(__('Model'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => UnifiedChargeLedger::modelOptions()[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('model_label')
                    ->label(__('Account / entity'))
                    ->wrap()
                    ->searchable()
                    ->limit(40),
                TextColumn::make('reference')
                    ->label(__('Reference'))
                    ->searchable()
                    ->copyable()
                    ->limit(28),
                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn ($state, array $record): string => number_format((float) $state, 2).' '.($record['currency'] ?? 'NOK')),
                TextColumn::make('status_label')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (string $state, array $record): string => ($record['status'] ?? false) ? 'success' : 'danger'),
                TextColumn::make('description')
                    ->label(__('Description'))
                    ->toggleable()
                    ->limit(40)
                    ->placeholder('-'),
                TextColumn::make('email')
                    ->label(__('Email'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->striped()
            ->filters([
                SelectFilter::make('model')
                    ->label(__('Model'))
                    ->options(UnifiedChargeLedger::modelOptions()),
                SelectFilter::make('source')
                    ->label(__('Source'))
                    ->options(UnifiedChargeLedger::sourceOptions()),
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        'paid' => __('Paid'),
                        'unpaid' => __('Unpaid'),
                    ]),
                Filter::make('date')
                    ->label(__('Date'))
                    ->form([
                        DatePicker::make('from')
                            ->label(__('From')),
                        DatePicker::make('until')
                            ->label(__('Until')),
                    ])
                    ->indicateUsing(function (array $state): array {
                        $indicators = [];

                        if (filled($state['from'] ?? null)) {
                            $indicators[] = __('From').': '.$state['from'];
                        }

                        if (filled($state['until'] ?? null)) {
                            $indicators[] = __('Until').': '.$state['until'];
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                Action::make('view')
                    ->label(__('View'))
                    ->icon(Heroicon::OutlinedEye)
                    ->color('primary')
                    ->modalHeading(fn (array $record): string => __('Charge details').' — '.($record['reference'] ?? ''))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close'))
                    ->modalContent(fn (array $record): View => view('filament.pages.partials.all-charge-details', [
                        'record' => $record,
                    ]))
                    ->extraModalFooterActions(fn (array $record): array => filled(data_get($record, 'extras.view_url'))
                        ? [
                            Action::make('openResource')
                                ->label(__('Open shop charge'))
                                ->url((string) data_get($record, 'extras.view_url'))
                                ->openUrlInNewTab(),
                        ]
                        : []),
            ])
            ->emptyStateHeading(__('No charges found'))
            ->emptyStateDescription(__('Try adjusting the model or date filters.'))
            ->emptyStateIcon(Heroicon::OutlinedQueueList);
    }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->modelLabel(__('Charge'))
            ->pluralModelLabel(__('Charges'));
    }
}
