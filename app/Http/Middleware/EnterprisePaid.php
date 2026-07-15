<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class EnterprisePaid
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
        $enterprise = $user->enterpriseOnboarding;

        if ($enterprise->canProcessPayments()) {
            return $next($request);
        }

        if (
            $enterprise->company_registration == null &&
            @$enterprise->company_address->city == null &&
            @$enterprise->company_address->street == null &&
            @$enterprise->company_address->post_code == null &&
            @$enterprise->company_email == null &&
            @$enterprise->company_domain == null
        ) {
            return redirect(route('enterprise.completeProfile'))->with('success', 'Please finish your profile before proceeding.');
        }

        $enterprise->subscription()->firstOrCreate([], [
            'fee' => (int) round($enterprise->signupFee()),
        ]);

        return redirect()->route('enterprise.subscription.payment');
    }
}
