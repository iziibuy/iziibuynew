<?php

use App\Http\Controllers\ButtonPaymentController;
use App\Http\Controllers\CallbackController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\Dashboard\External\DashboardController as ExternalController;
use App\Http\Controllers\Dashboard\External\ExternalBookingController;
use App\Http\Controllers\Dashboard\Shop\DashboardController;
use App\Http\Controllers\Dashboard\Shop\PaymentController;
use App\Http\Controllers\Dashboard\Shop\RegisterController;
use App\Http\Controllers\Dashboard\Shop\TicketController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\SurfboardPaymentCallback;
use App\Http\Controllers\Test\ElavonShopSubscriptionTestController;
use App\Http\Controllers\Test\SurfboardPaymentTestController;
use App\Models\EnterpriseOnboarding;
use App\Models\ExternalBooking;
use App\Models\Product;
use App\Models\Shipping;
use App\Models\Shop;
use App\Models\Slider;
use App\Models\User;
use App\Payment\External\Elavon\ExternalBookingElavonPayment;
use App\Payment\External\Surfboard\ExternalBookingSurfboardApi;
use App\Payment\Surfboard\SurfboardMarchant;
use App\Services\Reports\FinancialReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Auth::routes();

Route::get('/', function () {
    $user_name = env('default_username');

    if ($user_name) {

        if (request('manager_id')) {
            session()->put('manager_id', request('manager_id'));
        }
        $shop = Shop::where('user_name', $user_name)->first();
        $sliders = Slider::all();
        request()->merge(['user_name' => $user_name]);
        $new_products = Product::where('featured', 1)->with('ratings')->latest()->limit(8)->whereNull('parent_id')->get();

        return view('shop.home', compact('shop', 'new_products', 'sliders'));
    } else {
        return view('welcome');
    }
})->name('home');

Route::group(['controller' => RegisterController::class, 'middleware' => 'permission:enterprise,shop_register'], function () {
    Route::get('/register-as-shop', [RegisterController::class, 'register_form'])->name('shop.register');
    Route::post('/register-as-shop', [RegisterController::class, 'register'])->name('shop.register.post');
});
Route::get('/register-as-external', [ExternalController::class, 'registerForm'])->middleware('guest')->name('external.register');
Route::post('/register-as-external', [ExternalController::class, 'register'])->middleware('guest')->name('external.register.post');

Route::get('posts/{slug}', [HomeController::class, 'posts'])->name('posts');
Route::get('page/{slug}', [HomeController::class, 'pages'])->name('pages');
Route::get('shop-coupon', [CouponController::class, 'shopCoupon'])->name('shop.coupon');
Route::get('shop-delete-coupon', [CouponController::class, 'destroy'])->name('coupon.destroy');

Route::get('/contact', [HomeController::class, 'contact'])->name('contact');
Route::post('/contact-store', [HomeController::class, 'contact_store'])->name('contact.store');

Route::get('/about', [HomeController::class, 'about'])->name('about');
Route::get('faqs', [HomeController::class, 'faqs'])->name('faqs');

Route::group(['middleware' => ['auth', 'role:vendor,manager,retailer']], function () {
    Route::resource('tickets', TicketController::class);
    Route::post('ticket/reply/{ticket}', [TicketController::class, 'reply'])->name('ticket.reply');
    Route::get('ticket/close/{ticket}', [TicketController::class, 'close'])->name('ticket.close');
});

Route::post('send-message/{user}', [MessageController::class, 'send_message'])->name('send.message');
Route::get('send-order-notification', [HomeController::class, 'send_order_notification'])->name('send.order.notification');
Route::post('send-notification', [HomeController::class, 'send_notification'])->name('send.notification');

Route::post('/newsletter/subscribe', [HomeController::class, 'newsletter'])->name('newsletter.subscribe');
Route::any('surfboard/callback', SurfboardPaymentCallback::class)->name('surfboard.callback');
Route::get('button-payment/cancel-callback', [ButtonPaymentController::class, 'cancelCallback'])->name('buttonPayment.cancelCallback');
Route::group(['controller' => CallbackController::class, 'prefix' => 'callback', 'as' => 'callback.'], function () {
    Route::get('/payment/{paymentId}/{order}/success', 'paymentSuccess')->name('payment.success');
    Route::get('/payment/{paymentId}/{order}/cancel', 'paymentCanceled')->name('payment.cancel');
    Route::get('two/payment/{order}/success', 'twoPaymentSuccess')->name('two.payment.success');
    Route::get('elavon/payment/success', 'elavonPaymentSuccess')->name('elavon.payment.success');
    Route::get('elavon/payment/cancel/{order_id}', 'elavonPaymentCancel')->name('elavon.payment.cancel');
    Route::get('api/elavon/payment/success', 'elavonApiPaymentSuccess')->name('api.elavon.payment.success');
    Route::any('api/surfboard/payment/success', 'surfboardApiPaymentSuccess')->name('api.surfboard.payment.success');
    Route::any('api/surfboard/payment/redirect', 'surfboardApiRedirect')->name('api.surfboard.payment.redirect');
    Route::get('api/elavon/payment/cancel/{order_id}', 'elavonApiPaymentCancel')->name('api.elavon.payment.cancel');
    Route::get('subscription/{subscription}/success', 'subscriptionSuccess')->name('subscription.success');
    Route::get('subscription/{subscription}/cancel', 'subscriptionCancel')->name('subscription.cancel');
    Route::get('enterprise/elavon/subscription/{subscription}/success', 'enterpriseElavonSubscriptionSuccess')->name('enterprise.elavon.subscription.success');
    Route::get('enterprise/elavon/subscription/{subscription}/cancel', 'enterpriseElavonSubscriptionCancel')->name('enterprise.elavon.subscription.cancel');
    Route::any('subscription-callback', 'subscriptionCallback')->name('subscription');
    Route::any('elavon/shop-subscription', 'elavonShopSubscriptionNotification')->name('elavon.shop.subscription');

    Route::any('plugin/externalbooking/elavon/success', 'pluginExternalBookingElavonSuccess')->name('plugin.externalbooking.elavon.success');
    Route::any('plugin/externalbooking/elavon/{booking}/cancel', 'pluginExternalBookingElavonCancel')->name('plugin.externalbooking.elavon.cancel');
    Route::any('plugin/externalbooking/surfboard/success', 'pluginExternalBookingSurfboardSuccess')->name('plugin.externalbooking.surfboard.success');
    Route::any('plugin/externalbooking/surfboard/redirect', 'pluginExternalBookingSurfboardRedirect')->name('plugin.externalbooking.surfboard.redirect');
});
Route::get('payment-completed/{externalBooking:ulid}', function (ExternalBooking $externalBooking) {
    return view('paymentcompleted', compact('externalBooking'));
})->name('paymentcompleted');
Route::get('payment-completed/{externalBooking:ulid}/invoice', function (ExternalBooking $externalBooking) {
    if ($externalBooking->payment_method == 'elavon') {
        $response = (new ExternalBookingElavonPayment($externalBooking))->getTransaction();
    } else {
        $response = (new ExternalBookingSurfboardApi($externalBooking))->getTransaction();
    }

    return view('externalbookinginvoice', compact('externalBooking', 'response'));
})->name('externalbookinginvoice');

Route::get('payment-failed/{externalBooking:ulid}', function (ExternalBooking $externalBooking) {
    return view('paymentfailed', compact('externalBooking'));
})->name('paymentfailed');

Route::post('admin/shop/update/{shop}', [DashboardController::class, 'updateProfile'])->middleware('auth', 'role:admin')->name('admin.profile.update');

Route::get('/check-shipping', function (Request $request) {
    $request->validate([
        'shipping' => 'required',
    ]);
    if (auth()->check()) {
        return auth()->user()->checkIfShippingIsValid(Shipping::find($request->shipping)) ? 'true' : 'false';
    } else {
        return 'false';
    }
});

Route::get('subscription/test', function (Request $request) {})->name('subscription.test');

Route::get('{user_name}/current-currency/{symbol}', function ($user_name, $symbol) {

    session()->put('current_currency', [request()->user_name => $symbol]);

    return back();
})->name('set.currency');

Route::get('clear-sessions', function () {
    session()->flush();

    return redirect()->back();
})->name('clear.session');

Route::get('manager-updt', function () {
    $users = User::where('role_id', 3)->whereHas('shop')->whereNull('shop_id')->get();
    foreach ($users as $user) {
        $user->shop_id = $user->shop->id;
        $user->save();
    }
});

Route::get('view-payment-data/{type}/{id}', [PaymentController::class, 'viewPaymentData'])->name('view_payment_data')->middleware(['auth', 'role:external,vendor,enterprise', 'protectedLink']);

Route::get('/enterprise-onboarding-register', function () {
    return view('auth.enterpriseOnboarding');
})->name('enterpriseonboarding.register');

Route::post('/enterprise-onboarding-register', function (Request $request) {
    $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'last_name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
        'phone' => ['required', 'string', 'max:255'],
        'password' => ['required', 'string', 'min:8', 'confirmed'],
    ]);
    try {
        DB::beginTransaction();
        $user = User::create([
            'name' => $request->name,
            'last_name' => $request->last_name,
            'phone' => $request->phone,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => Role::firstOrCreate(['name' => 'enterprise', 'guard_name' => 'web'])->id,
        ]);
        $enterprise = EnterpriseOnboarding::create([
            'user_id' => $user->id,
            'key' => uniqid(),
            'company_address' => [
                'contact_number' => $request->phone,
                'country' => 'Norway',
            ],
            'fee' => 249,
            'establishment_fee' => 4500,
        ]);
        DB::commit();
        Auth::login($user);

        return redirect()->route('enterprise.dashboard');
    } catch (Exception $e) {
        DB::rollBack();

        return redirect()->back()->withErrors($e->getMessage());
    } catch (Error $e) {
        DB::rollBack();

        return redirect()->back()->withErrors($e->getMessage());
    }
})->name('enterpriseonboarding.register.post');

Route::post('/resend-order-email', [HomeController::class, 'resent_order_email'])->name('resend.order.email');

Route::get('/surfboard-marchant', function () {
    $shop = Shop::first();
    $marchants = (new SurfboardMarchant($shop))->marchantList();

    return $marchants;
});

Route::get('payment/booking/{externalBooking:ulid}', function (ExternalBooking $externalBooking) {
    return view('dashboard.external.booking.payment', compact('externalBooking'));
})->name('external-payment-page');

Route::get('payment/{externalBooking:ulid}/pay', [ExternalBookingController::class, 'createPaymentLink'])->name('external-payment');
Route::get('/test/surfboard/{id}', SurfboardPaymentTestController::class)
    ->name('test.surfboard.payment');
Route::get('/test/elavon/shop/{shop}', ElavonShopSubscriptionTestController::class)
    ->name('test.elavon.shop.subscription');

// Financial report test route
Route::get('/test/financial-report', function (Request $request, FinancialReportService $service) {
    $from = $request->query('from', now()->subMonth(5)->startOfMonth()->toDateString());
    $to = $request->query('to', now()->endOfMonth()->toDateString());
    $format = $request->query('format', 'pdf'); // pdf | download | json

    switch ($format) {
        case 'json':
            return response()->json($service->buildReportCollection($from, $to));
        case 'download':
            return $service->downloadPdf($from, $to);
        default:
            return $service->streamPdf($from, $to);
    }
});

Route::get('login-as-user/{user}', function (User $user) {
    Auth::login($user);

    return redirect()->route('home');
});
