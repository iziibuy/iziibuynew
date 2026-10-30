<?php

namespace App\Console\Commands;

use App\Models\PaymentMethodAccess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireTrialPaymentMethodAccess extends Command
{
    protected $signature = 'payment-method-access:expire-trials';

    protected $description = 'Log external payment method accesses whose free trial ended without a paid signup';

    public function handle(): int
    {
        // Access is revoked by the ExternalPaid middleware (status was never set); this only reports.
        $expired = PaymentMethodAccess::query()
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->whereNull('trial_charged_at')
            ->where('status', 0)
            ->orderBy('id')
            ->get();

        foreach ($expired as $access) {
            Log::info('External free trial ended without subscription', [
                'payment_method_access_id' => $access->id,
                'trial_ended_at' => $access->trial_ends_at?->toDateTimeString(),
            ]);
        }

        $this->info('Expired trials awaiting subscription: '.$expired->count());

        return self::SUCCESS;
    }
}
