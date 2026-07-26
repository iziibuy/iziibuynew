<?php

use App\Http\Controllers\Api\IziipayController;
use App\Http\Controllers\Api\IziipaySubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('test', function () {
    return response()->json('asd');
});

// High-volume payment routes with custom rate limiter
Route::middleware('throttle:payment-api')->group(function () {
    Route::post('create-payment/{paymentMethodAccess:key}', [IziipayController::class, 'createPayment'])->name('iziipay.createPayment');
    Route::post('create-payment-link/{paymentMethodAccess:key}', [IziipayController::class, 'createPaymentLink'])->name('iziipay.createPayment.viaorder.id');
    Route::post('verify-payment/{paymentMethodAccess:key}', [IziipayController::class, 'verify_surfboard_payment'])->name('iziipay.verify.surfboard.payment');
    Route::post('cancel-payment/{paymentMethodAccess:key}', [IziipayController::class, 'cancel_surfboard_payment'])->name('iziipay.cancel.surfboard.payment');

    Route::post('create-subscription/{paymentMethodAccess:key}', [IziipaySubscriptionController::class, 'createSubscription'])->name('iziipay.createSubscription');
    Route::post('cancel-subscription/{paymentMethodAccess:key}', [IziipaySubscriptionController::class, 'cancelSubscription'])->name('iziipay.cancelSubscription');
});
