<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Paid
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        $shop = $user->shop;

        if ($shop->hasAccess()) {
            return $next($request);
        } elseif (
            $shop->company_registration == null &&
            $shop->city == null &&
            $shop->street == null &&
            $shop->post_code == null &&
            $shop->contact_email == null &&
            $shop->contact_phone == null
        ) {
            return redirect(route('shop.completeProfile'))->with('success', 'Please finish your profile before proceeding.');
        }

        $redirect = redirect(route('shop.subscription.payment'));

        if ($shop->trial_used) {
            $redirect->withErrors(__('words.trial_ended_please_pay'));
        }

        return $redirect;
    }
}
