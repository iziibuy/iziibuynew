<?php

namespace App\Http\Controllers\Dashboard\External;

use App\Http\Controllers\Controller;
use App\Models\ExternalBooking;
use App\Payment\External\Elavon\ExternalBookingElavonPayment;
use App\Payment\External\Surfboard\ExternalBookingSurfboardApi;
use App\Services\SMS\SmsService;
use App\Support\ExternalPaymentAcquirer;
use Error;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExternalBookingController extends Controller
{
    public function index(Request $request)
    {
        $query = ExternalBooking::where('payment_method_access_id', auth()->user()->paymentMethodAccess->id)->latest();

        if ($request->booking_number) {
            $query->where('booking_number', 'like', "%{$request->booking_number}%");
        }
        if ($request->phone_number) {
            $query->where('phone_number', 'like', "%{$request->phone_number}%");
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->from_date && $request->to_date) {
            $query->whereBetween('created_at', [$request->from_date, $request->to_date]);
        }

        $bookings = $query->paginate(10);

        $breadcrumbs = [
            ['title' => 'Dashboard', 'url' => route('external.dashboard'), 'icon' => 'fas fa-home'],
            ['title' => 'Booking History', 'url' => null, 'icon' => 'fas fa-calendar-alt'],
        ];

        return view('dashboard.external.booking.index', compact('bookings', 'breadcrumbs'));
    }

    public function create()
    {
        $paymentMethodAccess = auth()->user()->paymentMethodAccess;

        $breadcrumbs = [
            ['title' => 'Dashboard', 'url' => route('external.dashboard'), 'icon' => 'fas fa-home'],
            ['title' => 'Bookings', 'url' => route('external.booking.index'), 'icon' => 'fas fa-calendar-alt'],
            ['title' => 'Create Payment Request', 'url' => null, 'icon' => 'fas fa-plus'],
        ];

        return view('dashboard.external.booking.create', compact('breadcrumbs', 'paymentMethodAccess'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'booking_number' => 'required',
                'country_code' => 'required',
                'phone_number' => 'required',
                'total' => 'required|numeric|min:0.01',
            ]);

            $booking = DB::transaction(function () use ($request) {
                $phone_number = $request->country_code.$request->phone_number;

                $taxPercentage = auth()->user()->paymentMethodAccess->tax_percentage != null ? auth()->user()->paymentMethodAccess->tax_percentage : 0;

                $booking = ExternalBooking::create([
                    'payment_method_access_id' => auth()->user()->paymentMethodAccess->id,
                    'ulid' => Str::ulid(),
                    'booking_number' => $request->booking_number,
                    'phone_number' => $phone_number,
                    'subtotal' => (float) $request->total - ($taxPercentage ? ($request->total * $taxPercentage) / (100 + $taxPercentage) : 0),
                    'total' => (float) $request->total,
                    'currency' => auth()->user()->paymentMethodAccess->currency ?: 'NOK',
                    'tax' => (float) ($taxPercentage ? ($request->total * $taxPercentage) / (100 + $taxPercentage) : 0),
                    'payment_method' => ExternalPaymentAcquirer::resolve(
                        auth()->user()->paymentMethodAccess->paymentMethod
                    ),
                ]);

                return $booking;
            });

            $booking->refresh();
            $sms_text = auth()->user()->paymentMethodAccess->sms_text
                ?: 'Dear customer, please complete your payment of {TOTAL} for booking {BOOKING_NUMBER}. Pay securely here: {LINK}

More text for 2izii: https://iziibuy.com';

            if (env('APP_ENV') === 'production' && ! empty($sms_text)) {
                try {
                    $link = route('external-payment', $booking);
                    $message = str_replace(
                        ['{BOOKING_NUMBER}', '{TOTAL}', '{LINK}'],
                        [$booking->booking_number, $booking->total.' '.$booking->currency, $link],
                        $sms_text
                    );

                    $sms = new SmsService;
                    $sms->send($booking->phone_number, $message);
                } catch (\Exception|Error $e) {
                }
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment request created successfully!',
                    'booking_id' => $booking->id,
                ]);
            }

            return redirect()->route('external.booking.index')->with('success', 'Payment request created successfully!');
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors(),
                ], 422);
            }
            throw $e;
        } catch (\Exception $e) {
            Log::error('Booking creation failed: '.$e->getMessage());

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'An error occurred while creating the payment request. Please try again.',
                ], 500);
            }

            return redirect()->route('external.booking.index')->with('error', 'An error occurred while creating the payment request. Please try again.');
        }
    }

    public function createPaymentLink(ExternalBooking $externalBooking)
    {
        if ($externalBooking->payment_status == 'PAID') {
            return redirect()->route('external-payment-page', $externalBooking->ulid);
        }
        if ($externalBooking->payment_id && $externalBooking->payment_url) {
            return redirect($externalBooking->payment_url);
        }

        if ($externalBooking->usesSurfboard()) {
            $payment = (new ExternalBookingSurfboardApi($externalBooking))->getPaymentLink();
            $acquirer = 'surfboard';
        } else {
            $payment = (new ExternalBookingElavonPayment($externalBooking))->getPaymentLink();
            $acquirer = 'elavon';
        }

        if ($payment['status']) {
            $externalBooking->update([
                'payment_id' => $payment['data']['payment_id'],
                'payment_url' => $payment['data']['url'],
                'payment_method' => $acquirer,
            ]);

            return redirect($payment['data']['url']);
        } else {
            return redirect()->route('external-payment-page', $externalBooking->ulid);
        }
    }

    public function destroy(ExternalBooking $externalBooking)
    {
        if ($externalBooking->payment_method_access_id !== auth()->user()->paymentMethodAccess->id) {
            return redirect()->route('external.booking.index')->withErrors('Unauthorized access to booking');
        }
        $externalBooking->delete();

        return redirect()->route('external.booking.index')->with('success', 'Booking deleted successfully');
    }

    public function invoice(ExternalBooking $externalBooking)
    {
        $paymentMethodAccess = auth()->user()->paymentMethodAccess;

        if ($externalBooking->payment_method_access_id !== auth()->user()->paymentMethodAccess->id) {
            return redirect()->route('external.booking.index')->withErrors('Unauthorized access to booking');
        }

        $breadcrumbs = [
            ['title' => 'Dashboard', 'url' => route('external.dashboard'), 'icon' => 'fas fa-home'],
            ['title' => 'Bookings', 'url' => route('external.booking.index'), 'icon' => 'fas fa-calendar-alt'],
            ['title' => 'Invoice #'.$externalBooking->booking_number, 'url' => null, 'icon' => 'fas fa-file-invoice'],
        ];

        return view('dashboard.external.booking.invoice', compact('externalBooking', 'breadcrumbs', 'paymentMethodAccess'));
    }

    public function exportBookings()
    {
        $bookings = ExternalBooking::where('payment_method_access_id', auth()->user()->paymentMethodAccess->id)->latest()->get();

        $csvHeader = ['ID', 'Booking Number', 'Phone Number', 'Amount', 'Currency', 'Status', 'Transaction ID'];
        $callback = function () use ($bookings, $csvHeader) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $csvHeader);
            foreach ($bookings as $b) {
                fputcsv($file, [
                    $b->id,
                    $b->booking_number,
                    $b->phone_number,
                    $b->total,
                    $b->currency,
                    $b->status,
                    $b->payment_id ?: 'N/A',
                ]);
            }
            fclose($file);
        };

        return response()->streamDownload($callback, 'bookings.csv');
    }
}
