<?php

namespace App\Console\Commands;

use App\Models\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireTrialShop extends Command
{
    protected $signature = 'shop:expire-trials';

    protected $description = 'Log shops whose free trial ended without a paid signup';

    public function handle(): int
    {
        // Access is revoked by the Paid middleware (status was never set); this only reports.
        $expired = Shop::query()
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->whereNull('trial_charged_at')
            ->where('status', 0)
            ->orderBy('id')
            ->get();

        foreach ($expired as $shop) {
            Log::info('Shop free trial ended without subscription', [
                'shop_id' => $shop->id,
                'trial_ended_at' => $shop->trial_ends_at?->toDateTimeString(),
            ]);
        }

        $this->info('Expired trials awaiting subscription: '.$expired->count());

        return self::SUCCESS;
    }
}
