<?php

namespace App\Http\Controllers\Dashboard\Shop;

use App\Constants\Constants;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\ManagerCreateRequest;
use App\Http\Requests\ManagerScheduleRequest;
use App\Mail\ManagerInvoice;
use App\Mail\NotificationEmail;
use App\Mail\OrderPlaced;
use App\Models\Level;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Payment\Payment;
use App\Payment\Subscribe;
use App\Services\Elavon\ElavonShopSubscriptionBilling;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ManagersController extends Controller
{
    public function index()
    {

        if (request('manager_id')) {
            $manager_edit = User::where('id', request('manager_id'))->first();

            if ($manager_edit->getShop()->id != auth()->user()->shop->id) {
                abort(403);
            }
            if (! $manager_edit) {
                return redirect(route('shop.managers'));
            }
        } else {
            $manager_edit = false;
        }
        $levels = Level::where('shop_id', auth()->user()->shop->id)->get();
        $managers = User::where('shop_id', auth()->user()->shop->id)->get();

        return view('dashboard.shop.managers.index', compact('managers', 'manager_edit', 'levels'));
    }

    public function store(ManagerCreateRequest $request)
    {

        $shop = auth()->user()->shop;

        foreach ($request->managers as $key => $manager) {
            $img = '';
            if (isset($manager['photo'])) {
                $img = $manager['photo']->store('avatar');
            }

            $managers[] = [
                'avatar' => $img,
                'name' => $manager['first_name'],
                'last_name' => $manager['last_name'],
                'email' => $manager['email'],
                'phone' => $manager['phone'],
                'tax_id' => $manager['tax'],
                'password' => Hash::make($manager['password']),
                'role_id' => User::ROLES['Manager'],
                'shop_id' => $shop->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $mail_data = [
                'subject' => 'new manager account created',
                'body' => 'Welcome to iziibuy. A new manager account has been created. Please click the link below to login',
                'button_link' => route('login'),
                'button_text' => 'Login',
                'emails' => [],
            ];
            Mail::to($manager['email'])->send(new NotificationEmail($mail_data));
        }
        User::insert($managers);
        ElavonShopSubscriptionBilling::syncRecurringPlan($shop->fresh());

        // $payment = (new Subscribe(setting('payment.api_key')))->subscription(auth()->user()->subscription_id);
        // $amount = auth()->user()->shop->singleUserCost() * count($request->managers);
        // $tax = $amount * (setting('payment.registration_tax') / 100);
        // $taxAddedAmount = $amount + $tax;
        // $payment->charge($taxAddedAmount);
        // Mail::to($shop->user->email)->send(new ManagerInvoice($shop, 200, 20));
        return back()->with('success', 'Manager Created');
    }

    public function schedule(User $user)
    {

        $user->load('schedules');

        return view('dashboard.shop.managers.schedule', compact('user'));
    }

    public function updateSchedule(ManagerScheduleRequest $request, User $user)
    {
        $schedules = $request->validated();

        foreach ($schedules as $schedule) {
            $user->schedules()->updateOrCreate(
                ['day' => $schedule['day']],
                $schedule
            );
        }

        return redirect()
            ->back()
            ->with('success', 'Schedule updated successfully');
    }

    public function delete(User $user)
    {

        if ($user->avatar && Storage::exists($user->avatar)) {
            Storage::delete($user->avatar);
        }
        $shop = auth()->user()->shop;
        $user->delete();
        ElavonShopSubscriptionBilling::syncRecurringPlan($shop->fresh());

        return back()->with('success', 'Manager Deleted');
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string',
            'last_name' => 'required|string',
            'phone' => 'required|string',
            'tax' => 'nullable|string',
            'password' => 'nullable|string',

        ]);

        $user->createMetas($request->meta);
        $data = [
            'name' => $request->name,
            'last_name' => $request->last_name,
            'phone' => $request->phone,
            'tax_id' => $request->tax,
            'service_type' => json_encode($request->service_type),
        ];

        if ($request->has('avatar')) {
            $data['avatar'] = $request->avatar->store('avatar');
        }
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }
        $result = $user->update($data);

        if ($request->has('permissions')) {
            $user->accesses()->sync($request->permissions);
        }

        return redirect(route('shop.managers'))->with('success', 'Manager Updated');
    }

    public function myQr(User $user)
    {
        $products = Product::where('shop_id', auth()->user()->shop->id)->paginate(30);

        return view('dashboard.shop.managers.qr', compact('products', 'user'));
    }

    public function orderVcard(Request $request)
    {

        $request->validate([
            'product' => 'required',
            'managers' => 'required',
        ]);
        try {

            $product = Product::find($request->product);

            $data = [
                'user_id' => auth()->id(),
                'shop_id' => Constants::vCardShop,
                'first_name' => auth()->user()->name,
                'last_name' => auth()->user()->last_name,
                'email' => auth()->user()->email,
                'address' => auth()->user()->address,
                'city' => auth()->user()->city,
                'post_code' => auth()->user()->post_code,
                'state' => auth()->user()->state,
                'phone' => auth()->user()->phone,
                'subtotal' => $request->total,
                'total' => $request->total,
                'is_vcard' => 1,
                'details' => json_encode($request->managers),

            ];

            $order = Order::create([
                'user_id' => auth()->id(),
                'shop_id' => Constants::vCardShop,
                'referral_code' => session('manager_id'),
                'subtotal' => $request->total,
                'payment_method' => 'quickpay',
                'total' => $request->total,
                'store_id' => request()->store,
                'type' => 0,
                'currency' => 'NOK',
                'payment_status' => 0,
            ]);

            $order->createMetas([

                'first_name' => auth()->user()->name,
                'last_name' => auth()->user()->last_name,
                'email' => auth()->user()->email,
                'address' => auth()->user()->address,
                'city' => auth()->user()->city,
                'post_code' => auth()->user()->post_code,
                'state' => auth()->user()->state,
                'phone' => auth()->user()->phone,
                'is_vcard' => 1,
                'details' => json_encode($request->managers),
            ]);

            $order->products()->attach($product->id, [
                'quantity' => $request->qty,
                'price' => $product->price,
            ]);

            $product->decrement('quantity', $request->qty);

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

            return redirect()
                ->route(
                    'payment',
                    [
                        'order' => $order,
                        'user_name' => $order->shop->user_name,
                    ]
                )
                ->with('success_msg', 'Ordre plassert. Vennligst betal nå for å bekrefte din ordre');
        } catch (Exception $e) {
            return redirect()->back()->withErrors($e->getMessage());
        }
    }
}
