<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Services\Subscription\MonthlyBillingPeriod;
use Illuminate\Support\Facades\Artisan;

class DueBillCharger
{
    /**
     * Charge shops, plugins, and enterprises that are still due for one month.
     * Accounts already paid through that month are skipped by the charge commands.
     * Failed charges keep the account active so it stays on the due list.
     *
     * @return array{
     *     month: string,
     *     label: string,
     *     dry_run: bool,
     *     due_count: int,
     *     exit_code: int,
     *     output: string
     * }
     */
    public function charge(
        MonthlyBillingPeriod $billingPeriod,
        bool $dryRun = false,
        ?int $shopId = null,
        ?int $pluginId = null,
        ?int $enterpriseId = null,
    ): array {
        if (! $dryRun) {
            set_time_limit(0);
        }

        $month = $billingPeriod->start->format('Y-m');
        $dueCount = app(DueBillsQuery::class)->counts(billingPeriod: $billingPeriod)[UnifiedDueLedger::SCOPE_DUE];
        $runAll = $shopId === null && $pluginId === null && $enterpriseId === null;
        $output = '';
        $exitCode = 0;

        if ($runAll || $shopId !== null) {
            $exitCode = max($exitCode, $this->run('monthly:charge', $this->parameters(
                month: $month,
                dryRun: $dryRun,
                idOption: '--shop',
                id: $shopId,
            ), $output));
        }

        if ($runAll || $pluginId !== null) {
            $exitCode = max($exitCode, $this->run('payment-method-access:charge', $this->parameters(
                month: $month,
                dryRun: $dryRun,
                idOption: '--id',
                id: $pluginId,
            ), $output));
        }

        if ($runAll || $enterpriseId !== null) {
            $exitCode = max($exitCode, $this->run('enterprise:payment', $this->parameters(
                month: $month,
                dryRun: $dryRun,
                idOption: '--id',
                id: $enterpriseId,
            ), $output));
        }

        return [
            'month' => $month,
            'label' => $billingPeriod->label(),
            'dry_run' => $dryRun,
            'due_count' => $dueCount,
            'exit_code' => $exitCode,
            'output' => $output,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function parameters(string $month, bool $dryRun, string $idOption, ?int $id): array
    {
        $parameters = [
            'status' => 1,
            '--month' => $month,
        ];

        if ($dryRun) {
            $parameters['--dry-run'] = true;
        }

        if ($id !== null) {
            $parameters[$idOption] = $id;
        }

        return $parameters;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function run(string $command, array $parameters, string &$output): int
    {
        $exitCode = Artisan::call($command, $parameters);
        $output .= Artisan::output();

        return $exitCode;
    }
}
