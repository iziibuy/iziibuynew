<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\Admin\DueBillCharger;
use App\Services\Admin\DueBillMakeAvailable;
use App\Services\Admin\DueBillOwnerInspector;
use App\Services\Admin\DueBillsQuery;
use App\Services\Admin\UnifiedDueLedger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
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
        $period = app(DueBillsQuery::class)->billingPeriodFromFilters($this->tableFilters);
        $nextMonth = $period->start->copy()->addMonth();

        return __('Due for :month. A successful charge leaves this list and shows again for :next after :next ends. Failed charges stay here.', [
            'month' => $period->start->format('F Y'),
            'next' => $nextMonth->format('F Y'),
        ]);
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
                    <div class="mb-4 flex flex-wrap gap-2 border-b border-gray-200 pb-3 dark:border-white/10">
                        <button
                            type="button"
                            wire:click="$set('activeTab', 'due')"
                            @class([
                                'inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium transition',
                                'bg-primary-600 text-white' => $activeTab === 'due',
                                'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10' => $activeTab !== 'due',
                            ])
                        >
                            {{ __('Due') }}
                            <span @class([
                                'rounded-md px-1.5 py-0.5 text-xs tabular-nums',
                                'bg-white/20' => $activeTab === 'due',
                                'bg-white dark:bg-white/10' => $activeTab !== 'due',
                            ])>{{ $counts['due'] ?? 0 }}</span>
                        </button>
                        <button
                            type="button"
                            wire:click="$set('activeTab', 'unavailable')"
                            @class([
                                'inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium transition',
                                'bg-amber-600 text-white' => $activeTab === 'unavailable',
                                'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10' => $activeTab !== 'unavailable',
                            ])
                        >
                            {{ __('Unavailable') }}
                            <span @class([
                                'rounded-md px-1.5 py-0.5 text-xs tabular-nums',
                                'bg-white/20' => $activeTab === 'unavailable',
                                'bg-white dark:bg-white/10' => $activeTab !== 'unavailable',
                            ])>{{ $counts['unavailable'] ?? 0 }}</span>
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
                Action::make('chargeDueAccounts')
                    ->label(__('Charge due accounts'))
                    ->icon(Heroicon::OutlinedCreditCard)
                    ->color('danger')
                    ->visible(fn (): bool => $this->activeTab === UnifiedDueLedger::SCOPE_DUE)
                    ->requiresConfirmation()
                    ->modalHeading(__('Charge due accounts'))
                    ->modalDescription(function (): string {
                        $period = app(DueBillsQuery::class)->billingPeriodFromFilters($this->tableFilters);
                        $count = app(DueBillsQuery::class)->counts(billingPeriod: $period)[UnifiedDueLedger::SCOPE_DUE];
                        $nextMonth = $period->start->copy()->addMonth()->format('F Y');

                        return __('This charges :count due account(s) for :month. Accounts already paid for :month are skipped. Successful charges leave this list and show again for :next after :next ends. Failed charges stay on this list.', [
                            'count' => $count,
                            'month' => $period->start->format('F Y'),
                            'next' => $nextMonth,
                        ]);
                    })
                    ->modalSubmitActionLabel(__('Charge now'))
                    ->action(function (): void {
                        $this->chargeBillingPeriod();
                    }),
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
                Filter::make('company')
                    ->label(__('Company'))
                    ->form([
                        TextInput::make('company')
                            ->label(__('Company'))
                            ->placeholder(__('Filter by company')),
                    ])
                    ->indicateUsing(function (array $state): array {
                        if (filled($state['company'] ?? null)) {
                            return [__('Company').': '.$state['company']];
                        }

                        return [];
                    }),
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
                            ->placeholder(__('Last month'))
                            ->helperText(__('Leave empty to use last month. Accounts already paid for that month stay off this list until the next month ends.'))
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
                Action::make('chargeAccount')
                    ->label(__('Charge'))
                    ->tooltip(__('Charge this account for the selected month'))
                    ->icon(Heroicon::OutlinedCreditCard)
                    ->color('danger')
                    ->visible(fn (): bool => $this->activeTab === UnifiedDueLedger::SCOPE_DUE)
                    ->requiresConfirmation()
                    ->modalHeading(fn (array $record): string => __('Charge').' — '.($record['company'] ?? ''))
                    ->modalDescription(function (array $record): string {
                        $period = app(DueBillsQuery::class)->billingPeriodFromFilters($this->tableFilters);

                        return __('Charge :company for :month. If this month is already paid, it is skipped. A failed charge stays on the due list.', [
                            'company' => (string) ($record['company'] ?? ''),
                            'month' => $period->start->format('F Y'),
                        ]);
                    })
                    ->modalSubmitActionLabel(__('Charge now'))
                    ->action(function (array $record): void {
                        $type = (string) ($record['type'] ?? '');
                        $entityId = (int) ($record['entity_id'] ?? 0);

                        $this->chargeBillingPeriod(
                            shopId: $type === UnifiedDueLedger::TYPE_SHOP ? $entityId : null,
                            pluginId: $type === UnifiedDueLedger::TYPE_PLUGIN ? $entityId : null,
                            enterpriseId: $type === UnifiedDueLedger::TYPE_ENTERPRISE ? $entityId : null,
                        );
                    }),
                Action::make('makeAvailable')
                    ->label(__('Make available'))
                    ->tooltip(__('Fix blockers so this account appears on the due list'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (): bool => $this->activeTab === UnifiedDueLedger::SCOPE_UNAVAILABLE)
                    ->requiresConfirmation()
                    ->modalHeading(fn (array $record): string => __('Make available').' — '.($record['company'] ?? ''))
                    ->modalDescription(fn (array $record): string => __('This will activate the account when inactive, clear this month’s paid marker, settle an open free trial, and reactivate a subscription when possible.').' '
                        .(filled($record['unavailable_reason'] ?? null)
                            ? __('Current reason: :reason', ['reason' => $record['unavailable_reason']])
                            : ''))
                    ->modalSubmitActionLabel(__('Make available'))
                    ->action(function (array $record): void {
                        $this->notifyAvailabilityResult(
                            app(DueBillMakeAvailable::class)->makeAvailable(
                                type: (string) ($record['type'] ?? ''),
                                entityId: (int) ($record['entity_id'] ?? 0),
                                billingPeriod: app(DueBillsQuery::class)->billingPeriodFromFilters($this->tableFilters),
                            ),
                            expectingDue: true,
                        );
                    }),
                Action::make('makeUnavailable')
                    ->label(__('Make unavailable'))
                    ->tooltip(__('Exclude from this month’s due list'))
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('warning')
                    ->visible(fn (): bool => $this->activeTab === UnifiedDueLedger::SCOPE_DUE)
                    ->requiresConfirmation()
                    ->modalHeading(fn (array $record): string => __('Make unavailable').' — '.($record['company'] ?? ''))
                    ->modalDescription(__('This marks the account as already paid for the selected billing month so it leaves the due list. The account stays active.'))
                    ->modalSubmitActionLabel(__('Make unavailable'))
                    ->action(function (array $record): void {
                        $this->notifyAvailabilityResult(
                            app(DueBillMakeAvailable::class)->makeUnavailable(
                                type: (string) ($record['type'] ?? ''),
                                entityId: (int) ($record['entity_id'] ?? 0),
                                billingPeriod: app(DueBillsQuery::class)->billingPeriodFromFilters($this->tableFilters),
                            ),
                            expectingDue: false,
                        );
                    }),
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

    protected function chargeBillingPeriod(?int $shopId = null, ?int $pluginId = null, ?int $enterpriseId = null): void
    {
        $period = app(DueBillsQuery::class)->billingPeriodFromFilters($this->tableFilters);
        $result = app(DueBillCharger::class)->charge(
            billingPeriod: $period,
            shopId: $shopId,
            pluginId: $pluginId,
            enterpriseId: $enterpriseId,
        );

        $details = collect(preg_split('/\R/', (string) $result['output']) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter(fn (string $line): bool => preg_match('/^(→|OK:|SKIP:|FAIL:|ERROR:|No .* matched|Payment method access #|Shop #|Enterprise)/', $line) === 1)
            ->take(30)
            ->implode("\n");

        $problems = preg_match('/(SKIP:|FAIL:|ERROR:|matched the due criteria)/', (string) $result['output']) === 1;
        $succeeded = $result['exit_code'] === 0 && ! $problems;

        $title = $succeeded
            ? __('Charge finished for :month', ['month' => $period->start->format('F Y')])
            : __('Charge did not complete for :month', ['month' => $period->start->format('F Y')]);

        $notification = Notification::make()
            ->title($title)
            ->body($details !== '' ? $details : __('The charge command returned no output.'))
            ->persistent();

        if ($succeeded) {
            $notification->success();
        } else {
            $notification->warning();
        }

        $notification->send();
        $this->resetTable();
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

    public function makeOwnerEntityAvailable(string $type, int $entityId): void
    {
        $this->toggleOwnerEntityAvailability($type, $entityId, makeAvailable: true);
    }

    public function makeOwnerEntityUnavailable(string $type, int $entityId): void
    {
        $this->toggleOwnerEntityAvailability($type, $entityId, makeAvailable: false);
    }

    protected function toggleOwnerEntityAvailability(string $type, int $entityId, bool $makeAvailable): void
    {
        if ($this->ownerInspection === null) {
            return;
        }

        $billingPeriod = app(DueBillsQuery::class)->billingPeriodFromFilters($this->tableFilters);
        $service = app(DueBillMakeAvailable::class);

        $result = $makeAvailable
            ? $service->makeAvailable($type, $entityId, $billingPeriod)
            : $service->makeUnavailable($type, $entityId, $billingPeriod);

        $this->notifyAvailabilityResult($result, expectingDue: $makeAvailable);

        $this->ownerInspection = app(DueBillOwnerInspector::class)
            ->inspect((int) $this->ownerInspection['user_id'], $billingPeriod);
    }

    /**
     * @param  array{success: bool, on_due_list: bool, actions: list<string>, remaining_reasons: list<string>, message: string}  $result
     */
    protected function notifyAvailabilityResult(array $result, bool $expectingDue): void
    {
        $body = collect($result['actions'])
            ->merge($result['remaining_reasons'])
            ->filter()
            ->implode(' ');

        $matchedExpectation = $expectingDue
            ? ($result['on_due_list'] === true)
            : ($result['on_due_list'] === false && $result['success'] === true);

        if ($matchedExpectation) {
            Notification::make()
                ->title($result['message'])
                ->body($body !== '' ? $body : null)
                ->success()
                ->send();
        } elseif ($result['actions'] === []) {
            Notification::make()
                ->title($result['message'])
                ->body($body !== '' ? $body : null)
                ->danger()
                ->send();
        } else {
            Notification::make()
                ->title($result['message'])
                ->body($body !== '' ? $body : null)
                ->warning()
                ->send();
        }

        $this->resetTable();
    }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->modelLabel(__('Due bill'))
            ->pluralModelLabel(__('Due bills'));
    }
}
