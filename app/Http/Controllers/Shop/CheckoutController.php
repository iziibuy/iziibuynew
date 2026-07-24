<?php

namespace App\Http\Controllers\Shop;

use App\Exceptions\CartIsEmpty;
use App\Exceptions\ShippingNotAvilableException;
use App\Exceptions\UpdateProfileException;
use App\Facades\Cart;
use App\Http\Controllers\Controller;
use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipping;
use App\Models\Shop;
use App\Models\User;
use App\Payment\Payment;
use App\Services\CheckoutService;
use App\Services\NewOrder;
use Error;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class CheckoutController extends Controller
{
    public function checkout(Request $request, $user_name)
    {

        $shop = Shop::where('user_name', $user_name)->first();

        if ($shop->force_register == 'Yes' && ! auth()->check()) {
            return redirect()->route('login');
        }

        $shippings = Shipping::where('shop_id', $shop->id)->first();
        switch (request()->method) {
            case 'two':
                return view('shop.checkout.two', compact('shop'));
            default:
                return view('shop.checkout.quickpay', compact('shop'));
        }
    }

    public function orderCancel($username, Order $order)
    {
        if ($order->status == 0 && $order->user_id == null) {
            $order->delete();
        }

        return redirect()->route('products', $username);
    }

    public function checkoutStore($user_name, Request $request)
    {

        $shop = Shop::where('user_name', $user_name)->first();

        $shipping = Shipping::find($request->shipping);

        session()->put('shipping', $request->shipping);
        // if ($shop->force_register == 'Yes' && !auth()->check()) return redirect()->route('login');

        if ($shop->force_register == 'Yes' && ! auth()->check()) {
            if (isset($request->user['register'])) {

                $request->validate([
                    'user.register.name' => ['required', 'string', 'max:255'],
                    'user.register.last_name' => ['required', 'string', 'max:255'],
                    'user.register.phone' => ['required', 'string', 'max:255'],
                    'user.register.email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                    'user.register.password' => ['required', 'string', 'min:8'],

                    'user.register.meta.country' => 'required|string',
                    'user.register.meta.state' => 'required|string',
                    'user.register.meta.post_code' => 'required|string',
                    'user.register.meta.address' => 'required|string',
                ]);

                $user = User::create([
                    'name' => $request->user['register']['name'],
                    'last_name' => $request->user['register']['last_name'],
                    'phone' => $request->user['register']['phone'],
                    'email' => $request->user['register']['email'],
                    'password' => Hash::make($request->user['register']['password']),
                ]);
                $user->createMetas($request->user['register']['meta']);
                Auth::login($user);
            }
            if (isset($request->user['login']) && filled($request->input('user.login.email'))) {
                $request->validate([
                    'user.login.email' => ['required', 'email'],
                    'user.login.password' => ['required'],
                ]);
                $user = User::where('email', $request->user['login']['email'])->first();
                if (! $user || ! Hash::check($request->user['login']['password'], $user->password)) {
                    return redirect()->back()->withInput()->withErrors(__('Invalid login credentials.'));
                }
                Auth::login($user, true);
            }
            if (! auth()->check()) {
                return redirect()->back()->withErrors('Something went wrong');
            }
        }

        if ($shop->shipping_force_register == 'Yes' && ! auth()->check() && $shipping) {
            if (isset($request->user['register'])) {

                $request->validate([
                    'user.register.name' => ['required', 'string', 'max:255'],
                    'user.register.last_name' => ['required', 'string', 'max:255'],
                    'user.register.phone' => ['required', 'string', 'max:255'],
                    'user.register.email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                    'user.register.password' => ['required', 'string', 'min:8'],

                    'user.register.meta.country' => 'required|string',
                    'user.register.meta.state' => 'required|string',
                    'user.register.meta.post_code' => 'required|string',
                    'user.register.meta.address' => 'required|string',
                ]);

                $user = User::create([
                    'name' => $request->user['register']['name'],
                    'last_name' => $request->user['register']['last_name'],
                    'phone' => $request->user['register']['phone'],
                    'email' => $request->user['register']['email'],
                    'password' => Hash::make($request->user['register']['password']),
                ]);
                $user->createMetas($request->user['register']['meta']);
                Auth::login($user);
            }
            if (isset($request->user['login']) && filled($request->input('user.login.email'))) {
                $request->validate([
                    'user.login.email' => ['required', 'email'],
                    'user.login.password' => ['required'],
                ]);
                $user = User::where('email', $request->user['login']['email'])->first();
                if (! $user || ! Hash::check($request->user['login']['password'], $user->password)) {
                    return redirect()->back()->withInput()->withErrors(__('Invalid login credentials.'));
                }
                Auth::login($user, true);
            }
            if (! auth()->check()) {
                return redirect()->back()->withErrors('Something went wrong');
            }
        }

        $paymentMethod = false;
        if ($shop->hasPaymentGateway('quickpay') && ! $shop->enabledCheckoutPaymentOptions()) {
            $paymentMethod = 'quickpay';
        } else {
            $resolvedAcquirer = $shop->acquirerForCheckoutOption(
                is_string($request->payment) ? $request->payment : null
            );

            if ($resolvedAcquirer) {
                $paymentMethod = $resolvedAcquirer;
            } else {
                switch ($request->payment) {
                    case 'elavon':
                        $paymentMethod = 'elavon';
                        break;
                    case 'surfboard':
                        $paymentMethod = 'surfboard';
                        break;
                    case 'dintero':
                        $paymentMethod = 'dintero';
                        break;
                    case 'quickpay':
                        $paymentMethod = 'quickpay';
                        break;
                    default:
                        $paymentMethod = $shop->hasPaymentGateway('quickpay') ? 'quickpay' : false;
                        break;
                }
            }
        }

        if ($paymentMethod == false && $shop->fallback_payment_method) {
            $paymentMethod = $shop->fallback_payment_method;
        }

        if ($paymentMethod == false) {
            return redirect()->back()->withInput()->withErrors(__('Please select a payment method.'));
        }

        $data = [
            'name' => $request->first_name ?? auth()->user()->name ?? 'Guest',
            'last_name' => $request->last_name ?? auth()->user()->last_name ?? 'User',
            'email' => $request->email ?? auth()->user()->email ?? setting('site.email'),
            'phone' => $request->phone ?? auth()->user()->phone ?? '',
            // 'payment_method' => $request->payment == 'company' ? 'two' : $shop->paymentMethod,
            'payment_method' => $paymentMethod,
            'currency' => 'NOK',
        ];

        if ($shipping) {
            $data = array_merge($data, [
                'address' => $request->address ?? auth()->user()->address ?? '',
                'city' => $request->city ?? auth()->user()->city ?? '',
                'state' => $request->state ?? auth()->user()->state ?? '',
                'post_code' => $request->post_code ?? auth()->user()->post_code ?? '',
                'country' => $request->country ?? auth()->user()->country ?? '',
            ]);
        }

        if (auth()->check() && $request->payment != 'company' && $shipping) {
            $data = array_merge($data, [
                'address' => $request->address ?? auth()->user()->address,
                'city' => $request->city ?? auth()->user()->city,
                'state' => $request->state ?? auth()->user()->state,
                'post_code' => $request->post_code ?? auth()->user()->post_code,
                'country' => $request->country ?? auth()->user()->country,
            ]);
        }
        if ($request->payment == 'company') {
            $data = array_merge($data, [
                'address' => $request->address ?? auth()->user()->address,
                'city' => $request->city ?? auth()->user()->city,
                'state' => $request->city ?? auth()->user()->state,
                'post_code' => $request->post_code ?? auth()->user()->post_code,
                'country' => $request->country ?? auth()->user()->country,
                'company_name' => $request->company_name ?? auth()->user()->address,
                'company_id' => $request->company_number ?? auth()->user()->city,
                'company_country_prefix' => $request->company_country_prefix ?? auth()->user()->state,
            ]);
        }

        Validator::make($data, [
            'name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'post_code' => 'nullable|string|max:10',
            'country' => 'nullable|string',
            'payment_method' => 'nullable|string',
            'currency' => 'required|string',
        ])->validate();
        try {

            if (Cart::session(request('user_name'))->isEmpty()) {
                throw new CartIsEmpty;
            }

            $order = (new CheckoutService($data, $shipping))->store();

            if ($shop->sell_digital_product) {
                $order->createMeta('is_digital', true);
            } else {
                $order->createMeta('is_digital', false);
            }

            $payment = (new Payment($order))->getUrl();

            if ($payment['status'] == false) {
                throw new Exception($payment['data']['message']);
            }
            $order->payment_id = $payment['data']['payment_id'];
            $order->payment_url = $payment['data']['url'];

            Log::info($payment['data']['payment_id']);

            $order->save();

            if ($order->is_vcard) {
                $message = "A new order has been placed . Download details from here: <a href='".route('order.managers-csv', $order->id)."'>Download CSV</a>";
                Mail::to($order->shop->user->email)->send(new OrderPlaced($order, $message));
            } else {
                Mail::to($order->shop->user->email)->send(new OrderPlaced($order, 'A new order has been placed'));
            }

            Mail::to($order->email)->send(new OrderPlaced($order, 'A new order has been placed'));

            Cart::session(request('user_name'))->clear();

            switch ($request->direct) {
                case true:
                    return redirect($order->payment_url);

                default:
                    return redirect()
                        ->route(
                            'payment',
                            [
                                'order' => $order,
                                'user_name' => request('user_name'),
                            ]
                        )
                        ->with('success_msg', 'Ordre plassert. Vennligst betal nå for å bekrefte din ordre');
                    break;
            }
        } catch (UpdateProfileException $e) {
            return redirect()->route('checkout', [request('user_name'), 'method' => $request->method])->withInput()->withErrors($e->getMessage());
        } catch (ShippingNotAvilableException $e) {
            return redirect()->route('checkout', [request('user_name'), 'method' => $request->method])->withInput()->withErrors($e->getMessage());
        } catch (CartIsEmpty $e) {
            return redirect()->back()->withErrors($e->getMessage());
        } catch (Exception $e) {
            return redirect()->route('checkout', [request('user_name'), 'method' => $request->method])->withInput()->withErrors($e->getMessage());
        }
    }

    public function two_checkout(Request $request)
    {
        return 'checkout';
    }

    public function payment(Request $request, $user_name, Order $order)
    {
        try {
            if (! $order->payment_url) {
                $payment = (new Payment($order))->getUrl();
                if ($payment['status'] == false) {
                    throw new Exception($payment['data']['message']);
                }
                $order->payment_id = $payment['data']['payment_id'];
                $order->payment_url = $payment['data']['url'];
                $order->save();
            }

            return view('shop.checkout.payment', compact('order'));
        } catch (Exception $e) {
            return redirect()->route('shop.home', $order->shop->user_name)->withErrors($e->getMessage());
        } catch (Error $e) {
            return redirect()->route('shop.home', $order->shop->user_name)->withErrors($e->getMessage());
        }
    }

    public function directOrder($user_name, Product $product)
    {
        if (auth()->check()) {
            $user = User::find(auth()->id());
        } else {
            $user = new User;
        }

        $shop = $product->shop;
        if ($shop->qr_code_option == 2) {
            $price = $product->currentPrice;
            Cart::session($user_name)->add($product->id, $product->name, $price, 1)->associate('App\Models\Product');

            return redirect()->route('product', ['product' => $product, 'user_name' => $user_name])->with('success_msg_cart', 'Item has been added to cart!');
        }
        try {

            $order = NewOrder::ingridents($user, $product, $shop)->cook();
            $payment = (new Payment($order))->getUrl();

            if ($payment['status'] == false) {
                throw new Exception($payment['data']['message']);
            }
            $order->payment_id = $payment['data']['payment_id'];
            $order->payment_url = $payment['data']['url'];

            $order->save();

            return redirect(route('payment', ['user_name' => $shop->user_name, 'order' => $order]))->with('success_msg', 'Order Placed. Please pay now for confirm order');
        } catch (Exception $e) {
            return redirect()->route('product', ['user_name' => $shop->user_name, $product->slug])->withErrors($e->getMessage());
        } catch (Error $e) {
            return redirect()->route('product', ['user_name' => $shop->user_name, $product->slug])->withErrors($e->getMessage());
        }
    }

    public function qrCallback($user_name, Order $order)
    {
        Cart::session($user_name)->clear();
        $message = '<br>Her er dine detaljer for din ordre plassert den '.$order->created_at->format('M d, Y').' hos '.$order->shop->name.' <br><br><b>Vennligst betal nå for å bekrefte din ordre:</b><br>';
        Mail::to($order->email)->send(new OrderPlaced($order, $message, true));

        return 'success';
    }
}
