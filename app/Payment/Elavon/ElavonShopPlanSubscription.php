<?php

namespace App\Payment\Elavon;

use App\Elavon\Converge2\Converge2;
use App\Elavon\Converge2\DataObject\Resource\Endpoint;
use App\Elavon\Converge2\Response\ResponseInterface;
use App\Elavon\Converge2\Response\SubscriptionResponse;
use App\Models\Shop;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Iziibuy;

class ElavonShopPlanSubscription
{
    public function __construct(
        protected Shop $shop,
        protected Converge2 $elavon,
        protected string $apiBase,
    ) {}

    /**
     * @return array{status: bool, code?: int, data: array<string, mixed>}
     */
    public function ensurePlanAndSubscription(): array
    {
        if (! filled($this->shop->subscription_id)) {
            return [
                'status' => false,
                'code' => 422,
                'data' => ['message' => 'No stored card on file for this shop.'],
            ];
        }

        $planResult = $this->ensurePlan();
        if (! $planResult['status']) {
            return $planResult;
        }

        if (filled($this->shop->elavon_subscription_id)) {
            $existing = $this->elavon->getSubscription($this->shop->elavon_subscription_id);
            if ($existing->isSuccess()) {
                return [
                    'status' => true,
                    'code' => 200,
                    'data' => [
                        'planId' => $this->shop->elavon_plan_id,
                        'subscriptionId' => $this->shop->elavon_subscription_id,
                    ],
                ];
            }
        }

        return $this->createConvergeSubscription();
    }

    /**
     * @return array{status: bool, code?: int, data: array<string, mixed>}
     */
    protected function ensurePlan(): array
    {
        if (filled($this->shop->elavon_plan_id)) {
            $existing = $this->elavon->getPlan($this->shop->elavon_plan_id);
            if ($existing->isSuccess()) {
                return [
                    'status' => true,
                    'code' => 200,
                    'data' => ['planId' => $this->shop->elavon_plan_id],
                ];
            }

            Log::warning('Elavon shop subscription: stored plan id not found at Elavon, creating a new plan', [
                'shop_id' => $this->shop->id,
                'plan_id' => $this->shop->elavon_plan_id,
            ]);
        }

        $response = $this->elavon->createPlan($this->buildPlanPayload());
        if (! $response->isSuccess()) {
            return $this->failureFromResponse($response, 'create_plan');
        }

        $planId = $response->getId();
        $this->shop->elavon_plan_id = $planId;
        $this->shop->save();

        return [
            'status' => true,
            'code' => 200,
            'data' => ['planId' => $planId],
        ];
    }

    /**
     * @return array{status: bool, code?: int, data: array<string, mixed>}
     */
    protected function createConvergeSubscription(): array
    {
        $planHref = $this->convergeResourceUrl(Endpoint::PLAN, (string) $this->shop->elavon_plan_id);
        $storedCardHref = $this->convergeResourceUrl(Endpoint::STORED_CARD, (string) $this->shop->subscription_id);

        $payload = [
            'plan' => $planHref,
            'storedCard' => $storedCardHref,
            'timeZoneId' => config('app.timezone', 'Europe/Oslo'),
            'firstBillAt' => $this->firstSubscriptionBillDate(),
            'customReference' => $this->shopCustomReference(),
            'customFields' => $this->shopCustomFields(),
        ];

        $response = $this->elavon->createSubscription($payload);
        if (! $response->isSuccess()) {
            return $this->failureFromResponse($response, 'create_subscription');
        }

        $subscriptionId = $response->getId();
        $this->shop->elavon_subscription_id = $subscriptionId;
        $this->shop->save();

        return [
            'status' => true,
            'code' => 200,
            'data' => [
                'planId' => $this->shop->elavon_plan_id,
                'subscriptionId' => $subscriptionId,
            ],
        ];
    }

    public function getConvergeSubscription(): SubscriptionResponse
    {
        if (! filled($this->shop->elavon_subscription_id)) {
            return new SubscriptionResponse;
        }

        return $this->elavon->getSubscription($this->shop->elavon_subscription_id);
    }

    public function waitForActiveSubscription(int $maxAttempts = 15, int $delayMicros = 2_000_000): bool
    {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $subscription = $this->getConvergeSubscription();
            if ($this->subscriptionIsBillable($subscription)) {
                return true;
            }

            if ($attempt < $maxAttempts - 1) {
                usleep($delayMicros);
            }
        }

        return false;
    }

    public function subscriptionIsBillable(SubscriptionResponse $subscription): bool
    {
        if (! $subscription->isSuccess()) {
            return false;
        }

        $state = $subscription->getSubscriptionState();
        if ($state === null) {
            return false;
        }

        return $state->isActive() || $state->isPastDue();
    }

    /**
     * Push updated monthly amount to the shop's Elavon plan (e.g. after manager count changes).
     *
     * @return array{status: bool, code?: int, data: array<string, mixed>}
     */
    public function syncRecurringPlanAmount(): array
    {
        if (! filled($this->shop->elavon_plan_id)) {
            return [
                'status' => true,
                'code' => 200,
                'data' => ['message' => 'No Elavon plan to sync.'],
            ];
        }

        $payload = [
            'total' => [
                'amount' => (float) Iziibuy::round_num($this->shop->elavonRecurringSubscriptionAmount()),
                'currencyCode' => 'NOK',
            ],
        ];

        $response = $this->elavon->updatePlan($this->shop->elavon_plan_id, $payload);
        if (! $response->isSuccess()) {
            return $this->failureFromResponse($response, 'sync_plan');
        }

        return [
            'status' => true,
            'code' => 200,
            'data' => [
                'planId' => $this->shop->elavon_plan_id,
                'recurringAmount' => $payload['total']['amount'],
            ],
        ];
    }

    /**
     * Cancel the Converge subscription at Elavon (future bills stop).
     *
     * @return array{status: bool, code?: int, data: array<string, mixed>}
     */
    public function cancelConvergeSubscription(): array
    {
        if (! filled($this->shop->elavon_subscription_id)) {
            return [
                'status' => true,
                'code' => 200,
                'data' => ['message' => 'No Elavon subscription to cancel.'],
            ];
        }

        $response = $this->elavon->updateSubscription($this->shop->elavon_subscription_id, [
            'doCancel' => true,
        ]);

        if (! $response->isSuccess()) {
            return $this->failureFromResponse($response, 'cancel_subscription');
        }

        return [
            'status' => true,
            'code' => 200,
            'data' => ['subscriptionId' => $this->shop->elavon_subscription_id],
        ];
    }

    protected function firstSubscriptionBillDate(): string
    {
        return Carbon::now(config('app.timezone', 'Europe/Oslo'))
            ->addMonth()
            ->startOfMonth()
            ->format('Y-m-d');
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPlanPayload(): array
    {
        $recurringAmount = (float) Iziibuy::round_num($this->shop->elavonRecurringSubscriptionAmount());

        return [
            'name' => sprintf('Shop subscription — %s', $this->shop->user_name),
            'description' => sprintf('Monthly webshop subscription for %s', $this->shop->company_name ?: $this->shop->user_name),
            'billingInterval' => [
                'timeUnit' => 'month',
                'count' => 1,
            ],
            'total' => [
                'amount' => $recurringAmount,
                'currencyCode' => 'NOK',
            ],
            'isSubscribable' => true,
            'customReference' => $this->shopCustomReference(),
            'customFields' => $this->shopCustomFields(),
        ];
    }

    protected function shopCustomReference(): string
    {
        return 'shop-'.$this->shop->id;
    }

    /**
     * @return array<string, string>
     */
    protected function shopCustomFields(): array
    {
        return [
            'shop_id' => (string) $this->shop->id,
            'type' => 'shop_subscription',
        ];
    }

    protected function convergeResourceUrl(string $collection, string $idOrHref): string
    {
        $value = trim($idOrHref);
        if ($value === '') {
            return '';
        }
        if (str_contains($value, '://')) {
            return $value;
        }

        return rtrim($this->apiBase, '/').'/'.$collection.'/'.$value;
    }

    /**
     * @return array{status: bool, code: int, data: array{message: string}}
     */
    protected function failureFromResponse(ResponseInterface $response, string $stage): array
    {
        $message = $this->extractFailureMessage($response);

        Log::warning('Elavon shop plan/subscription failed', [
            'stage' => $stage,
            'shop_id' => $this->shop->id,
            'status_code' => $response->getRawResponseStatusCode(),
            'message' => $message,
            'response_body' => $response->getRawResponseBody(),
        ]);

        return [
            'status' => false,
            'code' => $response->getRawResponseStatusCode() ?: 500,
            'data' => ['message' => $message],
        ];
    }

    protected function extractFailureMessage(ResponseInterface $response): string
    {
        $parts = [];

        if ($response->hasFailures()) {
            foreach ($response->getFailures() as $failure) {
                $description = method_exists($failure, 'getDescription') ? (string) $failure->getDescription() : '';
                if ($description !== '') {
                    $parts[] = $description;
                }
            }
        }

        if ($parts === []) {
            $shortError = $response->getShortErrorMessage();
            if (is_string($shortError) && $shortError !== '') {
                $parts[] = $shortError;
            }
        }

        if ($parts === []) {
            $rawError = $response->getRawErrorMessage();
            if (is_string($rawError) && $rawError !== '') {
                $parts[] = $rawError;
            }
        }

        return $parts !== [] ? implode(' | ', $parts) : 'Elavon subscription request failed.';
    }
}
