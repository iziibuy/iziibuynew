<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Mail\DueNoticeMail;
use App\Services\Subscription\MonthlyBillingPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class DueNoticeSender
{
    /**
     * Queue a due notice for each row that has a valid email address.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{sent: int, skipped: int}
     */
    public function send(Collection $rows, MonthlyBillingPeriod $billingPeriod): array
    {
        $sent = 0;
        $skipped = 0;
        $monthLabel = $billingPeriod->start->format('F Y');

        foreach ($rows->unique(fn (array $row): string => mb_strtolower(trim((string) ($row['email'] ?? '')))) as $row) {
            $email = trim((string) ($row['email'] ?? ''));

            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || (float) ($row['due_amount'] ?? 0) <= 0) {
                $skipped++;

                continue;
            }

            Mail::to($email)->queue(new DueNoticeMail($row, $monthLabel));
            $sent++;
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }
}
