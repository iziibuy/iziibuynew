<?php

use App\Http\Controllers\Dashboard\Shop\BookingController;
use App\Http\Controllers\Dashboard\Shop\BoxesController;
use App\Http\Controllers\Dashboard\Shop\CategoriesController;
use App\Http\Controllers\Dashboard\Shop\ClientController;
use App\Http\Controllers\Dashboard\Shop\ContractSignController;
use App\Http\Controllers\Dashboard\Shop\CouponController;
use App\Http\Controllers\Dashboard\Shop\DashboardController;
use App\Http\Controllers\Dashboard\Shop\DinteroOnboardingController;
use App\Http\Controllers\Dashboard\Shop\LevelController;
use App\Http\Controllers\Dashboard\Shop\ManagerScheduleController;
use App\Http\Controllers\Dashboard\Shop\ManagersController;
use App\Http\Controllers\Dashboard\Shop\OrdersController;
use App\Http\Controllers\Dashboard\Shop\PackageController;
use App\Http\Controllers\Dashboard\Shop\PackageoptionController;
use App\Http\Controllers\Dashboard\Shop\PaymentController;
use App\Http\Controllers\Dashboard\Shop\PriceGroupController;
use App\Http\Controllers\Dashboard\Shop\ProductsController;
use App\Http\Controllers\Dashboard\Shop\RegisterController;
use App\Http\Controllers\Dashboard\Shop\ReportController;
use App\Http\Controllers\Dashboard\Shop\ResourcesController;
use App\Http\Controllers\Dashboard\Shop\ServicesController;
use App\Http\Controllers\Dashboard\Shop\ShippingsController;
use App\Http\Controllers\Dashboard\Shop\SlidersController;
use App\Http\Controllers\Dashboard\Shop\StoreController;
use App\Http\Controllers\QrcodeController;
use App\Http\Controllers\Shop\PersonalTrainerReportController;
use App\Mail\BookingPlaced;
use App\Mail\OrderConfirmed;
use App\Mail\OrderPlaced;
use App\Mail\TicketPlaced;
use App\Models\Booking;
use App\Models\Order;
use App\Models\Shop;
use App\Models\Ticket;
use App\Payment\Surfboard\SurfboardMarchant;
use App\Payment\Surfboard\SurfboardTerminal;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::post('remove-media', [DashboardController::class, 'removeMedia'])->name('remove.media');

Route::get('/complete-registration', [RegisterController::class, 'completeProfile'])->name('completeProfile');

Route::put('/complete-registration', [RegisterController::class, 'completeProfileUpdate'])->name('profile.completeProfileUpdate');
Route::get('subscription', [RegisterController::class, 'subscriptionIndex'])->name('subscription.payment');
Route::get('delete-account', [RegisterController::class, 'deleteAccount'])->name('delete.account');
Route::any('enroll-subscription', [RegisterController::class, 'enrollSubscription'])->name('enroll.subscription');
Route::get('subscription/elavon-return', [RegisterController::class, 'elavonSubscriptionReturn'])->name('subscription.elavon.return');
Route::get('subscription/elavon-cancel', [RegisterController::class, 'elavonSubscriptionCancel'])->name('subscription.elavon.cancel');
Route::get('confirm-subscription/{subscription_id}', [RegisterController::class, 'confirmSubscription'])->name('confirm.subscription');

Route::get('/charges', [DashboardController::class, 'indexCharges'])->name('charges.index');
Route::get('/charges/{charge}/invoice', [DashboardController::class, 'chargesInvoice'])->name('charge.invoice');
Route::get('/charges/{charge}/invoice/pdf', [DashboardController::class, 'downloadInvoice'])->name('download.invoice');

Route::middleware('Paid')->group(function () {

    // Products Related Routes
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('qrcodes', QrcodeController::class);
    Route::resource('products', ProductsController::class)->except('show')->middleware('permission:product,browse');
    Route::group(['prefix' => 'products', 'controller' => ProductsController::class, 'as' => 'products.'], function () {
        Route::post('order', 'order')->name('order');
        Route::post('change-status', 'change_status')->name('change_status');
        Route::get('pin/{product}', 'pin')->name('pin');

        Route::group(['prefix' => 'attribute/', 'as' => 'attribute.'], function () {
            Route::post('store', 'store_attribue')->name('store');
            Route::post('update', 'update_attribue')->name('update');
            Route::get('{attribute}/delete', 'delete_attribue')->name('destroy');
        });

        Route::group(['prefix' => 'variation/{product}/', 'as' => 'variation.'], function () {
            Route::get('create', 'create_variation')->name('create');
            Route::get('affiliate', 'affiliate_variation')->name('affiliate');
            Route::post('update', 'update_variation')->name('update');
            Route::delete('delete', 'delete_variation')->name('destroy');
        });
    });

    Route::resource('categories', CategoriesController::class)->except('show')->middleware('permission:category,browse');
    Route::get('categories/order', [CategoriesController::class, 'order'])->name('categories.order');

    Route::resource('shippings', ShippingsController::class)->middleware('permission:shipping,browse');
    Route::post('update/config', [DashboardController::class, 'updateConfig'])->name('update.config');
    // store related route
    Route::group(['prefix' => 'settings',  'as' => 'store.'], function () {
        Route::get('profile', [DashboardController::class, 'profile'])->name('profile');
        Route::post('profile', [DashboardController::class, 'updateProfile'])->name('profile.update');
    });
    Route::resource('sliders', SlidersController::class);

    Route::resource('levels', LevelController::class);
    Route::resource('packageoptions', PackageoptionController::class);

    Route::resource('coupon', CouponController::class);
    Route::resource('storage', StoreController::class)->middleware('permission:store,browse');
    Route::post('/storage/{store}/add-product', [StoreController::class, 'addProduct'])->name('add-product');
    // slider related route
    Route::resource('sliders', SlidersController::class)->except('create', 'show')->middleware('permission:slider,browse');
    // Shop langaue related route
    Route::get('translations', [DashboardController::class, 'translations'])->name('translations');
    Route::get('shop-translations', [DashboardController::class, 'shop_translations'])->name('shop_translations');
    Route::post('shop-translations', [DashboardController::class, 'shop_translations_update'])->name('shop_translations_update');
    Route::post('languages/update', [DashboardController::class, 'update_languages'])->name('languages.update');
    Route::post('terms/update', [DashboardController::class, 'update_terms'])->name('terms.update');

    // End of shop langaue related route

    Route::get('/managers', [ManagersController::class, 'index'])->name('managers')->middleware('permission:manager,browse');
    Route::get('/managers/{user}/schedule', [ManagersController::class, 'schedule'])->name('managers.schedule');
    Route::post('/managers/{user}/schedule/update', [ManagersController::class, 'updateSchedule'])->name('managers.schedule.update');
    Route::post('/managers/store', [ManagersController::class, 'store'])->name('managers.store');
    Route::delete('/managers/{user}/destroy', [ManagersController::class, 'delete'])->name('managers.delete');
    Route::put('/managers/{user}/update', [ManagersController::class, 'update'])->name('managers.update');
    Route::get('my-qr/{user}', [ManagersController::class, 'myQr'])->name('myQr');
    Route::resource('sliders', SlidersController::class)->middleware('permission:slider,browse');
    Route::post('/update/config', [DashboardController::class, 'updateConfig'])->name('update.config');
    Route::post('order/vcard', [ManagersController::class, 'orderVcard'])->name('order.vCard');
    // finance
    Route::resource('coupon', CouponController::class)->middleware('permission:slider,browse')->middleware('permission:coupon,browse');

    Route::get('/cancel-subscription', [DashboardController::class, 'cancelSubscription'])->name('cancel-subscription');

    Route::get('complete-signup', [ContractSignController::class, 'selectPaymentMethods'])->name('complete.signup');
    Route::post('complete-signup', [ContractSignController::class, 'assignGatewayByPaymentMethods'])->name('post.complete.signup');

    Route::get('/setup/payment/surfboard', [ContractSignController::class, 'setup_surfboard_payment'])->name('setup_surfboard_payment');
    Route::get('/setup/payment/dintero', [DinteroOnboardingController::class, 'setup'])->name('setup_dintero_payment');
    Route::get('/setup/payment/elavon', [ContractSignController::class, 'setup_elavon_payment'])->name('setup_elavon_payment');
    // Route::get('/setup/payment/elavon', [PaymentController::class, 'setup_surfboard_payment'])->name('setup_surfboard_payment');
    Route::post('/setup/payment/elavon', [ContractSignController::class, 'store_setup_elavon_payment'])->name('store_setup_elavon_payment');
    Route::get('/verify/payment/elavon', [PaymentController::class, 'verifyElavonPayment'])->name('verify_elavon_payment_information');
    Route::get('/setup/payment/two', [PaymentController::class, 'setup_payment_two'])->name('setup_payment_two');
    Route::post('/setup/payment/two', [PaymentController::class, 'store_setup_payment_two'])->name('store_setup_payment_two');

    Route::get('surfboard-application-status-check', function () {
        $shop = auth()->user()->getShop();

        try {
            if ($shop->paymentMethod != 'surfboard' || $shop->contract_status != 0) {
                throw new Exception('You do not have permission');
            }
            $message = 'Contract signed';
            // Check merchant status
            $application = (new SurfboardMarchant($shop))->statusCheck()->json();
            $applicationStatus = $application['data']['applicationStatus'] ?? null;

            // Skip if application status is not successful
            if ($application['status'] !== 'SUCCESS' || $applicationStatus !== 'MERCHANT_CREATED') {
                $shop->createMetas([
                    'surfboard_applicationStatus' => $applicationStatus,
                ]);
                throw new Exception('Your application status is '.$applicationStatus);
            }

            // If merchant and store IDs exist
            if ($shop->surfboard_merchantId && $shop->surfboard_storeId) {
                // Skip if terminal ID already exists

                if ($shop->surfboard_terminalId) {
                    throw new Exception('Terminal already created');
                }
                // Create terminal
                $terminal = (new SurfboardTerminal($shop))->createTerminal()->json();
                $terminalId = $terminal['data']['terminalId'] ?? null;

                if ($terminalId) {
                    $shop->update([
                        'contract_status' => 1,
                    ]);
                    $shop->createMeta('surfboard_terminalId', $terminalId);
                } else {

                    $message = "Failed to create terminal for Shop ID {$shop->id}";
                    Log::warning("Failed to create terminal for Shop ID {$shop->id}", [
                        'response' => $terminal,
                    ]);
                }
            } else {
                // Save merchant and store details
                $shop->createMetas([
                    'surfboard_applicationStatus' => $applicationStatus,
                    'surfboard_storeId' => $application['data']['storeId'] ?? null,
                    'surfboard_merchantId' => $application['data']['merchantId'] ?? null,
                ]);

                if ($shop->surfboard_terminalId) {
                    throw new Exception('Terminal already created');
                }
                // Create terminal
                $terminal = (new SurfboardTerminal($shop))->createTerminal()->json();
                $terminalId = $terminal['data']['terminalId'] ?? null;

                if ($terminalId) {
                    $shop->update([
                        'contract_status' => 1,
                    ]);
                    $shop->createMeta('surfboard_terminalId', $terminalId);
                } else {
                    $message = "Failed to create terminal for Shop ID {$shop->id}";
                    Log::warning("Failed to create terminal for Shop ID {$shop->id}", [
                        'response' => $terminal,
                    ]);
                }
            }

            return redirect()->back()->with('scuess', $message);
        } catch (Exception $e) {
            // Log exceptions to debug errors
            Log::error("Error processing Shop ID {$shop->id}", [
                'error' => $e->getMessage(),
                'stack' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->withErrors($e->getMessage());
        }
    })->name('surfboardStatusCheck');

    Route::get('/service/subscription', [RegisterController::class, 'serviceSubscription'])->name('service.subscription');
    Route::get('/service/subscribe', [RegisterController::class, 'serviceSubscribe'])->name('service.subscribe');
    Route::get('/orders/{order}/fulfilled', [PaymentController::class, 'orderFulfilled'])->name('orders.fulfiled');
    Route::get('/orders/{order}/cancel', [PaymentController::class, 'orderCancel'])->name('orders.cancel');
    Route::get('/orders/{order}/refund', [PaymentController::class, 'refundView'])->name('orders.refund');
    Route::post('/orders/{order}/refund-store', [PaymentController::class, 'refund'])->name('orders.refund.store');
    Route::post('/order/{order}/capture', [PaymentController::class, 'captureOrder'])->name('captureOrder');

    // Subscription Box related routes
    // Route::resource('boxes', BoxesController::class);
    // boxes Controller start
    Route::get('box', [BoxesController::class, 'index'])->name('boxes.index')->middleware('permission:subscription_product,browse');
    Route::get('box/create', [BoxesController::class, 'create'])->name('boxes.create')->middleware('permission:subscription_product,create');
    Route::post('box/store', [BoxesController::class, 'store'])->name('boxes.store');
    Route::get('box/edit/{box}', [BoxesController::class, 'edit'])->name('boxes.edit')->middleware('permission:subscription_product,edit');
    Route::get('box/show/{box}', [BoxesController::class, 'show'])->name('boxes.show');
    Route::delete('box/destroy/{box}', [BoxesController::class, 'destroy'])->name('boxes.destroy')->middleware('permission:subscription_product,edit');
    Route::put('box/update/{box}', [BoxesController::class, 'update'])->name('boxes.update');
    // Boxes controller end
    Route::get('/boxes/subscription/{membership}/invoice', [BoxesController::class, 'subscriptionInvoice'])->name('subscriptionInvoice');
    // end of Subscription Box related routes

    Route::group(['as' => 'booking.', 'middleware' => ['canProvideService']], function () {

        Route::resource('services', ServicesController::class)->except('show');
        Route::resource('resources', ResourcesController::class);
        Route::resource('price-groups', PriceGroupController::class);
        Route::get('assign-group/create/{user}', [BookingController::class, 'assignGroupCreate'])->name('assignGroup.create');
        Route::post('manager/schedule-update/{user}', [BookingController::class, 'updateManagerSchedule'])->name('manager.updateManagerSchedule');
        Route::post('manager/{user}/price-group', [BookingController::class, 'updateStorePriceManager'])->name('manager.updatestoreprice');
        Route::get('booking/callender', [BookingController::class, 'myCalender'])->name('callender');
        Route::get('bookings', [BookingController::class, 'index'])->name('index');
        Route::get('clients', [ClientController::class, 'index'])->name('client.index')->middleware('permission:personal_trainee,browse');
        Route::middleware('personalClient')->group(
            function () {
                Route::get('/clients/addSessions', [ClientController::class, 'addSessions'])->name('client.addSessions')->middleware('permission:personal_trainee,edit');
            }
        );
        Route::get('booking/{booking}/set-status/completed', function (Booking $booking) {
            try {
                if ($booking->shop_id != auth()->user()->shop->id) {
                    throw new Exception('You do not have access');
                }
                if ($booking->status != 'Pending') {
                    throw new Exception("Status can't be changed");
                }
                $booking->update(['status' => $booking::STATUS_COMPLETED]);

                return redirect()->back()->with('success', 'Status updated to completed');
            } catch (Exception $e) {
                return redirect()->back()->with('error', $e->getMessage());
            } catch (Error $e) {
                return redirect()->back()->with('error', $e->getMessage());
            }
        })->name('status.completed');
    });
    Route::get('manager-schedule', [ManagerScheduleController::class, 'index'])->name('manage-schedule.index');
    Route::put('manager-schedule/{booking}', [ManagerScheduleController::class, 'update']);

    Route::get('disablekyc', function () {
        auth()->user()->shop->createMeta('needKYC', false);

        return redirect()->back();
    })->name('disablekyc');

    Route::resource('packages', PackageController::class);

    Route::get('email/{order}', function (Order $order) {
        return new OrderConfirmed($order, 'This message is for test purpose');
    });
    Route::get('placed/{order}', function (Order $order) {
        return new OrderPlaced($order, 'This message is for test purpose');
    });
    Route::get('booking/{booking}/placed', function (Booking $booking) {
        return new BookingPlaced($booking);
    });
    Route::get('ticket-email/{ticket}', function (Ticket $ticket) {
        return new TicketPlaced($ticket, 'This message is for test purpose');
    });

    Route::get('report', [DashboardController::class, 'reportIndex'])->name('report.index');
    Route::get('pt-report', [ReportController::class, 'ptReport'])->name('pt.report');
    Route::get('pt-report/pdf', [PersonalTrainerReportController::class, 'index'])->name('pt.report.pdf');
    Route::get('orders', [OrdersController::class, 'index'])->name('order.index');
    Route::get('/invoice/{order}', [OrdersController::class, 'invoice'])->name('invoice');
    Route::get('/invoice/{order}/pdf', [OrdersController::class, 'download'])->name('invoice.download');
    Route::post('imported-products', [ProductsController::class, 'importProduct'])->name('product.import');
});
