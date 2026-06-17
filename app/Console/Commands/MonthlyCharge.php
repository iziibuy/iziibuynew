<?php

namespace App\Console\Commands;

use App\Models\Charge;
use App\Models\Shop;
use App\Services\RetailerCommission;
use Error;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use QuickPay\QuickPay;

class MonthlyCharge extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monthly:charge {status=0}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $api_key = setting('payment.api_key');
        $callback = route('callback.subscription');
        $quickpay = new QuickPay(":{$api_key}", ["QuickPay-Callback-Url:{$callback}"]);
        $prevMonth = today()->subMonthsNoOverflow()->startOfMonth();
        $shops = Shop::whereBetween(
            'paid_at',
            [$prevMonth->toDateTimeString(), $prevMonth->copy()->endOfMonth()->toDateTimeString()]
        )->where('status', 1)
            ->get();

        foreach ($shops as $shop) {
            try {
                if ($shop->subscriptionMethod === 'elavon' && $shop->usesElavonNativeSubscription()) {
                    continue;
                }

                if ($shop) {
                    if ($shop->subscription_id) {
                        $shop->update([
                            'status' => 0,
                        ]);
                        $order_id = hexdec(uniqid());
                        $payment = $quickpay->request->post(sprintf('/subscriptions/%s/recurring', $shop->subscription_id), [
                            'auto_capture' => true,
                            'amount' => $shop->subscriptionFeeFull() * 100,
                            'order_id' => $order_id,
                        ]);
                        if (isset($payment->asObject()->id)) {
                            Charge::create([
                                'shop_id' => $shop->id,
                                'order_id' => $order_id,
                                'amount' => $shop->subscriptionFeeFull(),
                                'status' => 0,
                                'comment' => 'Monthly subscription fee',
                            ]);
                            if ($shop->retailer_id) {
                                RetailerCommission::commission_from_recurring_payments($shop)->pay();
                            }
                        } else {
                            $shop->update([
                                'status' => $this->argument('status'),
                                'subscription_id' => $this->argument('status') ? $shop->subscription_id : null,
                            ]);
                        }
                    } else {

                        $shop->update([
                            'status' => 0,
                            'subscription_id' => null,
                            'is_demo' => true,
                        ]);
                    }
                }
            } catch (Exception $e) {
                continue;
            } catch (Error $e) {
                continue;
            }
        }
        // Log::info('job Run at ' . now());
    }
}
