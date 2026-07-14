<?php

namespace App\Services\Subscription;

use App\Mail\ShopInvoice;
use App\Models\Charge;
use App\Payment\Elavon\ElavonShopSubscription;
use App\Services\RetailerCommission;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Iziibuy;

trait ElavonSubscriptionServiceTrait
{
    protected function createSubscriptionWithElavon()
    {
        $subscription = (new ElavonShopSubscription($this->shop))->createSubscription();

        if ($subscription['status'] !== true) {
            throw new Exception($subscription['data']['message'] ?? 'Subscription failed');
        }

        if (! empty($subscription['data']['requires_hpp'])) {
            $this->shop->payment_url = $subscription['data']['url'];
            $this->shop->payment_order_id = $subscription['data']['payment_id'] ?? null;
            $this->shop->save();

            return $subscription['data']['url'];
        }

        $this->shop->shopperId = $subscription['data']['shopperId'];
        $this->shop->subscription_id = $subscription['data']['cardId'];
        $this->shop->save();

        return route('shop.confirm.subscription', $subscription['data']['cardId']);
    }

    protected function confirmSubscriptionWithElavon()
    {
        $elavon = new ElavonShopSubscription($this->shop);
        $storedCard = $elavon->getStoredCardResource();

        if (! $storedCard->isSuccess()) {
            return redirect(route('shop.subscription.payment'))->withErrors('There is a problem with your Payment method. Please try again later');
        }

        if ($this->shop->status == 1) {
            return redirect(route('shop.complete.signup'))->with('Thank your for subscribe');
        }

        if ($this->shop->paid_at && $this->shop->paid_at->isSameMonth(today())) {
            $this->shop->status = 1;
            $this->shop->establishment = 1;
            $this->shop->save();

            return redirect(route('shop.dashboard'))->with('Thank your for subscribe');
        }

        return $this->activateShopAfterElavonSubscription($elavon);
    }

    protected function activateShopAfterElavonSubscription(ElavonShopSubscription $elavon)
    {
        $amount = (float) Iziibuy::round_num($this->shop->subscriptionFee());
        $transactionId = (string) ($this->shop->elavon_initial_transaction_id ?: $this->shop->payment_order_id ?: '');

        if ($transactionId !== '' && ! filled($this->shop->elavon_initial_transaction_id)) {
            $this->shop->elavon_initial_transaction_id = $transactionId;
        }

        try {
            Mail::to($this->shop->user->email)->send(new ShopInvoice($this->shop));
        } catch (Exception $e) {
            Log::error('Error sending shop invoice: '.$e->getMessage());
        }

        if ($amount > 0 && $transactionId !== '') {
            Charge::create([
                'shop_id' => $this->shop->id,
                'order_id' => $transactionId,
                'amount' => $amount,
                'status' => true,
                'comment' => 'subscription fee',
                'details' => json_encode($this->shop->subscriptionFeeDetails()),
            ]);
        }

        $this->shop->status = 1;
        $this->shop->establishment = 1;
        $this->shop->paid_at = Carbon::now();
        if ($this->shop->retailer_id) {
            RetailerCommission::one_time_pay_out($this->shop)->pay();
            RetailerCommission::commission_from_recurring_payments($this->shop)->pay();
        }
        $this->shop->save();

        return redirect(route('shop.complete.signup'))->with('Thank your for subscribe');
    }
}
