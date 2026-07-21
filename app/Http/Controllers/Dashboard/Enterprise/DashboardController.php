<?php

namespace App\Http\Controllers\Dashboard\Enterprise;

use App\Http\Controllers\Controller;
use App\Mail\ElavonPaymentDetails;
use App\Mail\paymentCapture;
use App\Models\Enterprise;
use App\Models\EnterpriseOnboarding;
use App\Models\PaymentMethodAccess;
use App\Models\ProtectedLink;
use App\Models\Subscription;
use App\Models\SubscriptionCharge;
use App\Models\User;
use App\Payment\Elavon\ElavonEnterpriseOnboardingSubscription;
use App\Payment\Enterprise\Surfboard\EnterpriseSurfboardMarchant;
use App\Payment\Subscribe;
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

class DashboardController extends Controller
{
    public function startSubscription(Subscription $subscription)
    {
        if ($subscription->subscribable->user_id != auth()->id()) {
            return abort(403);
        }

        $enterprise = $subscription->subscribable;

        if (request()->has('type')) {
            $enterprise->update([
                'shopperId' => null,
            ]);
            $subscription->update([
                'key' => null,
                'url' => null,
                'status' => 0,
                'establishment_status' => 0,
                'paid_at' => null,
            ]);
        }

        try {
            DB::beginTransaction();

            $subscriptionFee = $enterprise->fresh()->signupFee();

            $elavon = new ElavonEnterpriseOnboardingSubscription($enterprise);

            $result = $elavon->getPaymentLink(
                $subscriptionFee,
                route('enterprise.subscription.success', $subscription),
                route('enterprise.subscription.cancel', $subscription)
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
        $enterprise = null;

        try {
            DB::beginTransaction();

            $sessionId = $request->input('sessionId')
                ?? $request->input('session_id')
                ?? $request->query('sessionId')
                ?? $request->query('session_id')
                ?? $subscription->key;

            if (! is_string($sessionId) || trim($sessionId) === '') {
                DB::rollBack();

                return redirect()->route('enterprise.contract')->withErrors('Payment session is missing.');
            }

            $enterprise = $subscription->subscribable;
            $elavon = new ElavonEnterpriseOnboardingSubscription($enterprise);
            $result = $elavon->finalizeHostedSubscriptionFromSession(
                trim($sessionId),
                $subscription,
                $enterprise->fresh()->signupFee()
            );

            if (! $result['status']) {
                DB::rollBack();

                return redirect()->route('enterprise.contract')
                    ->withErrors($result['data']['message'] ?? 'Payment could not be completed.');
            }

            $enterprise->status = true;
            $enterprise->key = $enterprise->key ?: (string) Str::uuid();
            $enterprise->last_paid_at = now();
            $enterprise->save();

            DB::commit();

            return redirect()->route('enterprise.contract')->with('success', 'Subscription completed');
        } catch (Exception $e) {
            DB::rollBack();

            return redirect()->route('enterprise.contract')->withErrors($e->getMessage());
        } catch (Error $e) {
            DB::rollBack();

            return redirect()->route('enterprise.contract')->withErrors($e->getMessage());
        }
    }

    public function subscriptionCancel(Subscription $subscription)
    {
        return redirect()->route('enterprise.subscription.payment')->withErrors('Payment cancelled.');
    }

    public function subscriptionIndex()
    {
        $enterprise = auth()->user()->enterpriseOnboarding;
        $subscription = $enterprise->subscription()->firstOrCreate([], [
            'fee' => (int) round($enterprise->signupFee()),
        ]);

        return view('dashboard.enterprise.subscription', compact('enterprise', 'subscription'));
    }

    public function dashboard()
    {
        $paymentMethodAccesses = PaymentMethodAccess::where('user_id', auth()->id())->get();

        return view('dashboard.enterprise.index', compact('paymentMethodAccesses'));
    }

    public function edit()
    {

        $enterprise = auth()->user()->enterpriseOnboarding;

        return view('dashboard.enterprise.edit', compact('enterprise'));
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
        ]);
        $enterprise = auth()->user()->enterpriseOnboarding;

        $enterprise->update([
            'company_name' => $request->company_name,
            'company_email' => $request->company_email,
            'company_address' => $request->company_address,
            'company_domain' => $request->company_domain,
            'company_registration' => $request->company_registration,
        ]);

        return redirect()->back()->with('success', 'Enterprise info updated successfully');
    }

    public function enterprise()
    {

        $enterprise = auth()->user()->enterpriseOnboarding;

        if ($enterprise && ! $enterprise->canProcessPayments()) {
            return redirect()->route('enterprise.subscription.payment');
        }

        if ($enterprise->user_id != auth()->id()) {
            abort(403);
        }
        $subscription = $enterprise->subscription->key;
        $subscriptionQuickpay = (new Subscribe)->subscription($subscription)->subscription;

        return view('dashboard.enterprise.index', compact('enterprise', 'subscriptionQuickpay'));
    }

    public function charges()
    {

        $paymentMethodAccess = auth()->user()->enterpriseOnboarding;
        if ($paymentMethodAccess->user_id != auth()->id()) {
            abort(403);
        }
        $charges = $paymentMethodAccess->subscription->charges()->latest()->paginate(10);

        return view('dashboard.enterprise.charges', compact('paymentMethodAccess', 'charges'));
    }

    public function contract()
    {

        $enterprise = auth()->user()->enterpriseOnboarding;
        if ($enterprise->user_id != auth()->id()) {
            abort(403);
        }

        return view('dashboard.enterprise.contract', compact('enterprise'));
    }

    public function cancelSubscription(Subscription $subscription)
    {
        if ($subscription->subscribable->user_id != auth()->id()) {
            return abort(403);
        }
        $quickPay = new Subscribe;
        $response = $quickPay->stopsubscription($subscription);

        return redirect()->back()->with('success', 'Subscription cancelled for this account');
    }

    public function downloadInvoice(SubscriptionCharge $charge)
    {

        $reg_tax = setting('payment.registration_tax');
        $amount = $charge->amount;
        $base_price = ($amount * 100) / (100 + $reg_tax);
        $tax = $amount - $base_price;
        $pdf = Pdf::loadView('dashboard.enterprise.pdf.invoice', ['charge' => $charge, 'tax' => $tax, 'base_price' => $base_price]);
        $fileName = 'invoice-'.uniqid().'.pdf';
        try {
            return $pdf->download($fileName);
        } catch (Exception $e) {
            return redirect()->back()->withErrors($e->getMessage());
        }
    }

    public function signContract(Request $request, EnterpriseOnboarding $enterpriseOnboarding)
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

        $enterpriseOnboarding->createMetas([
            'gateway_contract_signed' => $gateways,
            'selected_payment_methods' => $request->paymentMethods,
        ]);

        $enterpriseOnboarding->update([
            'paymentMethod' => implode(',', $paymentMethod),
            'contract_signed' => 1,
        ]);

        return back();
    }

    public function settings()
    {

        return view('dashboard.enterprise.settings');
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
            return redirect()->route('enterprise.settings')->withErrors('The old password is incorrect.');
        }

        // Hash and update the new password
        $user->password = Hash::make($request->new_pass);
        $user->save();

        return redirect()->route('enterprise.dashboard')->with('success', 'Password changed successfully.');
    }

    public function settingsUpdate(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'last_name' => 'required',
            'company_name' => ['required', 'string', 'max:255'],
            'company_email' => ['required', 'string', 'max:255'],
            'company_address.city' => ['required', 'string', 'max:255'],
            'company_address.street' => ['required', 'string', 'max:255'],
            'company_address.zip' => ['required', 'string', 'max:255'],
            'company_registration' => ['required', 'string', 'max:255'],
            'company_domain' => ['required', 'url', 'max:255'],

        ]);
        Auth()->user()->update([
            'name' => $request->name,
            'last_name' => $request->last_name,

        ]);

        auth()->user()->enterpriseOnboarding->update([
            'company_name' => $request->company_name,
            'company_email' => $request->company_email,
            'company_address' => $request->company_address,
            'company_domain' => $request->company_domain,
            'company_registration' => $request->company_registration,
        ]);

        return redirect()->back()->with('success', 'settings update successfully.');
    }

    public function completeProfile()
    {
        return view('dashboard.enterprise.complete');
    }

    public function completeProfileStore(Request $request)
    {
        $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_email' => ['required', 'string', 'max:255'],
            'company_address.city' => ['required', 'string', 'max:255'],
            'company_address.street' => ['required', 'string', 'max:255'],
            'company_address.zip' => ['required', 'string', 'max:255'],
            'company_registration' => ['required', 'string', 'max:255'],
            'company_domain' => ['required', 'url', 'max:255'],
        ]);

        auth()->user()->enterpriseOnboarding->update([
            'company_name' => $request->company_name,
            'company_email' => $request->company_email,
            'company_address' => $request->company_address,
            'company_domain' => $request->company_domain,
            'company_registration' => $request->company_registration,
        ]);

        $enterprise = auth()->user()->enterpriseOnboarding;

        $enterprise->subscription()->firstOrCreate([], [
            'fee' => (int) round($enterprise->signupFee()),
        ]);

        return redirect()->route('enterprise.subscription.payment');
    }

    public function setup_surfboard_payment()
    {
        $enterprise = auth()->user()->enterpriseOnboarding;
        $createMarchant = (new EnterpriseSurfboardMarchant($enterprise))->createMarchant();

        if ($createMarchant['status'] == 'SUCCESS') {

            $enterprise->createMetas([
                'surfboard_webKybUrl' => $createMarchant['data']['webKybUrl'],
                'surfboard_application_id' => $createMarchant['data']['applicationId'],
                'surfboard_application_status' => @$createMarchant['data']['applicationStatus'] ?? false,
            ]);

            $contracts = json_decode($enterprise->gateway_contract_signed, true);
            $contracts['surfboard'] = 1;

            $enterprise->createMetas(['gateway_contract_signed' => $contracts]);

            return redirect($createMarchant['data']['webKybUrl']);
        } else {

            return redirect()->route('enterprise.settings')->withErrors($createMarchant['message']);
        }
    }

    public function setup_elavon_payment()
    {

        $enterprise = auth()->user()->enterpriseOnboarding;

        if ($enterprise->paymentMethod == 'elavon' && $enterprise->elavon_payment_setup == true || $enterprise->elavon_details_verified_by_shop == true) {
            return redirect()->route('shop.dashboard');
        }

        return view('dashboard.enterprise.payments.elavon_setup', compact('enterprise'));
    }

    public function store_setup_elavon_payment(Request $request)
    {
        $enterprise = auth()->user()->enterpriseOnboarding;

        if ($enterprise->paymentMethod == 'elavon' && $enterprise->elavon_payment_setup == true || $enterprise->elavon_details_verified_by_shop == true) {
            return redirect()->route('shop.dashboard');
        }

        // $request->validate([
        // 'meta.name' => 'required',
        // 'meta.businessAddress' => 'required',
        // 'meta.contact_phone' => 'required',
        // 'meta.contactPerson' => 'required',
        // 'meta.contact_email' => 'required',
        // 'meta.company_name' => 'required',
        // 'meta.comapny_address' => 'required',
        // 'meta.ownership' => 'required',
        // 'meta.orgNumber' => 'required',
        // 'meta.foundationDate' => 'required',
        // 'meta.businessDescription' => 'required',
        // 'meta.annualRevenue' => 'required',
        // 'meta.creditCardTurnover' => 'required',
        // 'meta.avgTransactionValue' => 'required',
        // 'meta.cardHolderPresent' => 'required',
        // 'meta.mailPhoneOrder' => 'required',
        // 'meta.internet' => 'required',
        // 'meta.gender' => 'required',
        // 'meta.dob' => 'required',
        // 'meta.share' => 'required',
        // 'meta.ceo' => 'required',
        // 'meta.privateAddress' => 'required',
        // 'meta.otherNationality' => 'required',
        // 'meta.country' => 'required',
        // 'meta.privatePhoneNumber' => 'required',
        // 'meta.mobileNumber' => 'required',
        // 'meta.privateEmail' => 'required',
        // 'meta.idNumber' => 'required',
        // 'meta.issueDate' => 'required',
        // 'meta.expiryDate' => 'required',
        // 'meta.nationality' => 'required',
        // 'meta.bankName' => 'required',
        // 'meta.accountHolderName' => 'required',
        // 'meta.accountNumber' => 'required',
        // 'meta.selectedUserName' => 'required',
        // 'meta.preferredUsername' => 'required',
        // 'meta.userEmail' => 'required',
        // 'meta.userPhoneNumber' => 'required',
        // 'meta.fullNameTitle' => 'required',
        // 'meta.date' => 'required',

        // ]);

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
        $enterprise->createMetas($meta);

        $protectedLink = ProtectedLink::updateOrCreate(['link' => route('view_payment_data', ['id' => $enterprise->id, 'type' => 'enterprise'])], [
            'link' => route('view_payment_data', ['id' => $enterprise->id, 'type' => 'enterprise']),
            'uid' => uniqid(),
            'password' => uniqid(),
        ]);

        $enterprise->createMeta('elavon_details_verified_by_shop', false);

        $viewLink = route('view_payment_data', ['id' => $enterprise->id, 'type' => 'enterprise', 'uid' => $protectedLink->uid, 'password' => $protectedLink->password]);
        $enterprise = auth()->user()->enterpriseOnboarding;
        $shop = $enterprise;
        if ($enterprise->elavon_details_verified_by_shop != true) {
            $pdf = Pdf::loadview('pdf.elavon_payment_shop_details', compact('shop'));
            Mail::to('digitalisertweb@gmail.com')->bcc('didrik.tonnessen@elavon.com')->cc($enterprise->contact_email)->send(new ElavonPaymentDetails($enterprise, $pdf));
            $enterprise->createMeta('elavon_details_verified_by_shop', true);
            $enterprise->createMeta('needKYC', true);
            $contracts = json_decode($enterprise->gateway_contract_signed, true);
            $contracts['elavon'] = 1;
            $enterprise->createMetas(['gateway_contract_signed' => $contracts]);

            return redirect()->route('enterprise.dashboard');
        } else {
            return redirect()->route('enterprise.dashboard')->withErrors('You already verified your information');
        }
        // $contactMail = $request->meta['contact_email'];

        // Mail::to($contactMail)->send(new paymentCapture($enterprise, $viewLink));
        // try {
        //     // Pass the link to the Mailable
        // } catch (\Exception $e) {
        //     return response()->json(['error' => 'Failed to send email.']);
        // }
        // return redirect()->route('enterprise.dashboard')->with('success', 'Your details have been successfully submitted. Please check your email for confirmation . Thank you .');
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

        $enterprise = auth()->user()->enterpriseOnboarding;
        $shop = $enterprise;
        if ($enterprise->elavon_details_verified_by_shop != true) {
            $pdf = Pdf::loadview('pdf.elavon_payment_shop_details', compact('shop'));
            Mail::to('digitalisertweb@gmail.com')->bcc('didrik.tonnessen@elavon.com')->cc($enterprise->contact_email)->send(new ElavonPaymentDetails($enterprise, $pdf));
            $enterprise->createMeta('elavon_details_verified_by_shop', true);
            $enterprise->createMeta('needKYC', true);
            $contracts = json_decode($enterprise->gateway_contract_signed, true);
            $contracts['elavon'] = 1;
            $enterprise->createMetas(['gateway_contract_signed' => $contracts]);

            return redirect()->route('enterprise.dashboard');
        } else {
            return redirect()->route('enterprise.dashboard')->withErrors('You already verified your information');
        }
    }
}
