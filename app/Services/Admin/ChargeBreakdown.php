<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Charge;
use App\Models\Enterprise;
use App\Models\ExternalSubscriptionCharge;
use App\Models\PaymentMethodAccess;
use App\Models\SubscriptionCharge;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Explains how the amount of a unified charge ledger row was calculated.
 *
 * @phpstan-type BreakdownLine array{label: string, amount: float, note: ?string}
 * @phpstan-type Breakdown array{method: string, summary: string, period: ?string, lines: list<BreakdownLine>, total: float, charged: float, currency: string, notes: list<string>}
 */
class ChargeBreakdown
{
    public const METHOD_SNAPSHOT = 'snapshot';

    public const METHOD_RECONSTRUCTED = 'reconstructed';

    public const METHOD_AS_CHARGED = 'as_charged';

    public const STANDARD_VAT_RATE = 25.0;

    protected const PRELOAD_CHUNK_SIZE = 1000;

    /**
     * Charge models loaded in bulk, keyed by ledger row id.
     *
     * @var array<string, Model>
     */
    protected array $preloaded = [];

    /**
     * Load the charges behind many ledger rows in a few queries, e.g. before an export.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function preload(Collection $rows): static
    {
        $idsBySource = $rows
            ->map(fn (array $row): array => UnifiedChargeLedger::parseKey((string) ($row['id'] ?? '')))
            ->groupBy(0)
            ->map(fn (Collection $keys): array => $keys->pluck(1)->unique()->values()->all());

        $loaders = [
            UnifiedChargeLedger::SOURCE_SHOP => fn (array $ids): EloquentCollection => tap(
                Charge::query()->whereIn('id', $ids)->get(),
                fn (EloquentCollection $charges) => $charges
                    ->filter(fn (Charge $charge): bool => ! $this->hasFeeSnapshot($charge))
                    ->load('shop.users'),
            ),
            UnifiedChargeLedger::SOURCE_SUBSCRIPTION => fn (array $ids): EloquentCollection => SubscriptionCharge::query()
                ->with('subscription.subscribable')
                ->whereIn('id', $ids)
                ->get(),
            UnifiedChargeLedger::SOURCE_EXTERNAL_SUBSCRIPTION => fn (array $ids): EloquentCollection => ExternalSubscriptionCharge::query()
                ->with('subscription')
                ->whereIn('id', $ids)
                ->get(),
        ];

        foreach ($loaders as $source => $loader) {
            foreach (array_chunk($idsBySource->get($source, []), self::PRELOAD_CHUNK_SIZE) as $ids) {
                foreach ($loader($ids) as $model) {
                    $this->preloaded[$source.'-'.$model->getKey()] = $model;
                }
            }
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return Breakdown
     */
    public function forRecord(array $record): array
    {
        [$source, $id] = UnifiedChargeLedger::parseKey((string) ($record['id'] ?? ''));

        $charged = round((float) ($record['amount'] ?? 0), 2);
        $currency = (string) ($record['currency'] ?? 'NOK');

        return match ($source) {
            UnifiedChargeLedger::SOURCE_SHOP => $this->shopCharge($id, $charged, $currency),
            UnifiedChargeLedger::SOURCE_SUBSCRIPTION => $this->subscriptionCharge($id, $charged, $currency),
            UnifiedChargeLedger::SOURCE_EXTERNAL_SUBSCRIPTION => $this->externalSubscriptionCharge($id, $charged, $currency),
            default => $this->asCharged(
                $charged,
                $currency,
                __('Membership charge'),
                __('The amount recorded when the membership was paid.'),
            ),
        };
    }

    /**
     * @return Breakdown
     */
    protected function shopCharge(int $id, float $charged, string $currency): array
    {
        $charge = $this->preloaded[UnifiedChargeLedger::SOURCE_SHOP.'-'.$id] ?? Charge::query()->find($id);

        if ($charge === null) {
            return $this->asCharged($charged, $currency, __('Shop charge'), __('The charge record could not be found.'));
        }

        if ($this->hasFeeSnapshot($charge)) {
            return $this->shopChargeFromSnapshot($this->feeSnapshot($charge), $charged, $currency);
        }

        $charge->loadMissing('shop.users');

        return $this->shopChargeFromCurrentPricing($charge, $charged, $currency);
    }

    protected function hasFeeSnapshot(Charge $charge): bool
    {
        return is_array($this->feeSnapshot($charge)['need_to_pay'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    protected function feeSnapshot(Charge $charge): array
    {
        $snapshot = json_decode((string) ($charge->getAttributes()['details'] ?? ''), true);

        return is_array($snapshot) ? $snapshot : [];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return Breakdown
     */
    protected function shopChargeFromSnapshot(array $snapshot, float $charged, string $currency): array
    {
        $needToPay = $snapshot['need_to_pay'];
        $fees = is_array(data_get($snapshot, 'shop.fees')) ? data_get($snapshot, 'shop.fees') : [];
        $daysLeft = (float) ($snapshot['days_before_the_months_end'] ?? 0);
        $daysInMonth = $this->daysInMonthFromSnapshot($snapshot);
        $proration = $daysLeft > 0 && $daysInMonth > 0
            ? __(':days of :total days left in the month', [
                'days' => number_format($daysLeft, 2),
                'total' => $daysInMonth,
            ])
            : null;

        $lines = [];

        $establishmentCost = (float) ($needToPay['esatblisment_cost'] ?? 0);
        if ($establishmentCost > 0) {
            $lines[] = $this->line(__('Setup fee (one-time)'), $establishmentCost);
        }

        $monthlyPrice = (float) ($fees['monthly_cost'] ?? 0);
        $lines[] = $this->line(
            __('Monthly fee'),
            (float) ($needToPay['monthly_cost'] ?? 0),
            $monthlyPrice > 0 && $proration !== null
                ? __(':price × :proration', ['price' => $this->money($monthlyPrice), 'proration' => $proration])
                : null,
        );

        $managersCost = (float) ($needToPay['managers_cost'] ?? 0);
        if ($managersCost > 0) {
            $lines[] = $this->line(
                __('Extra manager fee'),
                $managersCost,
                __(':price per extra manager, pro-rated', ['price' => $this->money((float) ($fees['per_manager_cost'] ?? 0))]),
            );
        }

        $discount = (float) ($snapshot['discount'] ?? 0);
        if ($discount > 0) {
            $lines[] = $this->line(__('Discount'), -$discount);
        }

        $lines[] = $this->line(__('VAT'), (float) ($needToPay['tax'] ?? 0));

        $notes = [];
        $difference = round($charged - $this->sumLines($lines), 2);
        if (abs($difference) >= 0.01) {
            $lines[] = $this->line(__('Difference not explained by the saved calculation'), $difference);
            $notes[] = __('The amount actually charged differs from the calculation saved with this charge, for example because prices, managers, or proration changed at renewal.');
        }

        return $this->result(
            method: self::METHOD_SNAPSHOT,
            summary: __('Calculated from the fee snapshot saved when the shop was charged on :date.', [
                'date' => (string) ($snapshot['charged_at'] ?? '-'),
            ]),
            lines: $lines,
            charged: $charged,
            currency: $currency,
            notes: $notes,
            period: $this->period(
                $this->snapshotDate($snapshot, 'charged_at'),
                $this->snapshotDate($snapshot, 'last_day_of_month'),
            ),
        );
    }

    /**
     * @return Breakdown
     */
    protected function shopChargeFromCurrentPricing(Charge $charge, float $charged, string $currency): array
    {
        $shop = $charge->shop;

        if ($shop === null) {
            return $this->asCharged($charged, $currency, __('Shop charge'), __('The shop for this charge no longer exists.'));
        }

        $lines = [$this->line(__('Monthly fee'), (float) $shop->monthly_cost, __('Current shop price'))];

        if ($shop->users->count() > 1) {
            $lines[] = $this->line(__('Extra manager fee'), (float) $shop->per_user_fee, __('Current shop price'));
        }

        $net = $this->sumLines($lines);
        $remainder = round($charged - $net, 2);
        $impliedVatRate = $net > 0 ? round($remainder / $net * 100, 2) : null;
        $notes = [__('No calculation was saved with this charge, so the breakdown uses the shop\'s current prices.')];

        if ($impliedVatRate !== null && $impliedVatRate >= 0 && $impliedVatRate <= self::STANDARD_VAT_RATE + 0.01) {
            $lines[] = $this->line(__('VAT (:rate%)', ['rate' => rtrim(rtrim(number_format($impliedVatRate, 2), '0'), '.')]), $remainder);
        } else {
            $lines[] = $this->line(__('Difference from current shop pricing'), $remainder);
            $notes[] = __('The charged amount does not match the shop\'s current prices. The prices may have changed since this charge, or it included a setup fee or discount.');
        }

        return $this->result(
            method: self::METHOD_RECONSTRUCTED,
            summary: __('Monthly fee + extra manager fee + VAT.'),
            lines: $lines,
            charged: $charged,
            currency: $currency,
            notes: $notes,
            period: $this->calendarMonthPeriod($charge->created_at),
        );
    }

    /**
     * @return Breakdown
     */
    protected function subscriptionCharge(int $id, float $charged, string $currency): array
    {
        $charge = $this->preloaded[UnifiedChargeLedger::SOURCE_SUBSCRIPTION.'-'.$id]
            ?? SubscriptionCharge::query()->with('subscription.subscribable')->find($id);
        $subscribable = $charge?->subscription?->subscribable;

        if ($subscribable instanceof PaymentMethodAccess) {
            return $this->pluginCharge($subscribable, $charge, $charged, $currency);
        }

        if ($subscribable instanceof Enterprise) {
            $notes = [];
            $currentFee = (float) ($charge->subscription->getAttributes()['fee'] ?? 0);
            if ($currentFee > 0 && abs($currentFee - $charged) >= 0.01) {
                $notes[] = __('The enterprise\'s current subscription fee is :fee.', ['fee' => $this->money($currentFee)]);
            }

            return $this->result(
                method: self::METHOD_AS_CHARGED,
                summary: __('Enterprise subscription fee, charged as-is (no pro-rating and no VAT added).'),
                lines: [$this->line(__('Subscription fee'), $charged)],
                charged: $charged,
                currency: $currency,
                notes: $notes,
                period: $this->calendarMonthPeriod($charge->created_at),
            );
        }

        return $this->asCharged($charged, $currency, __('Subscription charge'), __('The amount recorded when the subscription was charged.'));
    }

    /**
     * @return Breakdown
     */
    protected function pluginCharge(PaymentMethodAccess $plugin, SubscriptionCharge $charge, float $charged, string $currency): array
    {
        try {
            $basePrice = $plugin->baseMonthlyFee();
        } catch (Throwable) {
            $basePrice = 0.0;
        }

        $vatMultiplier = 1 + self::STANDARD_VAT_RATE / 100;
        $excludingVat = round($charged / $vatMultiplier, 2);
        $vat = round($charged - $excludingVat, 2);
        $daysInMonth = $charge->created_at?->daysInMonth ?? 0;
        $daysCharged = $basePrice > 0 && $daysInMonth > 0 ? $excludingVat / $basePrice * $daysInMonth : 0.0;

        $notes = [];
        if ($daysCharged > $daysInMonth + 0.5) {
            $notes[] = __('The charge is higher than one full month at the current plugin price, so the price has likely changed since this charge.');
        }

        $paymentDetails = json_decode((string) ($charge->getAttributes()['payment_details'] ?? ''), true);
        $isMonthlyRenewal = data_get($paymentDetails, 'type') === 'monthly';

        if ($isMonthlyRenewal) {
            $fullMonth = round($basePrice * $vatMultiplier, 2);
            $isFullMonth = $basePrice > 0 && abs($charged - $fullMonth) < 0.01;

            if (! $isFullMonth && $basePrice > 0) {
                $notes[] = __('This monthly renewal was pro-rated instead of charging the full month. The full month is :full, so it was under-charged by :missing.', [
                    'full' => $this->money($fullMonth),
                    'missing' => $this->money($fullMonth - $charged),
                ]);
            }

            return $this->result(
                method: self::METHOD_RECONSTRUCTED,
                summary: __('Monthly renewal: full monthly plugin fee + 25% VAT.'),
                lines: [
                    $this->line(
                        $isFullMonth ? __('Plugin fee (full month)') : __('Plugin fee (pro-rated)'),
                        $excludingVat,
                        $isFullMonth || $basePrice <= 0
                            ? null
                            : __(':price × :days of :total days', [
                                'price' => $this->money($basePrice),
                                'days' => number_format($daysCharged, 2),
                                'total' => $daysInMonth,
                            ]),
                    ),
                    $this->line(__('VAT (:rate%)', ['rate' => (int) self::STANDARD_VAT_RATE]), $vat),
                ],
                charged: $charged,
                currency: $currency,
                notes: $notes,
                period: $this->calendarMonthPeriod($charge->created_at),
            );
        }

        return $this->result(
            method: self::METHOD_RECONSTRUCTED,
            summary: __('Signup: (monthly plugin fee × days left ÷ days in month) + 25% VAT.'),
            lines: [
                $this->line(
                    __('Plugin fee (pro-rated)'),
                    $excludingVat,
                    $basePrice > 0
                        ? __(':price × :days of :total days', [
                            'price' => $this->money($basePrice),
                            'days' => number_format($daysCharged, 2),
                            'total' => $daysInMonth,
                        ])
                        : null,
                ),
                $this->line(__('VAT (:rate%)', ['rate' => (int) self::STANDARD_VAT_RATE]), $vat),
            ],
            charged: $charged,
            currency: $currency,
            notes: $notes,
            period: $this->period($charge->created_at, $charge->created_at?->copy()->endOfMonth()),
        );
    }

    /**
     * @return Breakdown
     */
    protected function externalSubscriptionCharge(int $id, float $charged, string $currency): array
    {
        $charge = $this->preloaded[UnifiedChargeLedger::SOURCE_EXTERNAL_SUBSCRIPTION.'-'.$id]
            ?? ExternalSubscriptionCharge::query()->with('subscription')->find($id);
        $subscription = $charge?->subscription;
        $note = $subscription !== null
            ? __(':amount every :days days', [
                'amount' => $this->money((float) $subscription->amount),
                'days' => (int) $subscription->interval_days,
            ])
            : null;

        return $this->result(
            method: self::METHOD_AS_CHARGED,
            summary: __('The button subscription amount set by the merchant, charged as-is.'),
            lines: [$this->line(__('Button subscription amount'), $charged, $note)],
            charged: $charged,
            currency: $currency,
            notes: [],
            period: $charge?->created_at !== null && (int) $subscription?->interval_days > 0
                ? $this->period($charge->created_at, $charge->created_at->copy()->addDays((int) $subscription->interval_days - 1))
                : null,
        );
    }

    /**
     * @return Breakdown
     */
    protected function asCharged(float $charged, string $currency, string $label, string $summary): array
    {
        return $this->result(
            method: self::METHOD_AS_CHARGED,
            summary: $summary,
            lines: [$this->line($label, $charged)],
            charged: $charged,
            currency: $currency,
            notes: [],
        );
    }

    /**
     * @param  list<BreakdownLine>  $lines
     * @param  list<string>  $notes
     * @return Breakdown
     */
    protected function result(string $method, string $summary, array $lines, float $charged, string $currency, array $notes, ?string $period = null): array
    {
        return [
            'method' => $method,
            'summary' => $summary,
            'period' => $period,
            'lines' => $lines,
            'total' => $this->sumLines($lines),
            'charged' => $charged,
            'currency' => $currency,
            'notes' => $notes,
        ];
    }

    /**
     * @return BreakdownLine
     */
    protected function line(string $label, float $amount, ?string $note = null): array
    {
        return [
            'label' => $label,
            'amount' => round($amount, 2),
            'note' => $note,
        ];
    }

    /**
     * @param  list<BreakdownLine>  $lines
     */
    protected function sumLines(array $lines): float
    {
        return round(array_sum(array_column($lines, 'amount')), 2);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function daysInMonthFromSnapshot(array $snapshot): int
    {
        return $this->snapshotDate($snapshot, 'last_day_of_month')?->day ?? 0;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function snapshotDate(array $snapshot, string $key): ?Carbon
    {
        try {
            return Carbon::createFromFormat('d M,Y', (string) ($snapshot[$key] ?? ''))->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    protected function period(?Carbon $from, ?Carbon $until): ?string
    {
        if ($from === null || $until === null || $until->lessThan($from)) {
            return null;
        }

        $days = (int) $from->copy()->startOfDay()->diffInDays($until->copy()->startOfDay()) + 1;

        return trans_choice(':from – :until (:count day)|:from – :until (:count days)', $days, [
            'from' => $from->format('d M Y'),
            'until' => $until->format('d M Y'),
        ]);
    }

    protected function calendarMonthPeriod(?Carbon $chargedAt): ?string
    {
        return $chargedAt === null
            ? null
            : $this->period($chargedAt->copy()->startOfMonth(), $chargedAt->copy()->endOfMonth());
    }

    protected function money(float $amount): string
    {
        return number_format($amount, 2);
    }
}
