<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class ExternalPaid
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response|RedirectResponse)  $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        $paymentMethodAccess = $user->paymentMethodAccess;

        if ($paymentMethodAccess->canProcessPayments()) {
            return $next($request);
        } elseif (
            $paymentMethodAccess->company_registration == null &&
            @$paymentMethodAccess->company_address->city == null &&
            @$paymentMethodAccess->company_address->street == null &&
            @$paymentMethodAccess->company_address->post_code == null &&
            @$paymentMethodAccess->company_email == null &&
            @$paymentMethodAccess->company_domain == null
        ) {
            return redirect(route('external.completeProfile'))->with('success', 'Please finish your profile before proceeding.');
        }

        $subscription = $paymentMethodAccess->subscription()->firstOrCreate([], [
            'fee' => (int) round($paymentMethodAccess->fee()),
        ]);

        return redirect()->route('external.start-subscription', $subscription);
    }
}
