<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Admin\DueBillCharger;
use App\Services\Subscription\MonthlyBillingPeriod;
use Illuminate\Console\Command;
use InvalidArgumentException;

class DueBillsCharge extends Command
{
    protected $signature = 'due-bills:charge
                            {--month= : Calendar month to charge (YYYY-MM). Default: the previous month}
                            {--dry-run : List due accounts without charging}
                            {--shop= : Only charge this shop ID}
                            {--plugin= : Only charge this payment method access ID}
                            {--enterprise= : Only charge this enterprise ID}';

    protected $description = 'Charge due shops, plugins, and enterprises for one billing month';

    public function handle(DueBillCharger $charger): int
    {
        try {
            $monthOption = $this->option('month');
            $billingPeriod = filled($monthOption)
                ? MonthlyBillingPeriod::resolve((string) $monthOption)
                : MonthlyBillingPeriod::openChargeMonth();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $nextMonth = $billingPeriod->start->copy()->addMonth();

        $this->info('Due bills charge');
        $this->line('  Billing period: '.$billingPeriod->label());
        $this->line('  Mode: '.($this->option('dry-run') ? 'dry-run (no charges)' : 'live'));
        $this->line('  Already paid for this month: skipped');
        $this->line('  Failed charges: stay active and remain on the due list');
        $this->line('  Successful charges leave the '.$billingPeriod->start->format('F Y').' due list');
        $this->line('  They show again for '.$nextMonth->format('F Y').' after '.$nextMonth->format('F Y').' ends');
        $this->newLine();

        $result = $charger->charge(
            billingPeriod: $billingPeriod,
            dryRun: (bool) $this->option('dry-run'),
            shopId: filled($this->option('shop')) ? (int) $this->option('shop') : null,
            pluginId: filled($this->option('plugin')) ? (int) $this->option('plugin') : null,
            enterpriseId: filled($this->option('enterprise')) ? (int) $this->option('enterprise') : null,
        );

        $this->line(rtrim($result['output']));

        return $result['exit_code'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
