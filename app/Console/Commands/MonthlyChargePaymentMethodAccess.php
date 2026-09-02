<?php

namespace App\Console\Commands;

use App\Models\PaymentMethodAccess;
use App\Payment\Elavon\ElavonExternalSubscription;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class MonthlyChargePaymentMethodAccess extends Command
{
    protected $signature = 'payment-method-access:charge
                            {status=0 : Status to set on failed charge (0=deactivate, 1=keep active)}
                            {--dry-run : List due payment method accesses without charging}
                            {--id= : Only process this payment method access ID}';

    protected $description = 'Charge active external payment method accesses (plugins) via Elavon';

    public function handle(): int
    {
        $failStatus = (int) $this->argument('status');
        $dryRun = (bool) $this->option('dry-run');
        $onlyId = $this->option('id');
        $prevMonth = today()->subMonthsNoOverflow()->startOfMonth();

        $this->info('Payment method access (plugin) monthly charge');
        $this->line('  Mode: '.($dryRun ? 'dry-run (no charges)' : 'live'));
        $this->line('  Fail status: '.$failStatus);
        $this->line('  Due rule: status=1 AND last_paid_at BETWEEN '.$prevMonth->toDateTimeString().' AND '.$prevMonth->copy()->endOfMonth()->toDateTimeString());
        if (filled($onlyId)) {
            $this->line('  Filter ID: '.$onlyId);
        }
        $this->newLine();

        $paymentMethods = $this->duePaymentMethodsQuery($prevMonth, $onlyId)->get();

        if ($paymentMethods->isEmpty()) {
            $this->warn('No payment method accesses matched the due criteria.');
            $this->explainWhyNothingMatched($prevMonth, $onlyId);

            return self::SUCCESS;
        }

        $this->info('Found '.$paymentMethods->count().' payment method access(es) to process.');
        $this->newLine();

        $charged = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($paymentMethods as $data) {
            $label = $this->paymentMethodLabel($data);
            $this->line("→ #{$data->id} {$label}");
            $this->line('    last_paid_at: '.$this->formatTimestamp($data->last_paid_at));
            $this->line('    subscription row: '.($data->subscription ? 'yes (status='.(int) $data->subscription->status.')' : 'missing'));

            Log::info('Payment method access charge attempt start: '.$data->id);

            if ($data->requiresElavonResubscription()) {
                $this->warn('    SKIP: requires Elavon resubscription — deactivating.');
                if (! $dryRun) {
                    $data->update(['status' => 0]);
                }
                $skipped++;
                $this->newLine();

                continue;
            }

            if ($data->subscription?->status != 1) {
                $this->warn('    SKIP: subscription missing or inactive — deactivating.');
                if (! $dryRun) {
                    $data->update(['status' => 0]);
                }
                $skipped++;
                $this->newLine();

                continue;
            }

            $fee = $data->fee();
            $this->line('    fee: '.number_format($fee, 2).' NOK');

            if ($dryRun) {
                $this->comment('    DRY-RUN: would charge now.');
                $skipped++;
                $this->newLine();

                continue;
            }

            try {
                $subscriptionDatabase = $data->subscription;
                $elavon = new ElavonExternalSubscription($data);
                $result = $elavon->charge($fee, $subscriptionDatabase, ['type' => 'monthly']);

                Log::info(json_encode($result));

                if ($result['status'] ?? false) {
                    $subscriptionDatabase->paid_at = now();
                    $subscriptionDatabase->status = true;
                    $subscriptionDatabase->establishment_status = true;
                    $subscriptionDatabase->save();

                    $data->status = true;
                    $data->last_paid_at = now();
                    $data->save();

                    $tx = $result['transaction_id'] ?? data_get($result, 'data.transactionId') ?? '-';
                    $this->info("    OK: charged. transaction={$tx}");
                    Log::info('Payment method access charge complete: '.$data->id);
                    $charged++;
                } else {
                    $message = (string) (data_get($result, 'data.message') ?? data_get($result, 'message') ?? 'Charge failed');

                    $subscriptionDatabase->status = $failStatus;
                    $subscriptionDatabase->save();

                    $data->status = $failStatus;
                    $data->save();

                    $this->error("    FAIL: {$message} (status set to {$failStatus})");
                    Log::info('Payment method access charge failed: '.$data->id);
                    $failed++;
                }
            } catch (Throwable $e) {
                $this->error('    ERROR: '.$e->getMessage());
                Log::warning('Payment method access charge exception', [
                    'payment_method_access_id' => $data->id,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }

            Log::info('Payment method access charge attempt finished: '.$data->id);
            $this->newLine();
        }

        $this->info('Summary');
        $this->table(
            ['Charged', 'Failed', 'Skipped / dry-run'],
            [[$charged, $failed, $skipped]]
        );

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return Builder<PaymentMethodAccess>
     */
    protected function duePaymentMethodsQuery(Carbon $prevMonth, mixed $onlyId): Builder
    {
        return PaymentMethodAccess::query()
            ->with('subscription')
            ->where('status', 1)
            ->whereBetween('last_paid_at', [
                $prevMonth->toDateTimeString(),
                $prevMonth->copy()->endOfMonth()->toDateTimeString(),
            ])
            ->when(filled($onlyId), fn (Builder $query) => $query->whereKey($onlyId))
            ->orderBy('id');
    }

    protected function explainWhyNothingMatched(Carbon $prevMonth, mixed $onlyId): void
    {
        if (filled($onlyId)) {
            $data = PaymentMethodAccess::query()->with('subscription')->find($onlyId);
            if (! $data) {
                $this->line("  Payment method access #{$onlyId} does not exist.");

                return;
            }

            $this->line("  Payment method access #{$onlyId} exists but is not due:");
            $this->line('    status: '.(int) $data->status.' (need 1)');
            $this->line('    last_paid_at: '.$this->formatTimestamp($data->last_paid_at).' (need between '.$prevMonth->toDateString().' and '.$prevMonth->copy()->endOfMonth()->toDateString().')');

            return;
        }

        $active = PaymentMethodAccess::query()->where('status', 1)->count();
        $dueByLastPaidAt = PaymentMethodAccess::query()
            ->where('status', 1)
            ->whereBetween('last_paid_at', [
                $prevMonth->toDateTimeString(),
                $prevMonth->copy()->endOfMonth()->toDateTimeString(),
            ])
            ->count();

        $this->line("  Active payment method accesses: {$active}");
        $this->line("  Active + paid last month (due): {$dueByLastPaidAt}");
    }

    protected function paymentMethodLabel(PaymentMethodAccess $data): string
    {
        return (string) ($data->company_name ?: $data->name ?: '');
    }

    protected function formatTimestamp(mixed $value): string
    {
        if (blank($value)) {
            return 'null';
        }

        return Carbon::parse($value)->toDateTimeString();
    }
}
