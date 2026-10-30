<?php

namespace App\Console\Commands;

use App\Models\PaymentMethodAccess;
use App\Payment\Elavon\ElavonExternalSubscriptionFactory;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChargeTrialPaymentMethodAccess extends Command
{
    protected $signature = 'payment-method-access:charge-trials
                            {--dry-run : List expired trials without charging}
                            {--id= : Only process this payment method access ID}';

    protected $description = 'Charge the signup fee for external payment method accesses whose free trial has ended';

    public function handle(ElavonExternalSubscriptionFactory $factory): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $accesses = PaymentMethodAccess::query()
            ->with('subscription')
            ->where('status', 1)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->whereNull('trial_charged_at')
            ->when(filled($this->option('id')), fn (Builder $query) => $query->whereKey($this->option('id')))
            ->orderBy('id')
            ->get();

        if ($accesses->isEmpty()) {
            $this->warn('No expired trials to charge.');

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($accesses as $access) {
            $fee = $access->fee();
            $this->line("→ #{$access->id} {$access->company_name} fee: ".number_format($fee, 2).' NOK');

            if ($dryRun) {
                $this->comment('    DRY-RUN: would charge now.');

                continue;
            }

            try {
                $subscription = $access->subscription;
                $result = $fee > 0
                    ? $factory->make($access)->charge($fee, $subscription, ['type' => 'trial_conversion'])
                    : ['status' => true];

                if ($result['status'] ?? false) {
                    // fee() is the signup charge pro-rated to the end of the month, so the
                    // access is paid through month end and the monthly run must not bill it again.
                    $paidThrough = now()->endOfMonth();

                    $subscription->update(['paid_at' => $paidThrough]);
                    $access->update(['trial_charged_at' => now(), 'last_paid_at' => $paidThrough]);

                    $this->info('    OK: trial converted.');

                    continue;
                }

                $message = (string) data_get($result, 'data.message', 'Charge failed');
                $this->markFailed($access, $message);
                $this->error("    FAIL: {$message}");
                $failed++;
            } catch (Throwable $e) {
                $this->markFailed($access, $e->getMessage());
                $this->error('    ERROR: '.$e->getMessage());
                $failed++;
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function markFailed(PaymentMethodAccess $access, string $message): void
    {
        $access->update(['status' => 0]);
        $access->subscription?->update(['status' => 0]);

        Log::warning('Trial conversion charge failed', [
            'payment_method_access_id' => $access->id,
            'error' => $message,
        ]);
    }
}
