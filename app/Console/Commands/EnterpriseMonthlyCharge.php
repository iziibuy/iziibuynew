<?php

namespace App\Console\Commands;

use App\Models\Enterprise;
use App\Payment\Elavon\ElavonEnterpriseSubscription;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class EnterpriseMonthlyCharge extends Command
{
    protected $signature = 'enterprise:payment
                            {status=0 : Status to set on failed charge (0=deactivate, 1=keep active)}
                            {--dry-run : List due enterprises without charging}
                            {--id= : Only process this enterprise ID}';

    protected $description = 'Charge active enterprises via Elavon stored card';

    public function handle(): int
    {
        $failStatus = (int) $this->argument('status');
        $dryRun = (bool) $this->option('dry-run');
        $onlyId = $this->option('id');

        $this->info('Enterprise monthly charge');
        $this->line('  Mode: '.($dryRun ? 'dry-run (no charges)' : 'live'));
        $this->line('  Fail status: '.$failStatus);
        $this->line('  Due rule: status=1 AND paid_at < '.now()->startOfMonth()->toDateTimeString());
        if (filled($onlyId)) {
            $this->line('  Filter ID: '.$onlyId);
        }
        $this->newLine();

        $query = $this->dueEnterprisesQuery($onlyId);
        $enterprises = $query->get();

        if ($enterprises->isEmpty()) {
            $this->warn('No enterprises matched the due criteria.');
            $this->explainWhyNothingMatched($onlyId);

            return self::SUCCESS;
        }

        $this->info('Found '.$enterprises->count().' enterprise(s) to process.');
        $this->newLine();

        $charged = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($enterprises as $enterprise) {
            $label = $this->enterpriseLabel($enterprise);
            $this->line("→ #{$enterprise->id} {$label}");
            $this->line('    paid_at: '.$this->formatTimestamp($enterprise->paid_at));
            $this->line('    subscription_id (stored card): '.($enterprise->subscription_id ?: 'missing'));
            $this->line('    subscription row: '.($enterprise->subscription ? 'yes (status='.(int) $enterprise->subscription->status.')' : 'missing'));

            Log::info('Charge attempt start: '.$enterprise->id);

            if (! $enterprise->subscription || ! filled($enterprise->subscription_id)) {
                $this->warn('    SKIP: missing subscription or stored card — deactivating.');
                if (! $dryRun) {
                    $enterprise->status = 0;
                    $enterprise->subscription?->update(['status' => 0]);
                    $enterprise->save();
                }
                $skipped++;
                $this->newLine();

                continue;
            }

            $fee = $this->resolveFee($enterprise);
            $this->line('    fee: '.number_format($fee, 2).' NOK');

            if ($dryRun) {
                $this->comment('    DRY-RUN: would charge now.');
                $skipped++;
                $this->newLine();

                continue;
            }

            try {
                $elavon = new ElavonEnterpriseSubscription($enterprise);
                $result = $elavon->charge($fee, $enterprise->subscription, [
                    'details' => $enterprise->details(),
                    'type' => 'monthly',
                ]);

                Log::info(json_encode($result));

                if ($result['status'] ?? false) {
                    $enterprise->paid_at = now();
                    $enterprise->subscription->paid_at = now();
                    $enterprise->save();
                    $enterprise->subscription->save();

                    $tx = $result['transaction_id'] ?? data_get($result, 'data.transactionId') ?? '-';
                    $this->info("    OK: charged. transaction={$tx}");
                    Log::info('Charge complete: '.$enterprise->id);
                    $charged++;
                } else {
                    $message = (string) (data_get($result, 'data.message') ?? data_get($result, 'message') ?? 'Charge failed');
                    $enterprise->status = $failStatus;
                    $enterprise->subscription->status = $failStatus;
                    $enterprise->save();
                    $enterprise->subscription->save();

                    $this->error("    FAIL: {$message} (status set to {$failStatus})");
                    Log::info('Charge failed: '.$enterprise->id);
                    $failed++;
                }
            } catch (Throwable $e) {
                $this->error('    ERROR: '.$e->getMessage());
                Log::warning('Enterprise charge exception', [
                    'enterprise_id' => $enterprise->id,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }

            Log::info('Charge attempt finished: '.$enterprise->id);
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
     * @return Builder<Enterprise>
     */
    protected function dueEnterprisesQuery(mixed $onlyId): Builder
    {
        return Enterprise::query()
            ->with('subscription')
            ->where('status', 1)
            ->where('paid_at', '<', now()->startOfMonth())
            ->when(filled($onlyId), fn (Builder $query) => $query->whereKey($onlyId))
            ->orderBy('id');
    }

    protected function explainWhyNothingMatched(mixed $onlyId): void
    {
        if (filled($onlyId)) {
            $enterprise = Enterprise::query()->with('subscription')->find($onlyId);
            if (! $enterprise) {
                $this->line("  Enterprise #{$onlyId} does not exist.");

                return;
            }

            $this->line("  Enterprise #{$onlyId} exists but is not due:");
            $this->line('    status: '.(int) $enterprise->status.' (need 1)');
            $this->line('    paid_at: '.$this->formatTimestamp($enterprise->paid_at).' (need < '.now()->startOfMonth()->toDateString().')');
            $this->line('    subscription_id: '.($enterprise->subscription_id ?: 'missing'));

            return;
        }

        $active = Enterprise::query()->where('status', 1)->count();
        $dueByPaidAt = Enterprise::query()
            ->where('status', 1)
            ->where('paid_at', '<', now()->startOfMonth())
            ->count();
        $paidThisMonth = Enterprise::query()
            ->where('status', 1)
            ->where('paid_at', '>=', now()->startOfMonth())
            ->count();

        $this->line("  Active enterprises: {$active}");
        $this->line("  Active + paid before this month (due): {$dueByPaidAt}");
        $this->line("  Active + already paid this month: {$paidThisMonth}");
    }

    protected function enterpriseLabel(Enterprise $enterprise): string
    {
        return (string) ($enterprise->enterprise_name
            ?? $enterprise->name
            ?? $enterprise->domain
            ?? $enterprise->unqid
            ?? '');
    }

    protected function formatTimestamp(mixed $value): string
    {
        if (blank($value)) {
            return 'null';
        }

        return Carbon::parse($value)->toDateTimeString();
    }

    protected function resolveFee(Enterprise $enterprise): float
    {
        $subscriptionFee = $enterprise->subscription?->fee;
        if (filled($subscriptionFee) && is_numeric($subscriptionFee) && (float) $subscriptionFee > 0) {
            return (float) $subscriptionFee;
        }

        $enterpriseFee = $enterprise->subscription_fee;
        if (filled($enterpriseFee) && is_numeric($enterpriseFee) && (float) $enterpriseFee > 0) {
            return (float) $enterpriseFee;
        }

        try {
            $details = $enterprise->details();
            if ($details && isset($details->total_fee) && is_numeric($details->total_fee)) {
                return (float) $details->total_fee;
            }
        } catch (Throwable) {
            // fall through
        }

        return 299.0;
    }
}
