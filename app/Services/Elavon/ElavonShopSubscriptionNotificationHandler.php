<?php

namespace App\Services\Elavon;

use App\Elavon\Converge2\Client\ClientConfig;
use App\Elavon\Converge2\Converge2;
use App\Mail\NotificationEmail;
use App\Models\Charge;
use App\Models\Shop;
use App\Services\RetailerCommission;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ElavonShopSubscriptionNotificationHandler
{
    public function handleNotificationId(string $notificationId): bool
    {
        $client = $this->platformClient();
        $notification = $client->getNotification($notificationId);

        if (! $notification->isSuccess()) {
            Log::warning('Elavon shop subscription notification: could not load notification', [
                'notification_id' => $notificationId,
            ]);

            return false;
        }

        $eventType = $notification->getEventType();
        if ($eventType === null) {
            return false;
        }

        if (! $eventType->isSaleCaptured() && ! $eventType->isSaleAuthorized()) {
            return true;
        }

        $resourceHref = $notification->getResource();
        if (! is_string($resourceHref) || $resourceHref === '') {
            return false;
        }

        $transactionId = $this->entityIdFromHref($resourceHref);
        if ($transactionId === '') {
            return false;
        }

        $transaction = $client->getTransaction($transactionId);
        if (! $transaction->isSuccess()) {
            return false;
        }

        $shop = $this->resolveShopFromTransaction($transaction);
        if ($shop === null) {
            return false;
        }

        if ($shop->subscriptionMethod !== 'elavon') {
            return true;
        }

        $this->recordSubscriptionBill($shop, $transactionId, $transaction->getTotalAmount());

        return true;
    }

    protected function resolveShopFromTransaction(object $transaction): ?Shop
    {
        $shopperReference = method_exists($transaction, 'getShopperReference')
            ? $transaction->getShopperReference()
            : null;

        if ($shopperReference !== null && $shopperReference !== '') {
            $shop = Shop::query()->find($shopperReference);
            if ($shop !== null) {
                return $shop;
            }
        }

        $customFields = method_exists($transaction, 'getCustomFields')
            ? $transaction->getCustomFields()
            : null;

        if (is_array($customFields) && isset($customFields['shop_id'])) {
            return Shop::query()->find($customFields['shop_id']);
        }

        if (is_object($customFields) && isset($customFields->shop_id)) {
            return Shop::query()->find($customFields->shop_id);
        }

        return null;
    }

    protected function recordSubscriptionBill(Shop $shop, string $transactionId, ?float $amount): void
    {
        $existing = Charge::query()
            ->where('shop_id', $shop->id)
            ->where('order_id', $transactionId)
            ->exists();

        if ($existing) {
            return;
        }

        $isSignupBill = $shop->paid_at === null || $shop->status != 1;
        $chargeAmount = $amount ?? ($isSignupBill ? $shop->subscriptionFee() : $shop->subscriptionFeeFull());

        Charge::create([
            'shop_id' => $shop->id,
            'order_id' => $transactionId,
            'amount' => $chargeAmount,
            'status' => true,
            'comment' => $isSignupBill ? 'subscription fee' : 'Monthly subscription fee',
            'details' => json_encode($shop->subscriptionFeeDetails()),
        ]);

        $shop->status = 1;
        $shop->paid_at = Carbon::now();
        if ($isSignupBill) {
            $shop->establishment = 1;
        }
        $shop->save();

        if ($shop->retailer_id) {
            if ($isSignupBill) {
                RetailerCommission::one_time_pay_out($shop)->pay();
            }
            RetailerCommission::commission_from_recurring_payments($shop)->pay();
        }

        if (! $isSignupBill && $shop->user) {
            $mailData = [
                'subject' => 'Subscription auto renew',
                'body' => 'Your subscription to webshop has auto renewed',
                'button_link' => route('shop.dashboard'),
                'button_text' => 'Visit',
                'emails' => [],
            ];
            try {
                Mail::to($shop->user->email)->send(new NotificationEmail($mailData));
            } catch (\Exception $e) {
                Log::error('Elavon shop subscription renewal email failed: '.$e->getMessage());
            }
        }
    }

    protected function platformClient(): Converge2
    {
        $config = new ClientConfig;
        $config->setMerchantAlias(str_replace(' ', '', (string) config('services.enterprise_elavon.merchant_alias', '')));
        $config->setPublicKey(str_replace(' ', '', (string) config('services.enterprise_elavon.public_key', '')));
        $config->setSecretKey(str_replace(' ', '', (string) config('services.enterprise_elavon.secret_key', '')));

        if (config('services.enterprise_elavon.sandbox')) {
            $config->setSandboxMode();
        }

        return new Converge2($config);
    }

    protected function entityIdFromHref(string $href): string
    {
        $href = trim($href);
        if ($href === '') {
            return '';
        }

        if (! str_contains($href, '/')) {
            return $href;
        }

        $path = parse_url($href, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            $parts = array_values(array_filter(explode('/', $href)));

            return $parts !== [] ? (string) end($parts) : '';
        }

        $parts = array_values(array_filter(explode('/', $path)));

        return $parts !== [] ? (string) end($parts) : '';
    }
}
