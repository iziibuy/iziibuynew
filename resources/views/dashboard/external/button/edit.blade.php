<x-dashboard.external>
    @php
        $isElavon = ($paymentApi->paymentMethodAccess?->paymentMethod ?? null) === 'elavon';
        $checkoutEnabled = old('elavon_link_mode', $paymentApi->elavon_link_mode ?? \App\Models\PaymentApi::ELAVON_LINK_MODE_HOSTED)
            === \App\Models\PaymentApi::ELAVON_LINK_MODE_CHECKOUTJS;
        $checkoutTheme = $paymentApi->checkoutJsTheme();
        $openCheckoutTab = $isElavon && collect([
            'checkout_company_name',
            'checkout_heading',
            'checkout_lead',
            'checkout_pay_button_label',
            'checkout_cancel_label',
            'checkout_summary_note',
            'checkout_footer',
            'checkout_primary_color',
            'checkout_secondary_color',
            'checkout_background_color',
            'checkout_custom_css',
            'checkout_logo',
        ])->contains(fn (string $key) => $errors->has($key));
        $appearance = $paymentApi->checkoutjs_appearance ?? [];
    @endphp

    <div class="card">
        <div class="card-body">
            <h3 class="mb-3">{{ __('Edit button') }}</h3>

            @if ($isElavon)
                <ul class="nav nav-tabs mb-3" id="button-edit-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $openCheckoutTab ? '' : 'active' }}" id="button-settings-tab" data-bs-toggle="tab"
                            data-bs-target="#button-settings" type="button" role="tab" aria-controls="button-settings"
                            aria-selected="{{ $openCheckoutTab ? 'false' : 'true' }}">
                            {{ __('Button settings') }}
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $openCheckoutTab ? 'active' : '' }}" id="checkout-page-tab" data-bs-toggle="tab"
                            data-bs-target="#checkout-page" type="button" role="tab" aria-controls="checkout-page"
                            aria-selected="{{ $openCheckoutTab ? 'true' : 'false' }}">
                            {{ __('Elavon checkout page') }}
                        </button>
                    </li>
                </ul>
            @endif

            <form action="{{ route('external.buttonPayment.update', $paymentApi) }}" method="post" enctype="multipart/form-data">
                @csrf

                <div class="tab-content" id="button-edit-tab-content">
                    <div class="tab-pane fade {{ $openCheckoutTab ? '' : 'show active' }}" id="button-settings" role="tabpanel"
                        aria-labelledby="button-settings-tab">
                        <x-form.input type="url" name="domain" label="Domain" value="{{ $paymentApi->domain }}" />
                        <x-form.input type="url" name="success" label="Success redirect url"
                            value="{{ $paymentApi->success_redirect_url }}" />
                        <x-form.input type="url" name="failed" label="Failed redirect url"
                            value="{{ $paymentApi->failed_redirect_url }}" />
                        <x-form.input type="url" name="cancel_callback_url" label="Cancel callback url (optional)"
                            value="{{ $paymentApi->cancel_callback_url }}" />

                        @if ($isElavon)
                            <x-form.input type="select" name="elavon_link_mode"
                                label="{{ __('Elavon payment page') }}"
                                :options="[
                                    \App\Models\PaymentApi::ELAVON_LINK_MODE_HOSTED => __('Elavon hosted page'),
                                    \App\Models\PaymentApi::ELAVON_LINK_MODE_CHECKOUTJS => __('Own CheckoutJS page'),
                                ]"
                                :value="old('elavon_link_mode', $paymentApi->elavon_link_mode ?? \App\Models\PaymentApi::ELAVON_LINK_MODE_HOSTED)" />
                            <small class="text-muted d-block mb-3">
                                {{ __('Hosted sends customers to Elavon. CheckoutJS keeps card fields on your branded payment page.') }}
                            </small>
                        @endif

                        <div class="form-group form-check mb-3">
                            <input type="hidden" name="is_subscription" value="0">
                            <input type="checkbox" class="form-check-input" id="is_subscription" name="is_subscription"
                                value="1" {{ old('is_subscription', $paymentApi->is_subscription) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_subscription">
                                {{ __('This is a subscription button') }}
                            </label>
                        </div>
                    </div>

                    @if ($isElavon)
                        <div class="tab-pane fade {{ $openCheckoutTab ? 'show active' : '' }}" id="checkout-page" role="tabpanel"
                            aria-labelledby="checkout-page-tab">
                            <div id="checkoutjs-appearance-fields" @style(['display: none' => ! $checkoutEnabled])>
                                @include('dashboard.partials.checkoutjs-appearance-fields', [
                                    'checkoutTheme' => $checkoutTheme,
                                    'appearance' => $appearance,
                                    'companyNamePlaceholder' => $paymentApi->paymentMethodAccess?->company_name,
                                ])
                            </div>
                            <div id="checkoutjs-appearance-disabled" class="text-muted" @style(['display: none' => $checkoutEnabled])>
                                {{ __('Select “Own CheckoutJS page” under Button settings to customize this page.') }}
                            </div>
                        </div>
                    @endif
                </div>

                <div class="mt-3">
                    <button class="btn btn-primary">{{ __('Submit') }}</button>
                </div>
            </form>
        </div>
    </div>

    @if ($isElavon)
        <script>
            (function () {
                var mode = document.getElementById('elavon_link_mode') || document.querySelector('[name="elavon_link_mode"]');
                var fields = document.getElementById('checkoutjs-appearance-fields');
                var disabled = document.getElementById('checkoutjs-appearance-disabled');

                function syncCheckoutAppearance() {
                    if (!mode || !fields || !disabled) {
                        return;
                    }
                    var enabled = mode.value === 'checkoutjs';
                    fields.style.display = enabled ? '' : 'none';
                    disabled.style.display = enabled ? 'none' : '';
                }

                if (mode) {
                    mode.addEventListener('change', syncCheckoutAppearance);
                    syncCheckoutAppearance();
                }
            })();
        </script>
    @endif
</x-dashboard.external>
