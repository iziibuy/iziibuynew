<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enterprise;
use App\Models\Subscription;
use App\Payment\Elavon\ElavonEnterpriseSubscription;
use App\Services\Elavon\ElavonOnboardingPromo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class EnterpriseSubscriptionController extends Controller
{
    private const DEFAULT_SIGNUP_FEE = 299.0;

    public function create(Request $request): JsonResponse|array
    {
        $request->validate([
            'company' => 'required',
            'domain' => 'required',
        ]);

        $enterprise = Enterprise::create([
            'unqid' => uniqid(),
            'enterprise_name' => $request->company,
            'domain' => $request->domain,
            'payment_provider' => 'elavon',
        ]);

        $fee = $this->resolveSignupFee(self::DEFAULT_SIGNUP_FEE);
        $subscription = $enterprise->subscription()->create([
            'fee' => (int) $fee,
            'establishment_status' => 1,
        ]);

        $result = $this->beginHostedSignup($enterprise, $subscription, $fee);
        if (! $result['status']) {
            return response()->json([
                'message' => $result['data']['message'] ?? 'Payment link failed',
            ], is_numeric($result['code'] ?? null) ? (int) $result['code'] : 502);
        }

        return [
            'enterprise' => $enterprise->unqid,
            'url' => $result['data']['url'],
        ];
    }

    public function start(string $uid): JsonResponse|array
    {
        $enterprise = Enterprise::where('unqid', $uid)->firstOrFail();
        $subscription = $enterprise->subscription;
        if (! $subscription) {
            abort(404, 'Subscription not found');
        }

        $fee = $this->resolveSignupFee($this->resolveEnterpriseFee($enterprise));
        $result = $this->beginHostedSignup($enterprise, $subscription, $fee);
        if (! $result['status']) {
            return response()->json([
                'message' => $result['data']['message'] ?? 'Payment link failed',
            ], is_numeric($result['code'] ?? null) ? (int) $result['code'] : 502);
        }

        return [
            'enterprise' => $enterprise->unqid,
            'url' => $result['data']['url'],
        ];
    }

    public function end(string $uid): Enterprise
    {
        $enterprise = Enterprise::where('unqid', $uid)->with('subscription')->firstOrFail();
        $enterprise->status = 0;
        $enterprise->save();
        if ($enterprise->subscription) {
            $enterprise->subscription->update(['status' => 0]);
        }

        return $enterprise->fresh(['subscription']);
    }

    public function show(string $uid): ?Enterprise
    {
        return Enterprise::where('unqid', $uid)->with('subscription')->first();
    }

    public function subscriptionCharges(string $uid)
    {
        $enterprise = Enterprise::where('unqid', $uid)->with('subscription')->firstOrFail();

        return $enterprise->subscription->charges()->latest()->paginate(10);
    }

    public function subscriptionCharge(string $uid, int|string $charge)
    {
        $enterprise = Enterprise::where('unqid', $uid)->with('subscription')->firstOrFail();

        return $enterprise->subscription->charges()->find($charge);
    }

    public function subscription(string $uid): array
    {
        $enterprise = Enterprise::where('unqid', $uid)->with('subscription')->firstOrFail();

        return (new ElavonEnterpriseSubscription($enterprise))->getBillingStatus();
    }

    public function charge(Request $request, string $uid): JsonResponse|array
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'details' => 'required',
        ]);

        $enterprise = Enterprise::where('unqid', $uid)->with('subscription')->firstOrFail();
        $subscription = $enterprise->subscription;
        if (! $subscription) {
            abort(404, 'Subscription not found');
        }

        $elavon = new ElavonEnterpriseSubscription($enterprise);
        $result = $elavon->charge(
            (float) $request->amount,
            $subscription,
            ['details' => $request->details]
        );

        if (! $result['status']) {
            return response()->json([
                'status' => false,
                'message' => $result['data']['message'] ?? 'Charge failed',
                'data' => $result['data'],
            ], 402);
        }

        return [
            'status' => true,
            'data' => $result['data'],
            'transaction_id' => $result['transaction_id'] ?? null,
        ];
    }

    public function getCharge(string $uid, string $id): array
    {
        $enterprise = Enterprise::where('unqid', $uid)->with('subscription')->firstOrFail();

        return (new ElavonEnterpriseSubscription($enterprise))->getTransaction($id);
    }

    /** @return array{status:bool,code?:int,data:array<string,mixed>} */
    protected function beginHostedSignup(Enterprise $enterprise, Subscription $subscription, float $fee): array
    {
        $elavon = new ElavonEnterpriseSubscription($enterprise);
        $result = $elavon->getPaymentLink(
            $fee,
            route('callback.enterprise.elavon.subscription.success', $subscription),
            route('callback.enterprise.elavon.subscription.cancel', $subscription)
        );

        if (! $result['status']) {
            return $result;
        }

        $subscription->update([
            'url' => $result['data']['url'],
            'key' => $result['data']['payment_id'],
            'fee' => (int) round($fee),
            'establishment_status' => 1,
        ]);

        $enterprise->update([
            'payment_url' => $result['data']['url'],
            'subscription_fee' => (int) round($fee),
        ]);

        return $result;
    }

    protected function resolveEnterpriseFee(Enterprise $enterprise): float
    {
        try {
            $details = $enterprise->details();
            if ($details && isset($details->total_fee) && is_numeric($details->total_fee)) {
                return (float) $details->total_fee;
            }
        } catch (Throwable) {
            // fall through
        }

        return self::DEFAULT_SIGNUP_FEE;
    }

    protected function resolveSignupFee(float $baseFeeNok): float
    {
        return ElavonOnboardingPromo::signupFee($baseFeeNok);
    }
}
