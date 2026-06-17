<?php

namespace App\Http\Controllers\Test;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Payment\Elavon\ElavonShopSubscription;
use App\Services\Elavon\ElavonShopSubscriptionBilling;
use App\Services\Elavon\ElavonShopSubscriptionNotificationHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class ElavonShopSubscriptionTestController extends Controller
{
    /**
     * Inspect or trigger Elavon shop subscription resources (local/testing only).
     *
     * Query: action=plan|subscription|sync-plan|cancel|notification (default subscription)
     *        notification_id= when action=notification
     */
    public function __invoke(Request $request, Shop $shop): JsonResponse
    {
        if (! app()->environment('local', 'testing')) {
            abort(404);
        }

        $action = (string) $request->query('action', 'subscription');

        try {
            $payload = match ($action) {
                'plan' => $this->planPayload($shop),
                'subscription' => $this->subscriptionPayload($shop),
                'sync-plan' => $this->syncPlanPayload($shop),
                'cancel' => $this->cancelPayload($shop),
                'notification' => $this->notificationPayload($request),
                default => throw new InvalidArgumentException(
                    'Invalid action. Use plan, subscription, sync-plan, cancel, or notification.',
                ),
            };
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 500);
        }

        return response()->json(array_merge([
            'success' => true,
            'shop_id' => $shop->id,
            'action' => $action,
        ], $payload));
    }

    /**
     * @return array<string, mixed>
     */
    protected function planPayload(Shop $shop): array
    {
        if (! filled($shop->elavon_plan_id)) {
            throw new InvalidArgumentException('Shop has no elavon_plan_id.');
        }

        $response = (new ElavonShopSubscription($shop))->getConvergePlan();

        return [
            'plan_id' => $shop->elavon_plan_id,
            'api_success' => $response?->isSuccess() ?? false,
            'data' => $response?->getData(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function subscriptionPayload(Shop $shop): array
    {
        if (! filled($shop->elavon_subscription_id)) {
            throw new InvalidArgumentException('Shop has no elavon_subscription_id.');
        }

        $response = (new ElavonShopSubscription($shop))->planSubscription()->getConvergeSubscription();

        return [
            'subscription_id' => $shop->elavon_subscription_id,
            'api_success' => $response->isSuccess(),
            'data' => $response->getData(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function syncPlanPayload(Shop $shop): array
    {
        return [
            'synced' => ElavonShopSubscriptionBilling::syncRecurringPlan($shop),
            'recurring_amount' => $shop->elavonRecurringSubscriptionAmount(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function cancelPayload(Shop $shop): array
    {
        return [
            'cancelled' => ElavonShopSubscriptionBilling::cancel($shop),
            'subscription_id' => $shop->elavon_subscription_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function notificationPayload(Request $request): array
    {
        $notificationId = $request->query('notification_id');
        if (! is_string($notificationId) || trim($notificationId) === '') {
            throw new InvalidArgumentException('notification_id query parameter is required.');
        }

        $handled = (new ElavonShopSubscriptionNotificationHandler)
            ->handleNotificationId(trim($notificationId));

        return [
            'notification_id' => trim($notificationId),
            'handled' => $handled,
        ];
    }
}
