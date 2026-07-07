<?php

namespace App\Http\Controllers\Dashboard\External;

use App\Http\Controllers\Controller;
use App\Mail\ElavonPaymentDetails;
use App\Mail\ExternalWelcomeEmail;
use App\Mail\PaymentMethodAccessMail;
use App\Models\PaymentMethodAccess;
use App\Models\ProtectedLink;
use App\Models\Subscription;
use App\Models\SubscriptionCharge;
use App\Models\User;
use App\Payment\Elavon\ElavonExternalSubscription;
use App\Payment\External\Surfboard\ExternalSurfboardMarchant;
use Barryvdh\DomPDF\Facade\Pdf;
use Error;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Iziibuy;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    public function registerForm()
    {
        return view('auth.externalregister');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
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
                'role_id' => Role::where('name', 'external')->first()->id,
            ]);

            $paymentMethodAccess = PaymentMethodAccess::create([
                'user_id' => $user->id,
                'company_name' => $request->company_name,
                'company_address' => [
                    'contact_number' => $request->phone,
                    'country' => 'Norway',
                ],
                'fee' => setting('payment.payment_method_fee'),
                'key' => Str::uuid(),
            ]);
            $paymentMethodAccess->createMeta('title', $request->title);

            Mail::to($user->email)->send((new ExternalWelcomeEmail(['email' => $user->email, 'url' => route('login'), 'password' => 'HIDDEN'])));
            DB::commit();
            Auth::login($user);

            return redirect()->route('external.dashboard');
        } catch (Exception $e) {
            DB::rollBack();

            return redirect()->back()->withErrors($e->getMessage());
        } catch (Error $e) {
            DB::rollBack();

            return redirect()->back()->withErrors($e->getMessage());
        }
    }

    public function startSubscription(Subscription $subscription)
    {
        if ($subscription->subscribable->user_id != auth()->id()) {
            return abort(403);
        }

        try {
            DB::beginTransaction();
            $paymentMethodAccess = $subscription->subscribable;
            $subscriptionFee = (float) ($subscription->getAttributes()['fee'] ?? 0);

            $elavon = new ElavonExternalSubscription($paymentMethodAccess);

            $result = $elavon->getPaymentLink(
                $subscriptionFee,
                route('external.subscription.success', $subscription),
                route('external.subscription.cancel', $subscription)
            );

            if (! $result['status']) {
                DB::rollBack();

                return redirect()->back()->withErrors($result['data']['message'] ?? 'Payment link failed');
            }

            $subscription->update([
                'key' => $result['data']['payment_id'],
                'url' => $result['data']['url'],
                'fee' => (int) round($subscriptionFee),
            ]);
            DB::commit();

            return redirect($subscription->url);
        } catch (Exception $e) {
            DB::rollBack();

            return redirect()->back()->withErrors($e->getMessage());
        } catch (Error $e) {
            DB::rollBack();

            return redirect()->back()->withErrors($e->getMessage());
        }
    }

    public function subscriptionSuccess(Request $request, Subscription $subscription)
    {
        $paymentMethodAccess = null;

        try {
            DB::beginTransaction();

            $sessionId = $request->input('sessionId')
                ?? $request->input('session_id')
                ?? $request->query('sessionId')
                ?? $request->query('session_id')
                ?? $subscription->key;

            if (! is_string($sessionId) || trim($sessionId) === '') {
                DB::rollBack();

                return redirect()->route('external.contract')->withErrors('Payment session is missing.');
            }

            $paymentMethodAccess = $subscription->subscribable;
            $elavon = new ElavonExternalSubscription($paymentMethodAccess);
            $result = $elavon->finalizeHostedSubscriptionFromSession(
                trim($sessionId),
                $subscription,
                (float) ($subscription->getAttributes()['fee'] ?? 0)
            );

            if (! $result['status']) {
                DB::rollBack();

                return redirect()->route('external.contract')
                    ->withErrors($result['data']['message'] ?? 'Payment could not be completed.');
            }

            DB::commit();

            Mail::to($paymentMethodAccess->user->email)->send(new PaymentMethodAccessMail($paymentMethodAccess));
            Mail::to(setting('site.email'))->send(new PaymentMethodAccessMail($paymentMethodAccess));

            return redirect()->route('external.contract')->with('success', 'Subscription completed');
        } catch (Exception $e) {
            DB::rollBack();

            return redirect()->route('external.contract')->withErrors($e->getMessage());
        } catch (Error $e) {
            DB::rollBack();

            return redirect()->route('external.contract')->withErrors($e->getMessage());
        }
    }

    public function subscriptionCancel(Subscription $subscription)
    {
        return redirect()->route('external.contract')->withErrors('Payment cancelled.');
    }

    public function dashboard()
    {
        $paymentMethodAccesses = PaymentMethodAccess::where('user_id', auth()->id())->get();

        return view('dashboard.external.index', compact('paymentMethodAccesses'));
    }

    public function edit()
    {

        $paymentMethodAccess = auth()->user()->paymentMethodAccess;

        return view('dashboard.external.edit', compact('paymentMethodAccess'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_email' => ['required', 'string', 'max:255'],
            'company_address.city' => ['required', 'string', 'max:255'],
            'company_address.street' => ['required', 'string', 'max:255'],
            'company_address.zip' => ['required', 'string', 'max:255'],
            'company_registration' => ['required', 'string', 'max:255'],
            'company_domain' => ['required', 'url', 'max:255'],
            'site_mode' => 'required',
        ]);
        $paymentMethodAccess = auth()->user()->paymentMethodAccess;

        $paymentMethodAccess->update([
            'company_name' => $request->company_name,
            'company_email' => $request->company_email,
            'company_address' => $request->company_address,
            'company_domain' => $request->company_domain,
            'company_registration' => $request->company_registration,
            'site_mode' => $request->site_mode,
        ]);

        return view('dashboard.external.edit', compact('paymentMethodAccess'));
    }

    public function paymentMethodAccess()
    {

        $paymentMethodAccess = auth()->user()->paymentMethodAccess;
        $billingStatus = (new ElavonExternalSubscription($paymentMethodAccess))->getBillingStatus();

        return view('dashboard.external.paymentMethodAccess', compact('paymentMethodAccess', 'billingStatus'));
    }

    public function charges()
    {

        $paymentMethodAccess = auth()->user()->paymentMethodAccess;
        if ($paymentMethodAccess->user_id != auth()->id()) {
            abort(403);
        }
        $charges = $paymentMethodAccess->subscription->charges()->latest()->paginate(10);

        return view('dashboard.external.charges', compact('paymentMethodAccess', 'charges'));
    }

    public function contract()
    {

        $paymentMethodAccess = auth()->user()->paymentMethodAccess;
        if ($paymentMethodAccess->user_id != auth()->id()) {
            abort(403);
        }

        return view('dashboard.external.contract', compact('paymentMethodAccess'));
    }

    public function cancelSubscription(Subscription $subscription)
    {
        if ($subscription->subscribable->user_id != auth()->id()) {
            return abort(403);
        }

        $subscription->update(['status' => 0]);
        $subscription->subscribable->update(['status' => 0]);

        return redirect()->back()->with('success', 'Subscription cancelled for this account');
    }

    public function downloadInvoice(SubscriptionCharge $charge)
    {
        $reg_tax = setting('payment.registration_tax');
        $amount = $charge->amount;
        $base_price = ($amount * 100) / (100 + $reg_tax);
        $tax = $amount - $base_price;
        $pdf = Pdf::loadView('dashboard.external.pdf.invoice', ['charge' => $charge, 'tax' => $tax, 'base_price' => $base_price]);
        $fileName = 'invoice/invoice'.uniqid().'.pdf';
        try {
            return $pdf->download($fileName);
        } catch (Exception $e) {
            return redirect()->back()->withErrors($e->getMessage());
        }
    }

    public function signContract(Request $request, PaymentMethodAccess $paymentMethodAccess)
    {

        $gateways = [];
        $paymentMethod = [];

        if (Iziibuy::isSubset(['mastercard', 'visa', 'amex'], $request->paymentMethods)) {
            $gateways['elavon'] = 0;
            array_push($paymentMethod, 'elavon');
        }

        // surfboard
        if (Iziibuy::isSubset(['googlepay', 'applepay', 'vipps'], $request->paymentMethods)) {
            $gateways['surfboard'] = 0;
            array_push($paymentMethod, 'surfboard');
        }

        $paymentMethodAccess->createMetas([
            'gateway_contract_signed' => $gateways,
            'selected_payment_methods' => $request->paymentMethods,
        ]);

        $paymentMethodAccess->update([
            'paymentMethod' => implode(',', $paymentMethod),
            'contract_signed' => 1,
        ]);

        return back();
    }

    public function settings()
    {
        $paymentMethodAccess = auth()->user()->paymentMethodAccess;

        return view('dashboard.external.settings', compact('paymentMethodAccess'));
    }

    public function passwordUpdate(Request $request)
    {
        // Validate the form data
        $request->validate([
            'old_pass' => 'required',
            'new_pass' => 'required|min:8',
        ]);
        $user = Auth::user();
        if (! Hash::check($request->old_pass, $user->password)) {
            return redirect()->route('external.settings')->withErrors('The old password is incorrect.');
        }

        // Hash and update the new password
        $user->password = Hash::make($request->new_pass);
        $user->save();

        return redirect()->route('external.dashboard')->with('success', 'Password changed successfully.');
    }

    public function settingsUpdate(Request $request)
    {

        $paymentMethodAccess = auth()->user()->paymentMethodAccess;
        $request->validate([
            'name' => 'required',
            'last_name' => 'required',
            'company_name' => ['required', 'string', 'max:255'],
            'company_email' => ['required', 'string', 'max:255'],
            'company_address.city' => ['required', 'string', 'max:255'],
            'company_address.street' => ['required', 'string', 'max:255'],
            'company_address.zip' => ['required', 'string', 'max:255'],
            'company_registration' => ['required', 'string', 'max:255'],
            'company_domain' => ['required', 'url', 'max:255', 'unique:payment_method_accesses,company_domain,'.$paymentMethodAccess->id],

        ]);
        Auth()->user()->update([
            'name' => $request->name,
            'last_name' => $request->last_name,

        ]);

        $paymentMethodAccess->update([
            'company_name' => $request->company_name,
            'company_email' => $request->company_email,
            'company_address' => $request->company_address,
            'company_domain' => $request->company_domain,
            'company_registration' => $request->company_registration,
        ]);

        $paymentMethodAccess->createMetas($request->meta);

        return redirect()->back()->with('success', 'settings update successfully.');
    }

    public function completeProfile()
    {
        return view('dashboard.external.complete');
    }

    public function completeProfileStore(Request $request)
    {
        $request->validate([
            'company_email' => ['required', 'string', 'max:255'],
            'company_address.city' => ['required', 'string', 'max:255'],
            'company_address.street' => ['required', 'string', 'max:255'],
            'company_address.zip' => ['required', 'string', 'max:255'],
            'company_registration' => ['required', 'string', 'max:255'],
            'company_domain' => ['required', 'url', 'max:255', 'unique:payment_method_accesses,company_domain'],
        ]);

        $paymentMethodAccess = auth()->user()->paymentMethodAccess;
        $paymentMethodAccess->update([
            'company_email' => $request->company_email,
            'company_address' => $request->company_address,
            'company_domain' => $request->company_domain,
            'company_registration' => $request->company_registration,
        ]);

        $subscription_fee = $paymentMethodAccess->fresh()->fee();

        $subscription = $paymentMethodAccess->subscription()->create([
            'fee' => (int) round($subscription_fee),
        ]);

        $elavon = new ElavonExternalSubscription($paymentMethodAccess);
        $result = $elavon->getPaymentLink(
            $subscription_fee,
            route('external.subscription.success', $subscription),
            route('external.subscription.cancel', $subscription)
        );

        if (! $result['status']) {
            return redirect()->back()->withErrors($result['data']['message'] ?? 'Payment link failed');
        }

        $subscription->update([
            'key' => $result['data']['payment_id'],
            'url' => $result['data']['url'],
            'fee' => (int) round($subscription_fee),
        ]);

        return redirect($subscription->url);
    }

    public function setup_surfboard_payment()
    {
        $paymentMethodAccess = auth()->user()->paymentMethodAccess;
        $createMarchant = (new ExternalSurfboardMarchant($paymentMethodAccess))->createMarchant();

        if ($createMarchant['status'] == 'SUCCESS') {

            $paymentMethodAccess->createMetas([
                'surfboard_webKybUrl' => $createMarchant['data']['webKybUrl'],
                'surfboard_application_id' => $createMarchant['data']['applicationId'],
                'surfboard_application_status' => @$createMarchant['data']['applicationStatus'] ?? false,
            ]);

            $contracts = json_decode($paymentMethodAccess->gateway_contract_signed, true);
            $contracts['surfboard'] = 1;

            $paymentMethodAccess->createMetas(['gateway_contract_signed' => $contracts]);

            return redirect($createMarchant['data']['webKybUrl']);
        } else {

            return redirect()->route('external.settings')->withErrors($createMarchant['message']);
        }
    }

    public function setup_elavon_payment()
    {
        $external = auth()->user()->paymentMethodAccess;
        if (Iziibuy::isSubset(['elavon'], explode(',', $external->paymentMethod)) && $external->elavon_payment_setup == true || $external->elavon_details_verified_by_shop == true) {
            return redirect()->route('shop.dashboard');
        }

        return view('dashboard.external.payments.elavon_setup', compact('external'));
    }

    public function store_setup_elavon_payment(Request $request)
    {
        $external = auth()->user()->paymentMethodAccess;

        if (Iziibuy::isSubset(['elavon'], explode(',', $external->paymentMethod)) && $external->elavon_payment_setup == true || $external->elavon_details_verified_by_shop == true) {
            return redirect()->route('external.dashboard');
        }

        $imageData = $request->input('signature');
        $imageData = substr($imageData, strpos($imageData, ',') + 1);
        $imageData = base64_decode($imageData);
        $filename = 'signature/signature_'.uniqid().'.png';

        Storage::disk('s3')->put($filename, $imageData);
        $meta = $request->meta;
        $meta['customer_profile'] = json_encode($meta['customer_profile']);
        $meta['authrized'] = json_encode($meta['authrized']);
        $meta['financial'] = json_encode($meta['financial']);
        $meta['report'] = json_encode($meta['report']);
        $meta['customerDetails'] = json_encode($meta['customerDetails']);
        $meta['trading'] = json_encode($meta['trading']);
        $meta['partner'] = json_encode($meta['partner']);
        $meta['productId'] = json_encode($meta['productId']);

        $meta['signature'] = $filename;
        $meta['ip_address'] = $request->ip();
        $meta['date'] = now();
        $external->createMetas($meta);

        $protectedLink = ProtectedLink::updateOrCreate(['link' => route('view_payment_data', ['id' => $external->id, 'type' => 'enterprise'])], [
            'link' => route('view_payment_data', ['id' => $external->id, 'type' => 'enterprise']),
            'uid' => uniqid(),
            'password' => uniqid(),
        ]);

        $external->createMeta('elavon_details_verified_by_shop', false);

        $viewLink = route('view_payment_data', ['id' => $external->id, 'type' => 'external', 'uid' => $protectedLink->uid, 'password' => $protectedLink->password]);
        $external = auth()->user()->paymentMethodAccess;

        if ($external->elavon_details_verified_by_shop != true) {
            $pdf = Pdf::loadview('pdf.elavon_payment_shop_details', ['shop' => $external]);

            Mail::to('digitalisertweb@gmail.com')->bcc('didrik.tonnessen@elavon.com')->cc($external->contact_email)->send(new ElavonPaymentDetails($external, $pdf));
            $external->createMeta('elavon_details_verified_by_shop', true);
            $external->createMeta('needKYC', true);
            $contracts = json_decode($external->gateway_contract_signed, true);
            $contracts['elavon'] = 1;
            $external->createMetas(['gateway_contract_signed' => $contracts]);

            return redirect()->route('external.dashboard');
        } else {
            return redirect()->route('external.dashboard')->withErrors('You already verified your information');
        }
    }

    public function viewPaymentData($id)
    {
        $external = PaymentMethodAccess::fid($id);

        // $external = auth()->user()->shop;
        if ($external->elavon_details_verified_by_shop != true) {
            return view('dashboard.enterprise.payments.confrimPaymentCapture', ['shop' => $external]);
        } else {
            return redirect()->route('enterprise.dashboard')->withErrors('You already verified your information');
        }
    }

    public function verifyElavonPayment(Request $request)
    {

        $external = auth()->user()->paymentMethodAccess;

        if ($external->elavon_details_verified_by_shop != true) {
            $pdf = Pdf::loadview('pdf.elavon_payment_shop_details', compact('shop'));
            Mail::to('digitalisertweb@gmail.com')->bcc('didrik.tonnessen@elavon.com')->cc($external->contact_email)->send(new ElavonPaymentDetails($external, $pdf));
            $external->createMeta('elavon_details_verified_by_shop', true);
            $external->createMeta('needKYC', true);
            $contracts = json_decode($external->gateway_contract_signed, true);
            $contracts['elavon'] = 1;
            $external->createMetas(['gateway_contract_signed' => $contracts]);

            return redirect()->route('external.dashboard');
        } else {
            return redirect()->route('external.dashboard')->withErrors('You already verified your information');
        }
    }
}
