<?php

namespace App\Console\Commands;

use App\Models\Enterprise;
use App\Payment\Elavon\ElavonEnterpriseSubscription;
use Error;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class EnterpriseMonthlyCharge extends Command
{
    protected $signature = 'enterprise:payment {status=0}';

    protected $description = 'Charge active enterprises via Elavon stored card';

    public function handle(): int
    {
        $enterprises = Enterprise::where('status', 1)
            ->where('paid_at', '<', now()->startOfMonth())
            ->get();

        foreach ($enterprises as $enterprise) {
            Log::info('Charge attempt start: '.$enterprise->id);

            try {
                if (! $enterprise->subscription || ! filled($enterprise->subscription_id)) {
                    $enterprise->status = 0;
                    $enterprise->subscription?->update(['status' => 0]);
                    $enterprise->save();

                    continue;
                }

                $fee = $this->resolveFee($enterprise);
                $elavon = new ElavonEnterpriseSubscription($enterprise);
                $result = $elavon->charge($fee, $enterprise->subscription, [
                    'details' => $enterprise->details(),
                    'type' => 'monthly',
                ]);

                Log::info(json_encode($result));

                if ($result['status']) {
                    $enterprise->paid_at = now();
                    $enterprise->subscription->paid_at = now();
                    $enterprise->save();
                    $enterprise->subscription->save();
                    Log::info('Charge complete: '.$enterprise->id);
                } else {
                    $enterprise->status = (int) $this->argument('status');
                    $enterprise->subscription->status = (int) $this->argument('status');
                    $enterprise->save();
                    $enterprise->subscription->save();
                    Log::info('Charge failed: '.$enterprise->id);
                }
            } catch (Exception|Error|Throwable $e) {
                Log::info($e->getMessage());

                continue;
            }

            Log::info('Charge attempt finished: '.$enterprise->id);
        }

        return self::SUCCESS;
    }

    protected function resolveFee(Enterprise $enterprise): float
    {
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
