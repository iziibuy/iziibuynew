<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Charge;
use App\Models\ExternalSubscriptionCharge;
use App\Models\SubscriptionCharge;
use App\Services\Admin\ChargeCardLookup;
use App\Services\Admin\ChargeElavonGateway;
use App\Services\Admin\UnifiedChargeLedger;
use Illuminate\Console\Command;

class SyncChargeCardLastFour extends Command
{
    protected $signature = 'charges:sync-card-last4
                            {--dry-run : List charges that would be synced without calling Elavon}
                            {--force : Re-fetch from Elavon even when last4 is already stored}
                            {--limit= : Maximum number of charges to process}
                            {--shop-charge= : Only this shop charge ID}
                            {--subscription-charge= : Only this subscription charge ID}
                            {--external-charge= : Only this button subscription charge ID}';

    protected $description = 'Backfill card last four digits on existing charges from Elavon (transaction, then stored card / subscription id)';

    public function handle(ChargeCardLookup $lookup): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $limit = filled($this->option('limit')) ? max(1, (int) $this->option('limit')) : null;

        $this->info('Sync card last4 from Elavon');
        $this->line('  Mode: '.($dryRun ? 'dry-run' : 'live'));
        $this->line('  Force: '.($force ? 'yes' : 'no'));
        $this->newLine();

        $processed = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        $shopChargeId = $this->option('shop-charge');
        $subscriptionChargeId = $this->option('subscription-charge');
        $externalChargeId = $this->option('external-charge');

        if (filled($shopChargeId)) {
            $this->processShopCharge((int) $shopChargeId, $lookup, $dryRun, $force, $updated, $failed);

            return self::SUCCESS;
        }

        if (filled($subscriptionChargeId)) {
            $this->processSubscriptionCharge((int) $subscriptionChargeId, $lookup, $dryRun, $force, $updated, $failed);

            return self::SUCCESS;
        }

        if (filled($externalChargeId)) {
            $this->processExternalSubscriptionCharge((int) $externalChargeId, $lookup, $dryRun, $force, $updated, $failed);

            return self::SUCCESS;
        }

        Charge::query()
            ->with('shop')
            ->whereHas('shop', fn ($query) => $query->where('subscriptionMethod', 'elavon'))
            ->orderByDesc('id')
            ->chunkById(50, function ($charges) use ($lookup, $dryRun, $force, $limit, &$processed, &$updated, &$skipped, &$failed): bool {
                foreach ($charges as $charge) {
                    if ($limit !== null && $processed >= $limit) {
                        return false;
                    }

                    $processed++;

                    if (! $this->shouldSyncShopCharge($charge, $force)) {
                        $skipped++;

                        continue;
                    }

                    if ($dryRun) {
                        $this->line("  [dry-run] shop charge #{$charge->id} order_id={$charge->order_id}");
                        $skipped++;

                        continue;
                    }

                    $lastFour = $lookup->syncFromElavon([
                        'id' => UnifiedChargeLedger::SOURCE_SHOP.'-'.$charge->id,
                    ], $force);

                    if ($lastFour !== null) {
                        $this->info("  shop charge #{$charge->id} → •••• {$lastFour}");
                        $updated++;
                    } else {
                        $this->warn("  shop charge #{$charge->id} → no card digits from Elavon");
                        $failed++;
                    }
                }

                return true;
            });

        SubscriptionCharge::query()
            ->with('subscription.subscribable')
            ->whereNotNull('elavon_transaction_id')
            ->orderByDesc('id')
            ->chunkById(50, function ($charges) use ($lookup, $dryRun, $force, $limit, &$processed, &$updated, &$skipped, &$failed): bool {
                foreach ($charges as $charge) {
                    if ($limit !== null && $processed >= $limit) {
                        return false;
                    }

                    $processed++;

                    if (! $this->shouldSyncSubscriptionCharge($charge, $force)) {
                        $skipped++;

                        continue;
                    }

                    if ($dryRun) {
                        $this->line("  [dry-run] subscription charge #{$charge->id} tx={$charge->elavon_transaction_id}");
                        $skipped++;

                        continue;
                    }

                    $lastFour = $lookup->syncFromElavon([
                        'id' => UnifiedChargeLedger::SOURCE_SUBSCRIPTION.'-'.$charge->id,
                    ], $force);

                    if ($lastFour !== null) {
                        $this->info("  subscription charge #{$charge->id} → •••• {$lastFour}");
                        $updated++;
                    } else {
                        $this->warn("  subscription charge #{$charge->id} → no card digits from Elavon");
                        $failed++;
                    }
                }

                return true;
            });

        ExternalSubscriptionCharge::query()
            ->with('subscription.paymentMethodAccess')
            ->whereNotNull('elavon_transaction_id')
            ->orderByDesc('id')
            ->chunkById(50, function ($charges) use ($lookup, $dryRun, $force, $limit, &$processed, &$updated, &$skipped, &$failed): bool {
                foreach ($charges as $charge) {
                    if ($limit !== null && $processed >= $limit) {
                        return false;
                    }

                    $processed++;

                    if (! $this->shouldSyncExternalSubscriptionCharge($charge, $force)) {
                        $skipped++;

                        continue;
                    }

                    if ($dryRun) {
                        $this->line("  [dry-run] button subscription charge #{$charge->id} tx={$charge->elavon_transaction_id}");
                        $skipped++;

                        continue;
                    }

                    $lastFour = $lookup->syncFromElavon([
                        'id' => UnifiedChargeLedger::SOURCE_EXTERNAL_SUBSCRIPTION.'-'.$charge->id,
                    ], $force);

                    if ($lastFour !== null) {
                        $this->info("  button subscription charge #{$charge->id} → •••• {$lastFour}");
                        $updated++;
                    } else {
                        $this->warn("  button subscription charge #{$charge->id} → no card digits from Elavon");
                        $failed++;
                    }
                }

                return true;
            });

        $this->newLine();
        $this->table(
            ['Processed', 'Updated', 'Skipped / dry-run', 'No digits from API'],
            [[$processed, $updated, $skipped, $failed]]
        );

        return self::SUCCESS;
    }

    protected function processShopCharge(int $id, ChargeCardLookup $lookup, bool $dryRun, bool $force, int &$updated, int &$failed): void
    {
        $charge = Charge::query()->with('shop')->find($id);
        if ($charge === null) {
            $this->error("Shop charge #{$id} not found.");

            return;
        }

        if ($dryRun) {
            $this->line("Would sync shop charge #{$id} (order_id={$charge->order_id})");

            return;
        }

        $lastFour = $lookup->syncFromElavon([
            'id' => UnifiedChargeLedger::SOURCE_SHOP.'-'.$id,
        ], $force);

        if ($lastFour !== null) {
            $this->info("shop charge #{$id} → •••• {$lastFour}");
            $updated++;
        } else {
            $this->warn("shop charge #{$id} → no card digits from Elavon");
            $failed++;
        }
    }

    protected function processSubscriptionCharge(int $id, ChargeCardLookup $lookup, bool $dryRun, bool $force, int &$updated, int &$failed): void
    {
        $charge = SubscriptionCharge::query()->with('subscription.subscribable')->find($id);
        if ($charge === null) {
            $this->error("Subscription charge #{$id} not found.");

            return;
        }

        if ($dryRun) {
            $this->line("Would sync subscription charge #{$id} (tx={$charge->elavon_transaction_id})");

            return;
        }

        $lastFour = $lookup->syncFromElavon([
            'id' => UnifiedChargeLedger::SOURCE_SUBSCRIPTION.'-'.$id,
        ], $force);

        if ($lastFour !== null) {
            $this->info("subscription charge #{$id} → •••• {$lastFour}");
            $updated++;
        } else {
            $this->warn("subscription charge #{$id} → no card digits from Elavon");
            $failed++;
        }
    }

    protected function shouldSyncShopCharge(Charge $charge, bool $force): bool
    {
        $canLookup = ChargeElavonGateway::looksLikeTransactionId((string) $charge->order_id)
            || filled($charge->shop?->subscription_id);

        if (! $canLookup) {
            return false;
        }

        if ($force) {
            return true;
        }

        $details = json_decode((string) ($charge->getAttributes()['details'] ?? ''), true);

        return ! is_array($details) || blank(data_get($details, 'last4'));
    }

    protected function processExternalSubscriptionCharge(int $id, ChargeCardLookup $lookup, bool $dryRun, bool $force, int &$updated, int &$failed): void
    {
        $charge = ExternalSubscriptionCharge::query()->with('subscription.paymentMethodAccess')->find($id);
        if ($charge === null) {
            $this->error("Button subscription charge #{$id} not found.");

            return;
        }

        if ($dryRun) {
            $this->line("Would sync button subscription charge #{$id} (tx={$charge->elavon_transaction_id})");

            return;
        }

        $lastFour = $lookup->syncFromElavon([
            'id' => UnifiedChargeLedger::SOURCE_EXTERNAL_SUBSCRIPTION.'-'.$id,
        ], $force);

        if ($lastFour !== null) {
            $this->info("button subscription charge #{$id} → •••• {$lastFour}");
            $updated++;
        } else {
            $this->warn("button subscription charge #{$id} → no card digits from Elavon");
            $failed++;
        }
    }

    protected function shouldSyncSubscriptionCharge(SubscriptionCharge $charge, bool $force): bool
    {
        $canLookup = ChargeElavonGateway::looksLikeTransactionId((string) $charge->elavon_transaction_id)
            || filled($charge->subscription?->key);

        if (! $canLookup) {
            return false;
        }

        if ($force) {
            return true;
        }

        $details = json_decode((string) ($charge->getAttributes()['charge_details'] ?? ''), true);

        return ! is_array($details) || blank(data_get($details, 'last4'));
    }

    protected function shouldSyncExternalSubscriptionCharge(ExternalSubscriptionCharge $charge, bool $force): bool
    {
        $canLookup = ChargeElavonGateway::looksLikeTransactionId((string) $charge->elavon_transaction_id)
            || filled($charge->subscription?->stored_card_id);

        if (! $canLookup) {
            return false;
        }

        if ($force) {
            return true;
        }

        $details = json_decode((string) ($charge->getAttributes()['payment_details'] ?? ''), true);

        return ! is_array($details) || blank(data_get($details, 'last4'));
    }
}
