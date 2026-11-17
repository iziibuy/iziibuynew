<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\Admin\DueBillOwnerInspector;
use App\Services\Admin\DueBillsQuery;
use App\Services\Admin\UnifiedDueLedger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
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

    #[Url(as: 'tab')]
    public string $activeTab = UnifiedDueLedger::SCOPE_DUE;

    /**
     * @var array{
     *     user_id: int,
     *     user: array{id: int, name: string, email: string}|null,
     *     billing_period: string,
     *     entities: list<array<string, mixed>>
     * }|null
     */
    public ?array $ownerInspection = null;

    public function getTitle(): string|Htmlable
    {
        return __('Due bills');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Shops, plugins, and enterprise subscriptions for the selected billing month.');
    }

    public function updatedActiveTab(): void
    {
        if (! in_array($this->activeTab, [UnifiedDueLedger::SCOPE_DUE, UnifiedDueLedger::SCOPE_UNAVAILABLE], true)) {
            $this->activeTab = UnifiedDueLedger::SCOPE_DUE;
        }

        $this->resetTable();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Html::make(function (): string {
                    if ($this->ownerInspection === null) {
                        return '';
                    }

                    return Blade::render(
                        '<div class="mb-6">@include(\'filament.pages.partials.due-bill-owner-inspection\', [\'inspection\' => $inspection])</div>',
                        ['inspection' => $this->ownerInspection],
                    );
                }),
                Html::make(fn (): string => Blade::render(
                    <<<'BLADE'
                    <div class="mb-4 flex flex-wrap gap-2">
                        <button
                            type="button"
                            wire:click="$set('activeTab', 'due')"
                            @class([
                                'fi-btn fi-size-sm',
                                'fi-btn-color-primary' => $activeTab === 'due',
                                'fi-btn-color-gray' => $activeTab !== 'due',
                            ])
                        >
                            {{ __('Due') }}
                            <span class="ms-1 rounded-full bg-white/20 px-1.5 text-xs">{{ $counts['due'] ?? 0 }}</span>
                        </button>
                        <button
                            type="button"
                            wire:click="$set('activeTab', 'unavailable')"
                            @class([
                                'fi-btn fi-size-sm',
                                'fi-btn-color-warning' => $activeTab === 'unavailable',
                                'fi-btn-color-gray' => $activeTab !== 'unavailable',
                            ])
                        >
                            {{ __('Unavailable') }}
                            <span class="ms-1 rounded-full bg-white/20 px-1.5 text-xs">{{ $counts['unavailable'] ?? 0 }}</span>
                        </button>
                    </div>
                    BLADE,
                    [
                        'activeTab' => $this->activeTab,
                        'counts' => app(DueBillsQuery::class)->counts($this->tableFilters),
                    ],
                )),
                EmbeddedTable::make(),
            ]);
    }

    public function table(Table $table): Table
    {
        $isUnavailable = $this->activeTab === UnifiedDueLedger::SCOPE_UNAVAILABLE;

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
                    ->sortable()
                    ->wrap()
                    ->limit(40),
                TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->sortable()
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
                TextColumn::make('unavailable_reason')
                    ->label(__('Why unavailable'))
                    ->wrap()
                    ->visible($isUnavailable)
                    ->placeholder('-')
                    ->toggleable(),
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
            ->headerActions([
                Action::make('inspectOwner')
                    ->label(__('Check owner'))
                    ->icon(Heroicon::OutlinedMagnifyingGlass)
                    ->color('gray')
                    ->modalHeading(__('Check owner billing status'))
                    ->modalDescription(__('Enter an owner user ID to see if their shop/plugin is on the due list, or why it will not be billed.'))
                    ->modalSubmitActionLabel(__('Check'))
                    ->schema([
                        TextInput::make('user_id')
                            ->label(__('Owner user ID'))
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->placeholder('40133'),
                    ])
                    ->action(function (array $data): void {
                        $billingPeriod = app(DueBillsQuery::class)->billingPeriodFromFilters($this->tableFilters);
                        $this->ownerInspection = app(DueBillOwnerInspector::class)
                            ->inspect((int) $data['user_id'], $billingPeriod);
                    }),
                Action::make('clearOwnerInspection')
                    ->label(__('Clear check'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('gray')
                    ->visible(fn (): bool => $this->ownerInspection !== null)
                    ->action(function (): void {
                        $this->ownerInspection = null;
                    }),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('Type'))
                    ->options(UnifiedDueLedger::typeOptions()),
                Filter::make('name')
                    ->label(__('Name'))
                    ->form([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->placeholder(__('Filter by owner name')),
                    ])
                    ->indicateUsing(function (array $state): array {
                        if (filled($state['name'] ?? null)) {
                            return [__('Name').': '.$state['name']];
                        }

                        return [];
                    }),
                Filter::make('email')
                    ->label(__('Email'))
                    ->form([
                        TextInput::make('email')
                            ->label(__('Email'))
                            ->placeholder(__('Filter by email')),
                    ])
                    ->indicateUsing(function (array $state): array {
                        if (filled($state['email'] ?? null)) {
                            return [__('Email').': '.$state['email']];
                        }

                        return [];
                    }),
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
            ->recordActions([
                Action::make('breakdown')
                    ->label(__('Breakdown'))
                    ->tooltip(__('Owed amount breakdown'))
                    ->icon(Heroicon::OutlinedCalculator)
                    ->iconButton()
                    ->color('gray')
                    ->modalHeading(fn (array $record): string => __('Due bill breakdown').' — '.($record['company'] ?? ''))
                    ->modalWidth(Width::Large)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close'))
                    ->modalContent(fn (array $record): View => view('filament.pages.partials.due-bill-breakdown', [
                        'breakdown' => $record['breakdown'] ?? [
                            'summary' => '',
                            'period' => '',
                            'lines' => [],
                            'total' => 0,
                            'currency' => 'NOK',
                        ],
                    ])),
            ])
            ->emptyStateHeading($isUnavailable ? __('No unavailable accounts') : __('No due bills'))
            ->emptyStateDescription($isUnavailable
                ? __('Nothing is excluded from billing for this month with the current filters.')
                : __('Nothing matches the due criteria for this billing month.'))
            ->emptyStateIcon($isUnavailable ? Heroicon::OutlinedNoSymbol : Heroicon::OutlinedBanknotes);
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
        return app(DueBillsQuery::class)->rows(
            filters: $filters,
            sortColumn: $sortColumn,
            sortDirection: $sortDirection,
            search: $search,
            scope: $this->activeTab === UnifiedDueLedger::SCOPE_UNAVAILABLE
                ? UnifiedDueLedger::SCOPE_UNAVAILABLE
                : UnifiedDueLedger::SCOPE_DUE,
        );
    }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->modelLabel(__('Due bill'))
            ->pluralModelLabel(__('Due bills'));
    }
}
