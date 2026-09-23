<x-dashboard.external>
    <div class="card">
        <div class="card-body">
            <h3 class="mb-3">{{ __('Edit button') }}</h3>
            <form action="{{ route('external.buttonPayment.update', $paymentApi) }}" method="post" enctype="multipart/form-data">
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

                @if ($paymentApi->usesElavonCheckoutJs())
                    @php
                        $checkoutTheme = $paymentApi->checkoutJsTheme();
                        $checkoutColors = [
                            'checkout_primary_color' => ['label' => __('Primary color'), 'help' => __('Panel and pay button'), 'value' => old('checkout_primary_color', $checkoutTheme->primary())],
                            'checkout_secondary_color' => ['label' => __('Accent color'), 'help' => __('Highlights'), 'value' => old('checkout_secondary_color', $checkoutTheme->secondary())],
                            'checkout_background_color' => ['label' => __('Background'), 'help' => __('Page background'), 'value' => old('checkout_background_color', $checkoutTheme->background())],
                        ];
                    @endphp
                    <hr>
                    <h4 class="mb-1">{{ __('Checkout page') }}</h4>
                    <p class="text-muted mb-3">{{ __('These settings are shown to customers on the CheckoutJS payment page. Leave the name, logo, or footer empty to keep the default.') }}</p>

                    <x-form.input type="text" name="checkout_company_name" label="Company name"
                        value="{{ old('checkout_company_name', $paymentApi->checkoutjs_appearance['company_name'] ?? '') }}"
                        placeholder="{{ $paymentApi->paymentMethodAccess?->company_name }}" />

                    <div class="mb-3">
                        <label class="form-label" for="checkout_logo">{{ __('Logo') }}</label>
                        @if ($checkoutTheme->hasCustomLogo())
                            <div class="mb-2">
                                <img src="{{ $checkoutTheme->logoUrl() }}" alt="" style="height:48px;width:auto;border-radius:8px;border:1px solid #e3e6e1;">
                            </div>
                            <div class="form-check mb-2">
                                <input type="checkbox" class="form-check-input" id="checkout_remove_logo" name="checkout_remove_logo" value="1">
                                <label class="form-check-label" for="checkout_remove_logo">{{ __('Remove logo') }}</label>
                            </div>
                        @endif
                        <input type="file" class="form-control @error('checkout_logo') is-invalid @enderror" id="checkout_logo" name="checkout_logo" accept="image/png,image/jpeg,image/webp">
                        <small class="text-muted">{{ __('PNG, JPG, or WebP. Up to 2 MB.') }}</small>
                        @error('checkout_logo')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        @foreach ($checkoutColors as $colorName => $color)
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label" for="{{ $colorName }}">{{ $color['label'] }}</label>
                                    <div class="d-flex align-items-center" style="gap:0.5rem;">
                                        <input type="color" class="form-control form-control-color" value="{{ $color['value'] }}" data-color-sync="{{ $colorName }}" aria-label="{{ $color['label'] }}">
                                        <input type="text" class="form-control @error($colorName) is-invalid @enderror" id="{{ $colorName }}" name="{{ $colorName }}" value="{{ $color['value'] }}" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$" spellcheck="false">
                                    </div>
                                    <small class="text-muted">{{ $color['help'] }}</small>
                                    @error($colorName)
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="checkout_footer">{{ __('Footer') }}</label>
                        <textarea class="form-control @error('checkout_footer') is-invalid @enderror" id="checkout_footer" name="checkout_footer" rows="2" maxlength="300">{{ old('checkout_footer', $checkoutTheme->footer()) }}</textarea>
                        <small class="text-muted">{{ __('Short note under the payment form.') }}</small>
                        @error('checkout_footer')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <details class="mb-3">
                        <summary class="mb-2">{{ __('Custom CSS') }}</summary>
                        <textarea class="form-control @error('checkout_custom_css') is-invalid @enderror" id="checkout_custom_css" name="checkout_custom_css" rows="6" maxlength="8000" spellcheck="false" placeholder=".footnote { letter-spacing: 0.02em; }">{{ old('checkout_custom_css', $checkoutTheme->customCss()) }}</textarea>
                        <small class="text-muted">{{ __('Optional. Added after the page styles. HTML is not allowed.') }}</small>
                        @error('checkout_custom_css')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </details>
                    <script>
                        document.querySelectorAll('[data-color-sync]').forEach(function (picker) {
                            var field = document.getElementById(picker.getAttribute('data-color-sync'));
                            if (!field) {
                                return;
                            }
                            picker.addEventListener('input', function () {
                                field.value = picker.value.toUpperCase();
                            });
                            field.addEventListener('input', function () {
                                if (/^#[0-9A-Fa-f]{6}$/.test(field.value)) {
                                    picker.value = field.value;
                                }
                            });
                        });
                    </script>
                @endif

                <button class="btn btn-primary">{{ __('Submit') }}</button>
            </form>
        </div>
    </div>
</x-dashboard.external>
