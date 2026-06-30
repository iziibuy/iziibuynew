<?php

use App\Constants\Constants;
use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\EnterpriseSubscriptionController;
use App\Http\Controllers\Api\MasterApiController;
use App\Http\Controllers\Dashboard\Shop\PaymentController;
use App\Http\Resources\OrdersResource;
use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Models\PaymentMethodAccess;
use App\Models\Product;
use App\Models\Shop;
use App\Payment\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['middleware' => 'checkMaster'], function () {
    Route::get('/areas', [MasterApiController::class, 'areas']);
    Route::post('/areas/create', [MasterApiController::class, 'areasCreate']);
    Route::get('/orders', [MasterApiController::class, 'orders']);
    Route::get('/customers', [MasterApiController::class, 'customers']);
    Route::get('/orders/{order}', [MasterApiController::class, 'order']);
    Route::get('/products', [MasterApiController::class, 'products']);
    Route::get('/products/{product}', [MasterApiController::class, 'product']);
    Route::post('/product/create/{product?}', [MasterApiController::class, 'createOrUpdate']);
});

Route::group(['prefix' => '{shop:user_name}', 'middleware' => 'checkShop'], function () {
    Route::get('/orders', [ApiController::class, 'orders']);
    Route::get('/customers', [ApiController::class, 'customers']);
    Route::get('/orders/{order}', [ApiController::class, 'order']);
    Route::get('/products', [ApiController::class, 'products']);
    Route::get('/products/{product}', [ApiController::class, 'product']);
    Route::post('/product/create/{product?}', [ApiController::class, 'createOrUpdate']);
});

Route::get('/ean-to-id', function (Request $request) {

    $product = Product::where('ean', $request->ean)->where('shop_id', $request->shop_id)->first();
    if ($product) {
        return response($product->id);
    }

    return response('Product not found', 404);
});
Route::post('quickpay-webhook', [PaymentController::class, 'quickpayWebhook'])->name('quickpay.webhook');
Route::post('quickpay-plugin-webhook', [PaymentController::class, 'quickpayPluginWebhook'])->name('quickpay.plugin.webhook');

Route::get('/payment-method-access/{paymentMethodAccess:key}', function (PaymentMethodAccess $paymentMethodAccess) {
    return [
        'name' => $paymentMethodAccess->company_name,
        'domain' => substr($paymentMethodAccess->company_domain, 8),
        'status' => $paymentMethodAccess->status ? true : false,
    ];
});

Route::get('vCard/price', function (Request $request) {
    $request->validate(['person_count' => 'required|integer']);

    $shop = Shop::find(Constants::vCardShop);
    $product = $shop->products()->first();

    return response()->json(
        [
            'price' => $request->person_count * $product->price,
        ]
    );
});

Route::post('vCard/order', function (Request $request) {

    $shop = Shop::find(Constants::vCardShop);
    $product = $shop->products()->first();

    $request->validate([
        'first_name' => 'required|string',
        'last_name' => 'required|string',
        'email' => 'required|string|email',
        'address' => 'required|string',
        'city' => 'required||string',
        'post_code' => 'required|string',
        'state' => 'required|string',
        'phone' => 'required|string',
        'persons.*.companyName' => 'required|string',
        'persons.*.companyLogo' => 'required|url',
        'persons.*.fullName' => 'required|string',
        'persons.*.email' => 'required|email',
        'persons.*.phone' => 'required|string',
        'persons.*.qr' => 'required|url',
    ]);
    try {
        $total = $product->price * count($request->persons);
        $data = [
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'address' => $request->address,
            'city' => $request->city,
            'post_code' => $request->post_code,
            'state' => $request->state,
            'phone' => $request->phone,
            'is_vcard' => 1,
            'details' => json_encode($request->persons),
            'external' => true,
        ];

        $order = Order::create([
            'shop_id' => $shop->id,
            'payment_method' => 'quickpay',
            'subtotal' => $total,
            'total' => $total,
            'type' => 0,
            'currency' => 'NOK',
            'payment_status' => 0,
        ]);
        $order->createMetas($data);

        $order->products()->attach($product->id, [
            'quantity' => count($request->persons),
            'price' => $product->price,
        ]);

        $product->decrement('quantity', count($request->persons));

        $order->save();

        $payment = (new Payment($order))->getUrl();

        if ($payment['status'] == false) {
            throw new Exception($payment['data']['message']);
        }
        $order->payment_id = $payment['data']['payment_id'];
        $order->payment_url = $payment['data']['url'];

        $order->save();

        $message = 'Her er dine detaljer for din ordre plassert den '.$order->created_at->format('M d, Y').' hos '.$order->shop->name.' Vennligst betal nå for å bekrefte din ordre';
        Mail::to($order->email)->send(new OrderPlaced($order, false, $message));
        $message = "A new order has been placed . Download details from here: <a href='".route('order.managers-csv', $order->id)."'>Download CSV</a>";
        Mail::to($order->shop->user->email)->send(new OrderPlaced($order, $message));

        return OrdersResource::make($order);
    } catch (Exception $e) {
        return redirect()->back()->withErrors($e->getMessage());
    }
});

Route::prefix('enterprise')->controller(EnterpriseSubscriptionController::class)->group(function () {
    Route::post('/create', 'create');
    Route::get('/{uid}/end', 'end');
    Route::get('/{uid}/start', 'start');
    Route::get('/{uid}', 'show');
    Route::get('/{uid}/subscription/charges', 'subscriptionCharges');
    Route::get('/{uid}/subscription/charges/{charge}', 'subscriptionCharge');
    Route::get('/{uid}/subscription', 'subscription');
    Route::post('/{uid}/charge', 'charge');
    Route::get('/{uid}/{id}/getcharge', 'getCharge');
});
