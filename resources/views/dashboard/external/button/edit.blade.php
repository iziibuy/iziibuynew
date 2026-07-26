<x-dashboard.external>
    <div class="card">
        <div class="card-body">
            <h3 class="mb-3">{{ __('Edit button') }}</h3>
            <form action="{{ route('external.buttonPayment.update', $paymentApi) }}" method="post">
                @csrf
                <x-form.input type="url" name="domain" label="Domain" value="{{ $paymentApi->domain }}" />
                <x-form.input type="url" name="success" label="Success redirect url"
                    value="{{ $paymentApi->success_redirect_url }}" />
                <x-form.input type="url" name="failed" label="Failed redirect url"
                    value="{{ $paymentApi->failed_redirect_url }}" />
                <x-form.input type="url" name="cancel_callback_url" label="Cancel callback url (optional)"
                    value="{{ $paymentApi->cancel_callback_url }}" />

                <div class="form-group form-check mb-3">
                    <input type="hidden" name="is_subscription" value="0">
                    <input type="checkbox" class="form-check-input" id="is_subscription" name="is_subscription"
                        value="1" {{ old('is_subscription', $paymentApi->is_subscription) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_subscription">
                        {{ __('This is a subscription button') }}
                    </label>
                </div>

                <button class="btn btn-primary">{{ __('Submit') }}</button>
            </form>
        </div>
    </div>
</x-dashboard.external>
