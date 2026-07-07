<?php

namespace App\Console\Commands;

use App\Models\PaymentMethodAccess;
use App\Payment\Elavon\ElavonExternalSubscription;
use Illuminate\Console\Command;

class MonthlyChargePaymentMethodAccess extends Command
{
    protected $signature = 'payment-method-access:charge';

    protected $description = 'Charge active external payment method accesses via Elavon';

    public function handle(): int
    {
        $prevMonth = today()->subMonthsNoOverflow()->startOfMonth();
        $paymentMethods = PaymentMethodAccess::whereBetween('last_paid_at', [
            $prevMonth->toDateTimeString(),
            $prevMonth->copy()->endOfMonth()->toDateTimeString(),
        ])->where('status', 1)->get();

        foreach ($paymentMethods as $data) {
            if ($data->subscription?->status != 1) {
                $data->update(['status' => 0]);

                continue;
            }

            $subscriptionDatabase = $data->subscription;
            $charge = $data->fee();
            $elavon = new ElavonExternalSubscription($data);
            $result = $elavon->charge($charge, $subscriptionDatabase, ['type' => 'monthly']);

            if ($result['status']) {
                $subscriptionDatabase->paid_at = now();
                $subscriptionDatabase->status = true;
                $subscriptionDatabase->establishment_status = true;
                $subscriptionDatabase->save();

                $data->status = true;
                $data->last_paid_at = now();
                $data->save();
            } else {
                $subscriptionDatabase->status = false;
                $subscriptionDatabase->save();

                $data->status = false;
                $data->save();
            }
        }

        return self::SUCCESS;
    }
}
