<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExternalSubscription;
use App\Payment\Elavon\ApiElavonButtonSubscription;
use Illuminate\Support\Facades\Log;

class ExternalButtonSubscriptionCharger
{
    /**
     * Charge a single active subscription that is due.
     *
     * @return array{status:bool,message:string}
     */
    public function charge(ExternalSubscription $subscription): array
    {
        if (! $subscription->isDueForCharge()) {
            return [
                'status' => false,
                'message' => 'Subscription is not due for charge.',
            ];
        }

        $method = strtolower((string) $subscription->payment_method);

        if ($method === 'surfboard') {
            return [
                'status' => false,
                'message' => 'Surfboard recurring charges are not available yet.',
            ];
        }

        try {
            $result = (new ApiElavonButtonSubscription($subscription))->chargeRenewal();

            if (! $result['status']) {
                $subscription->update(['status' => 'PAST_DUE']);

                return [
                    'status' => false,
                    'message' => (string) ($result['data']['message'] ?? 'Charge failed.'),
                ];
            }

            return [
                'status' => true,
                'message' => 'Charged successfully.',
            ];
        } catch (\Throwable $e) {
            Log::error('External button subscription charge failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            $subscription->update(['status' => 'PAST_DUE']);

            return [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{charged:int,failed:int,skipped:int}
     */
    public function chargeDue(?int $limit = null): array
    {
        $query = ExternalSubscription::query()
            ->where('status', 'ACTIVE')
            ->whereNotNull('stored_card_id')
            ->whereNotNull('next_charge_at')
            ->where('next_charge_at', '<=', now())
            ->orderBy('next_charge_at');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $charged = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($query->cursor() as $subscription) {
            /** @var ExternalSubscription $subscription */
            $result = $this->charge($subscription);
            if ($result['status']) {
                $charged++;
            } elseif ($result['message'] === 'Subscription is not due for charge.') {
                $skipped++;
            } else {
                $failed++;
            }
        }

        return compact('charged', 'failed', 'skipped');
    }
}
