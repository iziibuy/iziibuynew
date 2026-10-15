<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Exceptions\ChargeRefundException;
use App\Models\ChargeRefund;
use App\Services\Admin\AllChargesQuery;
use App\Services\Admin\ChargeBreakdown;
use App\Services\Admin\ChargeCardLookup;
use App\Services\Admin\ChargeRefundService;
use App\Services\Admin\UnifiedChargeLedger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
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
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Throwable;

class AllChargesPage extends Page implements HasTable
{
    use InteractsWithTable {
        makeTable as makeBaseTable;
    }

    protected const CARD_LOOKUPS_PER_RENDER = 25;

    protected static ?string $slug = 'all-charges';

    protected static ?string $navigationLabel = 'All charges';

    protected static ?int $navigationSort = 10;

    protected static string|\UnitEnum|null $navigationGroup = 'finance';

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
        $cardLookup = app(ChargeCardLookup::class)->limitRemoteLookups(self::CARD_LOOKUPS_PER_RENDER);

        return $table
            ->deferLoading()
            ->records(function (
                ?array $filters,
                int|string $page,
                int|string $recordsPerPage,
                ?string $sortColumn,
                ?string $sortDirection,
                ?string $search,
            ): LengthAwarePaginator {
                $rows = $this->chargeRows($filters, $sortColumn, $sortDirection, $search);

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
                TextColumn::make('card_last_four')
                    ->label(__('Card'))
                    ->state(fn (array $record): ?string => $cardLookup->lastFour($record))
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? '•••• '.$state : '-')
                    ->fontFamily(FontFamily::Mono)
                    ->toggleable(),
                TextColumn::make('status_label')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (string $state, array $record): string => match (true) {
                        (bool) ($record['refunded'] ?? false) => 'warning',
                        (bool) ($record['status'] ?? false) => 'success',
                        default => 'danger',
                    }),
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
            ->headerActions([
                Action::make('exportCsv')
                    ->label(__('Export CSV'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->url(fn (): string => route('admin.all_charges.export', [
                        'filters' => $this->tableFilters,
                        'sortColumn' => $this->getTableSortColumn(),
                        'sortDirection' => $this->getTableSortDirection(),
                        'search' => $this->getTableSearch(),
                    ]))
                    ->openUrlInNewTab(),
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
                        'refunded' => __('Refunded'),
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
                Action::make('breakdown')
                    ->label(__('Calculation breakdown'))
                    ->tooltip(__('Calculation breakdown'))
                    ->icon(Heroicon::OutlinedCalculator)
                    ->iconButton()
                    ->color('gray')
                    ->modalHeading(fn (array $record): string => __('Calculation breakdown').' — '.($record['reference'] ?? ''))
                    ->modalWidth(Width::Large)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close'))
                    ->modalContent(fn (array $record): View => view('filament.pages.partials.all-charge-breakdown', [
                        'breakdown' => app(ChargeBreakdown::class)->forRecord($record),
                        'cardLastFour' => app(ChargeCardLookup::class)->lastFour($record),
                    ])),
                Action::make('refund')
                    ->label(__('Refund'))
                    ->tooltip(__('Refund charge'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->iconButton()
                    ->color('danger')
                    ->visible(fn (array $record): bool => app(ChargeRefundService::class)->blockedReason($record) === null)
                    ->authorize(fn (): bool => (bool) auth()->user()?->hasRole('admin'))
                    ->requiresConfirmation()
                    ->modalIcon(Heroicon::OutlinedArrowUturnLeft)
                    ->modalHeading(fn (array $record): string => __('Refund :amount :currency?', [
                        'amount' => number_format((float) $record['amount'], 2),
                        'currency' => $record['currency'] ?? 'NOK',
                    ]))
                    ->modalDescription(fn (array $record): string => $this->refundDescription($record))
                    ->schema([
                        Textarea::make('reason')
                            ->label(__('Reason'))
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->modalSubmitActionLabel(__('Refund to card'))
                    ->action(function (array $record, array $data, Action $action): void {
                        try {
                            $refund = app(ChargeRefundService::class)->refund($record, (string) $data['reason'], auth()->user());
                        } catch (Throwable $exception) {
                            if (! $exception instanceof ChargeRefundException) {
                                report($exception);
                            }

                            Notification::make()
                                ->danger()
                                ->title(__('Refund failed'))
                                ->body($exception instanceof ChargeRefundException
                                    ? $exception->getMessage()
                                    : __('Unexpected error while contacting Elavon. Nothing was changed locally; check Elavon before retrying.'))
                                ->persistent()
                                ->send();

                            $action->halt();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title(__('Charge refunded'))
                            ->body(__(':amount NOK :operation at Elavon (transaction :id).', [
                                'amount' => number_format((float) $refund->amount, 2),
                                'operation' => $refund->provider_operation === ChargeRefund::OPERATION_VOID ? __('voided') : __('refunded'),
                                'id' => $refund->provider_transaction_id,
                            ]))
                            ->send();
                    }),
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

    /**
     * @param  array<string, mixed>  $record
     */
    protected function refundDescription(array $record): string
    {
        $cardLastFour = app(ChargeCardLookup::class)->lastFour($record);

        $lines = [
            $cardLastFour !== null
                ? __('The full amount goes back to card •••• :card through Elavon. This cannot be undone.', ['card' => $cardLastFour])
                : __('The full amount goes back to the card through Elavon. This cannot be undone.'),
            __('The account\'s paid date returns to its previous charge, so the next monthly run charges it again. The account stays active.'),
        ];

        if (($record['source'] ?? null) === UnifiedChargeLedger::SOURCE_SHOP) {
            $lines[] = __('Retailer commission paid out for this charge is reversed.');
        }

        return implode(' ', $lines);
    }

    /**
     * Filtered, searched, and sorted ledger rows shared by the table and the CSV export.
     *
     * @param  array<string, mixed>|null  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected function chargeRows(?array $filters, ?string $sortColumn, ?string $sortDirection, ?string $search): Collection
    {
        return app(AllChargesQuery::class)->rows($filters, $sortColumn, $sortDirection, $search);
    }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->modelLabel(__('Charge'))
            ->pluralModelLabel(__('Charges'));
    }
}
