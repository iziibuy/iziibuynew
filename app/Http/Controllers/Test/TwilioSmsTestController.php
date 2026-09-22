<?php

namespace App\Http\Controllers\Test;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SMS\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class TwilioSmsTestController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureAllowed();

        return view('test.twilio-sms', [
            'phone' => old('phone', $request->query('phone', '')),
            'message' => old('message', $request->query('message', 'Iziibuy Twilio test SMS at '.now()->toDateTimeString())),
            'from' => config('services.twilio.from'),
            'configured' => filled(config('services.twilio.sid'))
                && filled(config('services.twilio.token'))
                && filled(config('services.twilio.from')),
            'lastResult' => session('twilio_sms_test_result'),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $this->ensureAllowed();

        $validated = $request->validate([
            'phone' => ['required', 'string', 'min:8', 'max:20'],
            'message' => ['required', 'string', 'max:320'],
        ]);

        if (blank(config('services.twilio.sid')) || blank(config('services.twilio.token')) || blank(config('services.twilio.from'))) {
            return back()
                ->withInput()
                ->withErrors('Twilio is not configured. Set TWILIO_SID, TWILIO_TOKEN, and TWILIO_FROM in .env.');
        }

        $phone = trim($validated['phone']);
        $message = trim($validated['message']);

        try {
            app(SmsService::class)->send($phone, $message);

            session([
                'twilio_sms_test_result' => [
                    'success' => true,
                    'phone' => str_starts_with($phone, '+') ? $phone : '+'.$phone,
                    'message' => $message,
                    'from' => config('services.twilio.from'),
                    'sent_at' => now()->toDateTimeString(),
                ],
            ]);

            return redirect()
                ->route('test.twilio.sms')
                ->with('success', 'Test SMS sent to '.$phone);
        } catch (Throwable $exception) {
            session([
                'twilio_sms_test_result' => [
                    'success' => false,
                    'phone' => $phone,
                    'message' => $message,
                    'from' => config('services.twilio.from'),
                    'error' => $exception->getMessage(),
                    'sent_at' => now()->toDateTimeString(),
                ],
            ]);

            return back()
                ->withInput()
                ->withErrors('Twilio SMS failed: '.$exception->getMessage());
        }
    }

    protected function ensureAllowed(): void
    {
        if (app()->environment('local', 'testing', 'staging') || (bool) config('app.debug')) {
            return;
        }

        $user = auth()->user();

        if ($user instanceof User && $user->isAdmin()) {
            return;
        }

        abort(404);
    }
}
