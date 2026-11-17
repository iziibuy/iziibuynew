<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\Admin\DueBillsQuery;
use App\Services\Admin\UnifiedDueLedger;
use BackedEnum;
use Filament\Forms\Components\Select;
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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

class DueBillsPage extends Page implements HasTable
{
    use InteractsWithTable {
        makeTable as makeBaseTable;
    }

    protected static ?string $slug = 'due-bills';

    protected static ?string $navigationLabel = 'Due bills';

    protected static ?int $navigationSort = 5;

    protected static string|\UnitEnum|null $navigationGroup = 'finance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $title = 'Due bills';

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
        return __('Due bills');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Shops, plugins, and enterprise subscriptions due for the selected billing month.');
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
                $rows = $this->dueRows($filters, $sortColumn, $sortDirection, $search);

                $perPage = $recordsPerPage === 'all'
                    ? max($rows->count(), 1)
                    : max((int) $recordsPerPage, 1);
                $currentPage = max((int) $page, 1);

                return new LengthAwarePaginator(
                    items: $rows->forPage($currentPage, $perPage)->keyBy('id')->all(),
                    total: $rows->count(),
                    perPage: $perPage,
                    currentPage: $currentPage,
                );
            })
            ->columns([
                TextColumn::make('company')
                    ->label(__('Company'))
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->url(fn (array $record): ?string => $record['entity_url'] ?? null)
                    ->color(fn (array $record): ?string => filled($record['entity_url'] ?? null) ? 'primary' : null),
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->wrap()
                    ->limit(40),
                TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->copyable()
                    ->limit(40),
                TextColumn::make('type_label')
                    ->label(__('Type'))
                    ->badge()
                    ->color(fn (string $state, array $record): string => match ($record['type'] ?? '') {
                        UnifiedDueLedger::TYPE_SHOP => 'primary',
                        UnifiedDueLedger::TYPE_PLUGIN => 'warning',
                        UnifiedDueLedger::TYPE_ENTERPRISE => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('monthly_price')
                    ->label(__('Price / month'))
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2).' NOK'),
                TextColumn::make('due_amount')
                    ->label(__('Due bill'))
                    ->alignEnd()
                    ->sortable()
                    ->weight('bold')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2).' NOK'),
                TextColumn::make('last_paid_at')
                    ->label(__('Last paid'))
                    ->placeholder('-')
                    ->formatStateUsing(fn (?string $state): string => filled($state)
                        ? Carbon::parse($state)->format('Y-m-d')
                        : '-')
                    ->toggleable(),
                TextColumn::make('owner_url')
                    ->label(__('Owner'))
                    ->state(fn (array $record): string => filled($record['owner_url'] ?? null) ? __('Open') : '-')
                    ->url(fn (array $record): ?string => $record['owner_url'] ?? null)
                    ->color(fn (array $record): ?string => filled($record['owner_url'] ?? null) ? 'primary' : null)
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('company', 'asc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->striped()
            ->filters([
                SelectFilter::make('type')
                    ->label(__('Type'))
                    ->options(UnifiedDueLedger::typeOptions()),
                Filter::make('month')
                    ->label(__('Billing month'))
                    ->form([
                        Select::make('month')
                            ->label(__('Month'))
                            ->options($this->monthOptions())
                            ->placeholder(__('Current month'))
                            ->native(false),
                    ])
                    ->indicateUsing(function (array $state): array {
                        if (filled($state['month'] ?? null)) {
                            return [__('Month').': '.$state['month']];
                        }

                        return [];
                    }),
            ])
            ->emptyStateHeading(__('No due bills'))
            ->emptyStateDescription(__('Nothing matches the due criteria for this billing month.'))
            ->emptyStateIcon(Heroicon::OutlinedBanknotes);
    }

    /**
     * @return array<string, string>
     */
    protected function monthOptions(): array
    {
        $options = [];

        for ($i = 0; $i < 12; $i++) {
            $month = now()->startOfMonth()->subMonths($i);
            $options[$month->format('Y-m')] = $month->format('F Y');
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>|null  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected function dueRows(?array $filters, ?string $sortColumn, ?string $sortDirection, ?string $search): Collection
    {
        return app(DueBillsQuery::class)->rows($filters, $sortColumn, $sortDirection, $search);
    }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->modelLabel(__('Due bill'))
            ->pluralModelLabel(__('Due bills'));
    }
}
