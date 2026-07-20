<?php

namespace App\Http\Controllers\Dashboard\Shop;

use App\Constants\Constants;
use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Models\Language;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Services\Clearhaus;
use App\Services\Elavon\ElavonShopSubscriptionBilling;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Iziibuy;

class DashboardController extends Controller
{
    public function index()
    {
        $orders = [
            'paid' => Auth::user()->shop->orders()->where('payment_status', 1)->where('status', 5)->count(),
            'pending' => Auth::user()->shop->orders()->where('status', 0)->count(),
            'cancel' => Auth::user()->shop->orders()->where('status', 3)->count(),
            'undelivered' => Auth::user()->shop->orders()->where('payment_status', 1)->where('status', 4)->count(),
        ];
        $coupons = Auth::user()->shop->coupons->count();
        $managers = Auth::user()->shop->users->count();
        $monthly_sales = Auth::user()->shop->orders()->where('status', '!=', 0)->where(
            'created_at',
            '>=',
            Carbon::now()->startOfMonth()->subMonth()->toDateString()
        )->sum('total');
        $todays_sales = Auth::user()->shop->orders()->where('status', '!=', 0)->whereDate(
            'created_at',
            '>=',
            Carbon::today()
        )->sum('total');

        return view('dashboard.shop.index', compact('orders', 'coupons', 'managers', 'monthly_sales', 'todays_sales'));
    }

    public function removeMedia(Request $request)
    {

        $data = DB::table($request->table)->where('id', $request->id)->update([
            $request->field => null,
        ]);

        if (Storage::disk(config('voyager.storage.disk'))->exists($request->filename)) {
            Storage::disk(config('voyager.storage.disk'))->delete($request->filename);
        }
        session()->flash('success', 'File removed');
    }

    public function profile()
    {
        $user = auth()->user()->load('shop');

        return view('dashboard.shop.profile.index', compact('user'));
    }

    public function updateProfile(Request $request, Shop $shop)
    {
        if (auth()->user()->role_id == 1) {
            $shop = $shop;
        } else {
            $shop = auth()->user()->shop;
        }
        $shop->update([
            'country' => $request->country,
            'user_name' => $request->user_name,
            'default_currency' => $request->default_currency,
            'currencies' => json_encode($request->currencies),
            'selling_location_mode' => $request->selling_location_mode,
            'locations' => json_encode($request->locations),
            ...(auth()->user()->role_id == 1 && $request->has('payment_method')
                ? ['paymentMethod' => implode(',', $request->payment_method ?? [])]
                : []),
        ]);
        $data = $request->meta ?? [];

        $data['footerPaymentMethod'] = $request->meta['footerPaymentMethod'] ?? [];
        Iziibuy::resetShop($shop);
        $shop->createMetas($data);

        return back()->with('success', 'profile updated succesfully');

        return back()->with('success', 'profile updated succesfully');
    }

    public function translations()
    {
        return view('dashboard.shop.store.translations');
    }

    public function shop_translations()
    {
        $languages = Language::where('shopCanEdit', 1)->get();

        return view('dashboard.shop.store.shop_translations', compact('languages'));
    }

    public function shop_translations_update(Request $request)
    {
        $shop = auth()->user()->getShop();
        $shop->mylanguages()->sync($request->languages);

        return redirect()->back()->with('success', 'Language updated');
    }

    public function update_languages(Request $request)
    {
        $request->validate(['default' => ['required', Rule::in(Constants::LANGUAGES['list'])]]);

        auth()->user()->shop()->update([
            'default_language' => $request->default,
        ]);

        return redirect()->back()->with('success', 'Language updated');
    }

    public function update_terms(Request $request)
    {

        $shop = auth()->user()->shop;
        $shop->terms = $request->terms[$shop->default_language ? $shop->default_language : config('voyager.multilingual.default')];

        $shop->save();
        $request['terms_i18n'] = json_encode($request->terms);

        $translations = $shop->prepareTranslations($request);
        $shop->saveTranslations($translations);

        return redirect()->back()->with('success', 'Language updated');
    }

    public function reportIndex()
    {
        $shop = Auth()->user()->shop;

        $query = Order::where('payment_status', 1)->where('shop_id', $shop->id)->where('status', '!=', 0);
        if (request()->filled('filter')) {

            $query = $query->where('referral_code', request()->filter);
        }
        if (request()->filled(['from', 'to'])) {
            $query = $query->whereBetween('created_at', [request()->from, request()->to]);
        }

        $sells = $query->get()->groupBy(function ($data) {
            return $data->created_at->format('Y-m-d');
        })->map(function ($row) {
            return $row->sum('total');
        });

        return view('dashboard.shop.report', compact('sells', 'shop'));
    }

    public function indexCharges()
    {

        $charges = auth()->user()->shop->charges()->latest()->paginate(25);

        return view('dashboard.shop.charges', compact('charges'));
    }

    public function chargesInvoice(Charge $charge)
    {

        try {
            $datas = (new Clearhaus)->get_transaction($charge->order_id);
        } catch (Exception) {
            $datas = [];
        }

        if (count($datas)) {

            if (request()->mode) {
                $transiction = $datas[1];
            } else {
                $transiction = $datas[0];
            }
        } else {
            $transiction = [];
        }

        return view('dashboard.shop.charge_invoice', compact('datas', 'transiction', 'charge'));
    }

    public function downloadInvoice(Charge $charge)
    {

        $reg_tax = $charge->shop?->registrationTax() ?? setting('payment.registration_tax');

        $amount = $charge->amount;
        $base_price = ($amount * 100) / (100 + $reg_tax);
        $tax = $amount - $base_price;
        $pdf = Pdf::loadView('dashboard.shop.pdf.invoice', ['charge' => $charge, 'tax' => $tax, 'base_price' => $base_price]);

        $fileName = 'invoice-'.uniqid().'.pdf';

        try {
            return $pdf->download($fileName);
        } catch (Exception $e) {
            // Handle the exception here
            return redirect()->back()->withErrors($e->getMessage());
        }
    }

    public function cancelSubscription()
    {
        $shop = auth()->user()->shop;
        ElavonShopSubscriptionBilling::cancel($shop);

        $shop->update([
            'subscription_id' => null,
            'shopperId' => null,
            'elavon_plan_id' => null,
            'elavon_subscription_id' => null,
        ]);

        return redirect()->back()->with('success', 'Subscription cancelled for this account');
    }

    public function updateConfig(Request $request)
    {

        $request->validate([
            'toggle' => 'required',
        ]);

        auth()->user()->shop()->update([
            'store_as_pickup_point' => $request->toggle,
        ]);

        return redirect()->back()->with(['success', 'Successfull']);
    }

    public function ptReport()
    {
        if (! auth()->user()->getShop()->defaultOption) {
            return redirect()->back()->withErrors('Shop do not have default option');
        }
        $allManagers = auth()->user()->shop->users()->personalTrainer()->get();
        $managers = auth()->user()->shop->users()->when(request()->filled('manager'), function ($query) {
            $query->where('id', request()->manager);
        })->personalTrainer()->get();
        $orders = Order::where('payment_status', 1)->whereHas('metas', function ($query) use ($managers) {
            $query->where('column_name', 'trainer')->whereIn('column_value', $managers->pluck('id')->toArray());
        })->where('payment_status', 1)->when(request()->filled('from'), function ($query) {
            $query->whereBetween('created_at', [request()->filled('from'), request()->filled('to')]);
        })->get();

        return view('dashboard.shop.report.pt_report', compact('allManagers', 'orders', 'managers'));
    }

    public function ptReportPdf()
    {
        if (! auth()->user()->getShop()->defaultOption) {
            return redirect()->back()->withErrors('Shop do not have default option');
        }

        $managers = auth()->user()->shop->users()->when(request()->filled('manager'), function ($query) {
            $query->where('id', request()->manager);
        })->personalTrainer()->get();
        $orders = Order::where('payment_status', 1)->whereHas('metas', function ($query) use ($managers) {
            $query->where('column_name', 'trainer')->whereIn('column_value', $managers->pluck('id')->toArray());
        })->where('payment_status', 1)->when(request()->filled('from'), function ($query) {
            $query->whereBetween('created_at', [request()->filled('from'), request()->filled('to')]);
        })->get();
        $data = ['orders' => $orders, 'manager' => $managers];
        $pdf = Pdf::loadView('dashboard.shop.report.pdf.pt_report', $data);

        return $pdf->download('pt_report.pdf');
    }

    public function clients()
    {
        $managers = auth()->user()->shop->users()->personalTrainer()->get()->pluck('id')->toArray();
        $users = User::whereHas('metas', function ($query) use ($managers) {
            $query->where('column_name', 'personal_trainner')->whereIn('column_value', $managers);
        })->latest()->paginate(10);
        $shop = auth()->user()->getShop();

        return view('dashboard.shop.report.clients', compact('users', 'shop'));
    }

    public function myCalender(Request $request)
    {
        $bookings = Booking::where('shop_id', auth()->user()->getShop()->id);

        if ($request->filled('status')) {
            $bookings = $bookings->where('status', $request->status);
        }

        $bookings = $bookings->latest()->paginate(20);

        // return view('auth.manager.booking.index', compact('bookings'));
        return view('dashboard.shop.booking.calender', compact('bookings'));
    }
}
