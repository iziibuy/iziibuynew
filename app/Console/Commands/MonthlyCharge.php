<?php

namespace App\Console\Commands;

use App\Models\Charge;
use App\Models\Shop;
use App\Payment\Elavon\ElavonShopSubscription;
use App\Services\RetailerCommission;
use App\Services\Subscription\MonthlyBillingPeriod;
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
    protected $signature = 'monthly:charge
                            {status=0 : Status to set on failed charge (0=deactivate, 1=keep active)}
                            {--month= : Bill for this calendar month (YYYY-MM), default: current month}
                            {--shop= : Only process this shop ID}';

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
        $billingPeriod = MonthlyBillingPeriod::resolve($this->option('month'));
        $paidThrough = $billingPeriod->paidThrough();

        $this->info('Shop monthly charge');
        $this->line('  Billing period: '.$billingPeriod->label());
        $this->line('  paid_at after success: '.$paidThrough->toDateTimeString());
        $this->line('  Due rule: status=1 AND (paid_at IS NULL OR paid_at < '.$paidThrough->toDateTimeString().')');
        if (filled($this->option('shop'))) {
            $this->line('  Filter shop ID: '.$this->option('shop'));
        }
        $this->newLine();

        $api_key = setting('payment.api_key');
        $callback = route('callback.subscription');
        $quickpay = new QuickPay(":{$api_key}", ["QuickPay-Callback-Url:{$callback}"]);
        $shops = Shop::query()
            ->where('status', 1)
            ->where(fn ($query) => $query
                ->whereNull('paid_at')
                ->orWhere('paid_at', '<', $paidThrough->toDateTimeString()))
            ->when(filled($this->option('shop')), fn ($query) => $query->whereKey($this->option('shop')))
            ->get();

        if ($shops->isEmpty()) {
            $this->warn('No shops matched the due criteria.');
            $this->explainWhyNoShopsMatched($billingPeriod);

            return self::SUCCESS;
        }

        $this->info('Found '.$shops->count().' shop(s) to process.');
        $this->newLine();

        foreach ($shops as $shop) {
            try {
                if ($shop->requiresElavonResubscription()) {
                    $shop->update([
                        'status' => 0,
                    ]);

                    continue;
                }

                if ($shop->subscriptionMethod === 'elavon' && $shop->usesElavonNativeSubscription()) {
                    continue;
                }

                if ($shop->usesElavonAppManagedSubscription()) {
                    $shop->update([
                        'status' => 0,
                    ]);

                    $elavon = new ElavonShopSubscription($shop);
                    $result = $elavon->chargeMonthly((float) $shop->subscriptionFeeFull());

                    if ($result['status']) {
                        Charge::create([
                            'shop_id' => $shop->id,
                            'order_id' => (string) ($result['data']['id'] ?? uniqid('elavon_', true)),
                            'amount' => $shop->subscriptionFeeFull(),
                            'status' => 1,
                            'comment' => 'Monthly subscription fee',
                            'details' => json_encode($shop->subscriptionRenewalFeeDetails($billingPeriod)),
                        ]);

                        $shop->update([
                            'status' => 1,
                            'paid_at' => $billingPeriod->paidThrough(),
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
                                'details' => json_encode($shop->subscriptionRenewalFeeDetails($billingPeriod)),
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

    protected function explainWhyNoShopsMatched(MonthlyBillingPeriod $billingPeriod): void
    {
        $paidThrough = $billingPeriod->paidThrough()->toDateTimeString();
        $onlyShop = $this->option('shop');

        if (filled($onlyShop)) {
            $shop = Shop::query()->find($onlyShop);
            if ($shop === null) {
                $this->line("  Shop #{$onlyShop} does not exist.");

                return;
            }

            $this->line("  Shop #{$onlyShop} exists but is not due:");
            $this->line('    status: '.(int) $shop->status.' (need 1)');
            $this->line('    paid_at: '.($shop->paid_at?->toDateTimeString() ?? 'null').' (need null or before '.$paidThrough.')');
            $this->line('    subscription: '.($shop->usesElavonNativeSubscription() ? 'Elavon native (skipped by cron)' : ($shop->subscription_id ? 'QuickPay/Elavon app-managed' : 'missing')));

            return;
        }

        $active = Shop::query()->where('status', 1)->count();
        $due = Shop::query()
            ->where('status', 1)
            ->where(fn ($query) => $query
                ->whereNull('paid_at')
                ->orWhere('paid_at', '<', $paidThrough))
            ->count();

        $this->line("  Active shops: {$active}");
        $this->line("  Active + due for {$billingPeriod->label()}: {$due}");
    }
}
