<x-dashboard.external>
    @php
        $checkoutEnabled = $paymentApi->usesElavonCheckoutJs();
        $checkoutTheme = $checkoutEnabled ? $paymentApi->checkoutJsTheme() : null;
        $openCheckoutTab = $checkoutEnabled && collect([
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
        $checkoutColors = $checkoutTheme ? [
            'checkout_primary_color' => ['label' => __('Primary color'), 'help' => __('Panel and pay button'), 'value' => old('checkout_primary_color', $checkoutTheme->primary())],
            'checkout_secondary_color' => ['label' => __('Accent color'), 'help' => __('Highlights'), 'value' => old('checkout_secondary_color', $checkoutTheme->secondary())],
            'checkout_background_color' => ['label' => __('Background'), 'help' => __('Page background'), 'value' => old('checkout_background_color', $checkoutTheme->background())],
        ] : [];
        $appearance = $paymentApi->checkoutjs_appearance ?? [];
    @endphp

    <div class="card">
        <div class="card-body">
            <h3 class="mb-3">{{ __('Edit button') }}</h3>

            @if ($checkoutEnabled)
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
                            {{ __('Checkout page') }}
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

                        <div class="form-group form-check mb-3">
                            <input type="hidden" name="is_subscription" value="0">
                            <input type="checkbox" class="form-check-input" id="is_subscription" name="is_subscription"
                                value="1" {{ old('is_subscription', $paymentApi->is_subscription) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_subscription">
                                {{ __('This is a subscription button') }}
                            </label>
                        </div>
                    </div>

                    @if ($checkoutEnabled && $checkoutTheme)
                        <div class="tab-pane fade {{ $openCheckoutTab ? 'show active' : '' }}" id="checkout-page" role="tabpanel"
                            aria-labelledby="checkout-page-tab">
                            <p class="text-muted mb-3">
                                {{ __('Customize the CheckoutJS payment page customers see. Leave a field empty to keep the default text.') }}
                            </p>

                            <h5 class="mb-2">{{ __('Branding') }}</h5>
                            <x-form.input type="text" name="checkout_company_name" label="Company name"
                                value="{{ old('checkout_company_name', $appearance['company_name'] ?? '') }}"
                                placeholder="{{ $paymentApi->paymentMethodAccess?->company_name }}" />

                            <div class="mb-3">
                                <label class="form-label" for="checkout_logo">{{ __('Logo') }}</label>
                                @if ($checkoutTheme->hasCustomLogo())
                                    <div class="mb-2">
                                        <img src="{{ $checkoutTheme->logoUrl() }}" alt=""
                                            style="height:48px;width:auto;border-radius:8px;border:1px solid #e3e6e1;">
                                    </div>
                                    <div class="form-check mb-2">
                                        <input type="checkbox" class="form-check-input" id="checkout_remove_logo"
                                            name="checkout_remove_logo" value="1">
                                        <label class="form-check-label" for="checkout_remove_logo">{{ __('Remove logo') }}</label>
                                    </div>
                                @endif
                                <input type="file" class="form-control @error('checkout_logo') is-invalid @enderror"
                                    id="checkout_logo" name="checkout_logo" accept="image/png,image/jpeg,image/webp">
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
                                                <input type="color" class="form-control form-control-color"
                                                    value="{{ $color['value'] }}" data-color-sync="{{ $colorName }}"
                                                    aria-label="{{ $color['label'] }}">
                                                <input type="text"
                                                    class="form-control @error($colorName) is-invalid @enderror"
                                                    id="{{ $colorName }}" name="{{ $colorName }}"
                                                    value="{{ $color['value'] }}" maxlength="7"
                                                    pattern="^#[0-9A-Fa-f]{6}$" spellcheck="false">
                                            </div>
                                            <small class="text-muted">{{ $color['help'] }}</small>
                                            @error($colorName)
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <hr>
                            <h5 class="mb-2">{{ __('Page text') }}</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <x-form.input type="text" name="checkout_heading" label="Form heading"
                                        value="{{ old('checkout_heading', $appearance['heading'] ?? '') }}"
                                        placeholder="{{ __('Card details') }}" />
                                </div>
                                <div class="col-md-6">
                                    <x-form.input type="text" name="checkout_pay_button_label" label="Pay button label"
                                        value="{{ old('checkout_pay_button_label', $appearance['pay_button_label'] ?? '') }}"
                                        placeholder="{{ __('Pay') }}" />
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="checkout_lead">{{ __('Form description') }}</label>
                                <textarea class="form-control @error('checkout_lead') is-invalid @enderror" id="checkout_lead"
                                    name="checkout_lead" rows="2" maxlength="400">{{ old('checkout_lead', $appearance['lead'] ?? '') }}</textarea>
                                <small class="text-muted">{{ __('Shown under the form heading.') }}</small>
                                @error('checkout_lead')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <x-form.input type="text" name="checkout_cancel_label" label="Cancel link text"
                                        value="{{ old('checkout_cancel_label', $appearance['cancel_label'] ?? '') }}"
                                        placeholder="{{ __('Cancel payment') }}" />
                                </div>
                                <div class="col-md-6">
                                    <x-form.input type="text" name="checkout_summary_note" label="Summary note"
                                        value="{{ old('checkout_summary_note', $appearance['summary_note'] ?? '') }}"
                                        placeholder="{{ __('Card details stay with Elavon Hosted Fields.') }}" />
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="checkout_footer">{{ __('Footer') }}</label>
                                <textarea class="form-control @error('checkout_footer') is-invalid @enderror" id="checkout_footer"
                                    name="checkout_footer" rows="2" maxlength="300">{{ old('checkout_footer', $checkoutTheme->footer()) }}</textarea>
                                <small class="text-muted">{{ __('Short note under the payment form.') }}</small>
                                @error('checkout_footer')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <hr>
                            <h5 class="mb-2">{{ __('Custom CSS') }}</h5>
                            <p class="text-muted mb-2">
                                {{ __('Optional styles for the whole checkout page, not only the footer. HTML and JavaScript are not allowed.') }}
                            </p>
                            <div class="mb-2">
                                <small class="text-muted d-block mb-1">{{ __('Useful selectors') }}</small>
                                <div class="d-flex flex-wrap" style="gap:0.4rem;">
                                    @foreach (\App\Payment\Elavon\CheckoutJsTheme::cssSelectorHints() as $selector => $hint)
                                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                            data-css-snippet="{{ $selector }} { /* {{ $hint }} */ }"
                                            title="{{ $hint }}">
                                            {{ explode(' / ', $selector)[0] }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                            <textarea class="form-control font-monospace @error('checkout_custom_css') is-invalid @enderror"
                                id="checkout_custom_css" name="checkout_custom_css" rows="12" maxlength="20000"
                                spellcheck="false"
                                placeholder=".btn-primary { border-radius: 8px; }&#10;.summary { letter-spacing: 0.01em; }&#10;.footnote { opacity: 0.85; }">{{ old('checkout_custom_css', $checkoutTheme->customCss()) }}</textarea>
                            <small class="text-muted">{{ __('Up to :max characters. Click a selector above to insert a starter rule.', ['max' => number_format(20000)]) }}</small>
                            @error('checkout_custom_css')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif
                </div>

                <div class="mt-3">
                    <button class="btn btn-primary">{{ __('Submit') }}</button>
                </div>
            </form>
        </div>
    </div>

    @if ($checkoutEnabled)
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

            document.querySelectorAll('[data-css-snippet]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var area = document.getElementById('checkout_custom_css');
                    if (!area) {
                        return;
                    }
                    var snippet = button.getAttribute('data-css-snippet') || '';
                    area.value = area.value
                        ? area.value.replace(/\s*$/, '') + '\n\n' + snippet
                        : snippet;
                    area.focus();
                    area.setSelectionRange(area.value.length, area.value.length);
                });
            });
        </script>
    @endif
</x-dashboard.external>
