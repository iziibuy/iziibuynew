<?php

use App\Exports\LanguageExport;
use App\Filament\Pages\RetailerReportPage;
use App\Filament\Pages\RetailerWithdrawalsPage;
use App\Filament\Resources\RetailerMetas\RetailerMetaResource;
use App\Filament\Resources\Shops\ShopResource;
use App\Http\Controllers\Admin\ImportsController;
use App\Http\Controllers\Admin\OrderAdminController;
use App\Http\Controllers\Admin\RetailerAdminController;
use App\Http\Controllers\Admin\RetailerTransferController;
use App\Http\Controllers\Admin\ShopAdminToolsController;
use App\Http\Controllers\Dashboard\External\DashboardController;
use App\Http\Controllers\Dashboard\Shop\ProductsController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\Shop\PagesController;
use App\Http\Controllers\TwoPaymentController;
use App\Models\RetailerMeta;
use App\Models\RetailerWithdrawal;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;

/*
| Legacy staff routes previously mixed with Voyager under /admin.
| Filament panel lives at /panel (see AdminPanelProvider).
*/

Route::middleware(['web', 'admin.user'])->prefix('admin')->as('admin.')->group(function () {
    Route::post('store-attribute', [ProductsController::class, 'store_attribue'])->name('store.attribute');
    Route::post('update-attribute', [ProductsController::class, 'update_attribue'])->name('update.attribute');
    Route::get('new-variation/{product}', [ProductsController::class, 'create_variation'])->name('new.variation');
    Route::post('update-variation/{product}', [ProductsController::class, 'update_variation'])->name('update.variation');
    Route::get('delete-meta/{product}', [ProductsController::class, 'delete_variation'])->name('delete.product.meta');
    Route::get('delete-attribute/{attribute}', [ProductsController::class, 'delete_attribue'])->name('delete.product.attribute');
    Route::get('copy-product/{product}', [ProductsController::class, 'CopyProduct'])->name('copy.product');
    Route::get('changelog', [PagesController::class, 'changelog'])->name('changelog');
    Route::get('shop-product-export-by-admin/{shop}', [ExportController::class, 'shop_product_export_by_admin'])->name('shop_product_export_by_admin');
    Route::get('enterprise-onboardings-export', [ExportController::class, 'enterprise_onboarding_export'])->name('enterprise_onboarding_export');
    Route::get('enterprise-onboardings-export/{enterpriseOnboarding}', [ExportController::class, 'enterprise_onboarding_export_single'])->name('enterprise_onboarding_export_single');
    Route::get('export-group-product/{group}', [ExportController::class, 'export_group_product'])->name('export_group_product');
    Route::get('charges/{charge}/invoice/pdf', [DashboardController::class, 'downloadInvoice'])->name('external.download.invoice');
});

Route::middleware(['web', 'admin.user'])->prefix('admin/retailer')->as('admin.retailer.')->group(function () {
    Route::redirect('/add-new-retailer', '/panel/retailer-metas/create', 302)->name('create-retailer');
    Route::post('/store-retailer', fn () => redirect()->to(RetailerMetaResource::getUrl('create'), 302))->name('store-retailer');
    Route::get('/withdrawals/{user?}', function (?int $user = null) {
        $url = RetailerWithdrawalsPage::getUrl();
        if ($user) {
            $url .= '?user='.$user;
        }

        return redirect()->to($url, 302);
    })->name('retailer-withdrawals');
    Route::post('/withdrawals/balance/{user}', [RetailerAdminController::class, 'withdrawalsBalance'])->name('retailer-withdrawals-balance');
    Route::post('/delete-retailer/{retailer}', function (RetailerMeta $retailer) {
        $owner = $retailer->user;
        $retailer->delete();
        $owner?->delete();

        return redirect()->to(RetailerMetaResource::getUrl(), 302);
    })->name('delete-retailer');
    Route::post('/transfer-clients/{retailerMeta}', RetailerTransferController::class)->name('transferClients');
    Route::get('/report/{user}', function (User $user) {
        return redirect()->to(RetailerReportPage::getUrl(['user' => $user]), 302);
    })->name('report');
    Route::get('/withdraw/approve/{data}', function (RetailerWithdrawal $data) {
        $data->status = 1;
        $data->save();

        return redirect()->to(RetailerWithdrawalsPage::getUrl().'?user='.$data->user_id, 302);
    })->name('withdraw.approve');
    Route::get('/withdraw/cancel/{data}', function (RetailerWithdrawal $data) {
        $data->status = 2;
        $data->save();

        return redirect()->to(RetailerWithdrawalsPage::getUrl().'?user='.$data->user_id, 302);
    })->name('withdraw.cancel');
});

Route::middleware(['web', 'admin.user'])->prefix('admin')->as('admin.')->group(function () {
    Route::get('advance-shop-edit/{shop}', function (Shop $shop) {
        return redirect()->to(ShopResource::getUrl('advance-edit', ['record' => $shop]), 302);
    })->name('advance.shop.edit');
    Route::get('send-shop-password/{shop}', [ShopAdminToolsController::class, 'sendShopPassword'])->name('send.shop.password');
    Route::get('orders/{order}/refund', [OrderAdminController::class, 'refundView'])->name('orders.refund');
    Route::post('orders/{order}/refund-store', [OrderAdminController::class, 'refund'])->name('orders.refund.store');
    Route::get('orders/{order}/cancel', [TwoPaymentController::class, 'orderCancel'])->name('orders.cancel');
    Route::get('orders/{order}/fulfilled', [TwoPaymentController::class, 'orderFulfilled'])->name('orders.fulfiled');
    Route::post('import-product', [ImportsController::class, 'import_product'])->name('product.import');
    Route::post('import-languages', [ImportsController::class, 'import_languages'])->name('languages.import');
    Route::post('import-shop', [ImportsController::class, 'import_shops'])->name('shops.import');
    Route::get('export/languages', function () {
        return Excel::download(new LanguageExport, 'languages.xlsx');
    })->name('languages.download');
});
