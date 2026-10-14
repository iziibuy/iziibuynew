<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class MonthlyBillingPeriod
{
    public function __construct(
        public readonly Carbon $start,
        public readonly Carbon $end,
    ) {}

    /**
     * Timestamp stored on paid_at / last_paid_at: end of the billed period (inclusive).
     */
    public function paidThrough(): Carbon
    {
        return $this->end->copy()->endOfDay();
    }

    /**
     * Whether last_paid_at / paid_at already reflects payment through this billing month.
     */
    public function isPaidThrough(?Carbon $lastPaidAt): bool
    {
        if ($lastPaidAt === null) {
            return false;
        }

        return $lastPaidAt->gte($this->paidThrough());
    }

    public function inclusiveDays(): float
    {
        return (float) ($this->start->copy()->startOfDay()->diffInDays($this->end->copy()->startOfDay()) + 1);
    }

    public function daysInMonth(): int
    {
        return $this->start->daysInMonth;
    }

    public function label(): string
    {
        return sprintf(
            '%s – %s',
            $this->start->format('d M Y'),
            $this->end->format('d M Y'),
        );
    }

    public static function forMonth(Carbon $month): self
    {
        $start = $month->copy()->startOfMonth()->startOfDay();
        $end = $month->copy()->endOfMonth()->startOfDay();

        return new self($start, $end);
    }

    public static function resolve(?string $monthOption = null, ?Carbon $reference = null): self
    {
        if ($monthOption !== null && $monthOption !== '') {
            try {
                $parsed = Carbon::createFromFormat('Y-m', $monthOption)->startOfMonth();
            } catch (\Throwable) {
                throw new InvalidArgumentException('The month option must be in YYYY-MM format.');
            }

            return self::forMonth($parsed);
        }

        $reference ??= today();

        return self::forMonth($reference);
    }
}
