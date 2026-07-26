<?php

namespace App\Console\Commands;

use App\Services\ExternalButtonSubscriptionCharger;
use Illuminate\Console\Command;

class ChargeExternalButtonSubscriptions extends Command
{
    protected $signature = 'external-button-subscriptions:charge {--limit= : Max number of subscriptions to charge}';

    protected $description = 'Charge due external button payment subscriptions (vaulted card MIT renewals)';

    public function handle(ExternalButtonSubscriptionCharger $charger): int
    {
        $limit = $this->option('limit');
        $limit = filled($limit) ? (int) $limit : null;

        $this->info('Charging due external button subscriptions...');

        $result = $charger->chargeDue($limit);

        $this->info("Charged: {$result['charged']}");
        $this->info("Failed: {$result['failed']}");
        $this->info("Skipped: {$result['skipped']}");

        return self::SUCCESS;
    }
}
