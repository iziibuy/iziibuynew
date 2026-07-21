<?php

namespace App\Http\Controllers\Dashboard\Shop;

use App\Filament\Resources\Shops\ShopResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShopRegisterRequest;
use App\Mail\NotificationEmail;
use App\Mail\ServiceSubscriptionMail;
use App\Mail\WelcomeEmail;
use App\Models\Charge;
use App\Models\Shop;
use App\Models\User;
use App\Payment\Elavon\ElavonShopSubscription;
use App\Payment\Elavon\ElavonShopSubscriptionFactory;
use App\Rules\CreditCardValidation;
use App\Services\Elavon\ElavonShopSubscriptionBilling;
use App\Services\RetailerCommission;
use App\Services\Subscription\ShopSubscriptionService;
use Exception;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Iziibuy;

class RegisterController extends Controller
{
    /**
     * This method return shop register page as response
     *
     * @return void
     */
    public function register_form()
    {
        if (
            request()->filled('refferal') &&
            User::where('role_id', 5)->where('id', request()->refferal)->count() &&
            ! Session::has('refferal')
        ) {
            Session::put('refferal', request()->refferal);
        }

        return view('dashboard.shop.auth.register');
    }

    public function register(ShopRegisterRequest $request, Guard $auth)
    {

        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'last_name' => $request->last_name,
                'role_id' => User::ROLES['Vendor'],
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
            ]);

            $shop = Shop::create([
                'is_demo' => true,
                'user_id' => $user->id,
                'user_name' => $request->user_name,
                'retailer_id' => Session::get('refferal'),
                'establishment_cost' => setting('payment.estibliment_cost') ? setting('payment.estibliment_cost') : 1995,
                'monthly_cost' => setting('payment.price_per_shop') ? setting('payment.price_per_shop') : 299,
                'service_establishment_cost' => setting('payment.service_establishment_cost') ? setting('payment.service_establishment_cost') : 1995,
                'service_monthly_fee' => setting('payment.service_monthly_cost') ? setting('payment.service_monthly_cost') : 299,
                'per_user_fee' => setting('payment.price_per_user') ? setting('payment.price_per_user') : 99,
                'terms' => setting('terms.no'),
                'subscriptionMethod' => 'elavon',
            ]);

            $shop->createMetas([
                'name' => $request->company_name,
                'company_name' => $request->company_name,
                'title' => $request->title,
                'logo' => 'defaults/shop_default_logo.png',
                'shop_color' => '#203864',
                'shop_bottom_color' => '#203864',
                'header_color' => '#96afe8',
                'menu_color' => '#203864',
                'menu_hover_color' => '#203864',
                'top_header_language_text_color' => '#203864',
                'top_header_language_text_hover_color' => '#96afe8',
                'top_header_search_bar_text_color' => '#203864',
                'top_header_search_bar_bg_color' => '#ffffff',
                'top_header_search_btn_text_color' => '#ffffff',
                'top_header_search_btn_hover_color' => '#000000',
                'footer_bg_color' => '#96afe8',
                'footer_text_color' => '#ffffff',
                'footer_text_hover_color' => '#f1f1f1',
                'buy_btn_bg_color' => '#3fa24f',
                'buy_btn_text_color' => '#ffffff',
                'buy_btn_hover_color' => '#ffffff',
            ]);

            $user->update([
                'shop_id' => $user->shop->id,
            ]);

            return $user;
        });
        $message = $user->name.' '.$user->last_name.' has created new shop '.$user->shop_name;
        $mail_data = [
            'subject' => 'New shop registered',
            'body' => $message,
            'button_link' => ShopResource::getUrl(panel: 'admin'),
            'button_text' => 'View Shop',
            'emails' => [],
        ];

        Mail::to(setting('site.email'))->send(new NotificationEmail($mail_data));
        // send email to shop
        $message = 'Welcome! <br>
         Thank you for signing up with us.<br>
         Your new account has been setup and you can now login to your area using the details below.<br>
         Url For Your Shop: '.route('shop.home', ['user_name' => $user->shop->user_name]).'<br> Email Address: '.$user->email.' <br>Password: HIDDEN';
        $mail_data = [
            'subject' => 'Thank you for creating new shop',
            'body' => $message,
            'button_link' => route('shop.home', ['user_name' => $user->shop->user_name]),
            'button_text' => 'View Shop',
            'emails' => [],
        ];

        // Mail::to($user->email)->send(new WelcomeEmail(['email' => $user->email, 'url' => route('shop.home', ['user_name' => $user->shop->user_name]), 'password' => 'HIDDEN']));
        // Mail::to($user->email)->send(new NotificationEmail($mail_data));

        session()->forget('refferal');

        $auth->login($user);

        return redirect()->route('shop.dashboard');
    }

    public function updateProfile(Request $request)
    {
        $shop = auth()->user()->shop;
        $shop->update([
            'country' => $request->country,
            'user_name' => $request->user_name,
            'default_currency' => $request->default_currency,
            'currencies' => json_encode($request->currencies),
            'selling_location_mode' => $request->selling_location_mode,
        ]);
        $shop->createMetas($request->meta);

        session()->flash('success', 'profile updated succesfully');
        if (auth()->user()->shop->status) {
            return redirect()->back();
        } else {
            return redirect()->route('shop.subscription.payment');
        }
    }

    public function completeProfile()
    {
        $shop = auth()->user()->shop;

        return view('dashboard.shop.profile-complete', compact('shop'));
    }

    public function completeProfileUpdate(Request $request)
    {
        $request->validate(
            [
                'country' => ['required', 'string', 'max:20'],
                'city' => ['required', 'string', 'max:20'],
                'street' => ['required', 'string', 'max:40'],
                'post_code' => ['required', 'string', 'max:10'],
                'contact_email' => ['required', 'email', 'string', 'max:50'],
                'contact_phone' => ['required', 'string', 'max:15'],
                'company_registration' => ['required', 'string', 'max:30'],
                // 'card_holder_name' => ['required', new CreditCardValidation],
                // 'card_number' => ['required', new CreditCardValidation],
                // 'expiration_month' => ['required', new CreditCardValidation],
                // 'expiration_year' => ['required', new CreditCardValidation],
                // 'ccv' => ['required', new CreditCardValidation],
            ]
        );
        try {
            $user = auth()->user();
            $shop = $user->shop->createMetas([
                'country' => $request->country,
                'company_registration' => $request->company_registration,
                'city' => $request->city,
                'street' => $request->street,
                'post_code' => $request->post_code,
                'contact_email' => $request->contact_email,
                'contact_phone' => $request->contact_phone,
                // 'card_holder_name' => $request->card_holder_name,
                // 'card_number' => $request->card_number,
                // 'expiration_month' => $request->expiration_month,
                // 'expiration_year' => $request->expiration_year,
                // 'ccv' => $request->ccv
            ]);

            return redirect()->route('shop.subscription.payment');
        } catch (Exception $e) {
            return redirect()->back()->withErrors($e->getMessage());
        }

        return redirect()->back()->withErrors('Something went wrong');
    }

    public function subscriptionIndex(Request $request)
    {
        if (auth()->user()->role_id == 3) {
            $shop = auth()->user()->shop;

            if ($this->shouldFinalizeElavonSubscriptionFromRequest($request, $shop)) {
                return $this->elavonSubscriptionReturn($request);
            }

            return view('dashboard.shop.subscription');
        }

        return redirect(route('home'));
    }

    public function deleteAccount()
    {
        auth()->user()->delete();

        return redirect(route('home'))->withErrors('Account Deleted');
    }

    public function enrollSubscription()
    {
        $shop = auth()->user()->shop;

        if (request()->has('type')) {
            ElavonShopSubscriptionBilling::cancel($shop);

            $shop->update([
                'subscription_id' => null,
                'shopperId' => null,
                'elavon_plan_id' => null,
                'elavon_subscription_id' => null,
            ]);
        }

        if (! request()->has('type') && $shop->subscription_id) {
            if ($shop->subscriptionMethod === 'elavon') {
                return redirect()->route('shop.confirm.subscription', [
                    'subscription_id' => $shop->subscription_id,
                ]);
            }

            $amount = Iziibuy::round_num($shop->subscriptionFee());
            $charge_status = app(ElavonShopSubscriptionFactory::class)->make($shop)->chargeViaCard($amount);

            if ($charge_status['status'] == true && ($charge_status['data']['status'] ?? false)) {
                Charge::create([
                    'shop_id' => $shop->id,
                    'order_id' => $charge_status['data']['id'],
                    'amount' => $amount,
                    'status' => true,
                    'comment' => 'subscription fee',
                    'details' => json_encode($shop->subscriptionFeeDetails()),
                ]);
                $shop->is_demo = false;
                $shop->status = 1;
                $shop->establishment = 1;
                $shop->paid_at = Carbon::now();
                $shop->save();
                if ($shop->retailer_id) {
                    RetailerCommission::commission_from_recurring_payments($shop)->pay();
                }

                return redirect(route('shop.dashboard'));
            }

            return redirect(route('shop.subscription.payment'))->withErrors('There is a problem with your Payment method. Please try again later');
        }

        try {
            $target = ShopSubscriptionService::createSubscription($shop);

            return redirect($target);
        } catch (Exception $e) {
            return redirect(route('shop.subscription.payment'))->withErrors($e->getMessage());
        }
    }

    public function elavonSubscriptionReturn(Request $request)
    {
        $shop = auth()->user()->shop;

        $sessionId = $this->elavonSessionIdFromRequest($request, $shop);

        if (! is_string($sessionId) || trim($sessionId) === '') {
            return redirect()->route('shop.subscription.payment')
                ->withErrors('Payment session is missing.');
        }

        $sessionId = trim($sessionId);
        if (strlen($sessionId) > 255) {
            return redirect()->route('shop.subscription.payment')
                ->withErrors('Invalid payment session.');
        }

        Log::info('Elavon shop subscription: HPP return', [
            'shop_id' => $shop->id,
            'session_id' => $sessionId,
        ]);

        $result = app(ElavonShopSubscriptionFactory::class)
            ->make($shop)
            ->finalizeHostedSubscriptionFromSession($sessionId);

        if (! $result['status']) {
            Log::warning('Elavon shop subscription: HPP return failed', [
                'shop_id' => $shop->id,
                'session_id' => $sessionId,
                'message' => $result['data']['message'] ?? null,
            ]);

            return redirect()->route('shop.subscription.payment')
                ->withErrors($result['data']['message'] ?? 'Payment could not be completed.');
        }

        $shop->refresh();

        // Activate immediately after HPP finalize (same as enterprise/plugin), instead of an
        // extra redirect that can bounce unpaid shops back to the subscription page.
        return ShopSubscriptionService::confirmSubscription($shop);
    }

    protected function shouldFinalizeElavonSubscriptionFromRequest(Request $request, Shop $shop): bool
    {
        if (filled($shop->subscription_id)) {
            return false;
        }

        if ($request->filled('sessionId') || $request->filled('session_id')) {
            return true;
        }

        return false;
    }

    protected function elavonSessionIdFromRequest(Request $request, Shop $shop): ?string
    {
        $sessionId = $request->input('sessionId')
            ?? $request->input('session_id')
            ?? $request->query('sessionId')
            ?? $request->query('session_id')
            ?? $shop->payment_order_id;

        return is_string($sessionId) ? $sessionId : null;
    }

    public function elavonSubscriptionCancel()
    {
        return redirect()->route('shop.subscription.payment')
            ->withErrors('Payment cancelled.');
    }

    public function confirmSubscription(string $subscription_id)
    {
        $shop = auth()->user()->getShop();

        if ($shop->subscription_id !== null && $shop->subscription_id !== $subscription_id) {
            abort(403);
        }

        return ShopSubscriptionService::confirmSubscription($shop);
    }

    public function serviceSubscription()
    {
        $shop = Auth::user()->shop;

        if ($shop->can_provide_service) {
            abort('403', 'You already subscribed for service');
        }

        return view('dashboard.shop.subscribe', compact('shop'));
    }

    public function serviceSubscribe()
    {
        $shop = Auth::user()->shop;

        if ($shop->can_provide_service) {
            abort('403', 'You already subscribed for service');
        }
        try {

            $fee = $shop->ServiceSubscriptionFee();
            $charge_status = (new ElavonShopSubscription($shop))->chargeViaCard($fee);

            if (! ($charge_status['status'] && ($charge_status['data']['status'] ?? false))) {
                throw new Exception($charge_status['data']['message'] ?? 'Subscription attempt failed');
            }

            Charge::create([
                'shop_id' => $shop->id,
                'order_id' => $charge_status['data']['id'],
                'amount' => $fee,
                'status' => true,
                'comment' => 'Service subscription',

            ]);

            Mail::to($shop->user->email)->send(new ServiceSubscriptionMail($shop));

            $shop->update([
                'can_provide_service' => 1,
                'service_establishment' => 1,
            ]);

            return redirect()->route('shop.dashboard')->with('success', 'Now you can provide service');
        } catch (Exception $e) {
            return redirect()->back()->withErrors($e->getMessage());
        }
    }
}
