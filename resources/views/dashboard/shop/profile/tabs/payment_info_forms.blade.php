<div class="row">

    @php
        $editable = $editable ?? false;
        $catalog = app(\App\Services\Checkout\CheckoutPaymentOptionCatalog::class);
        $checkoutOptions = $catalog->active();
        $acquirerLabels = $catalog->acquirerLabels();
        $shopCheckoutConfig = $shop->checkoutPaymentOptionsConfig();
        $selectedAcquirers = $shop->selectedCheckoutAcquirers();
        if ($selectedAcquirers === [] && filled($shop->paymentMethod)) {
            $selectedAcquirers = array_values(array_filter(explode(',', (string) $shop->paymentMethod)));
        }
        if (filled($shop->fallback_payment_method)) {
            $selectedAcquirers[] = (string) $shop->fallback_payment_method;
            $selectedAcquirers = array_values(array_unique($selectedAcquirers));
        }
        $showQuickpay = in_array('quickpay', $selectedAcquirers, true);
        $showElavon = in_array('elavon', $selectedAcquirers, true);
        $showSurfboard = in_array('surfboard', $selectedAcquirers, true);
        $showTwo = in_array('two', $selectedAcquirers, true) || in_array('two', explode(',', (string) $shop->paymentMethod), true);
        $showDintero = in_array('dintero', $selectedAcquirers, true);
        $paymentOptionIcons = [
            'visa' => asset('images/payment/visa.png'),
            'mastercard' => asset('images/payment/mastercard.png'),
            'amex' => asset('images/payment/amex.png'),
            'googlepay' => asset('images/payment/googlepay.png'),
            'applepay' => asset('images/payment/applepay.png'),
            'klarna' => asset('images/payment/klarna.jpg'),
            'vipps' => asset('images/payment/vipps.png'),
            'swish' => asset('images/payment/swish.png'),
        ];
    @endphp

    <div class="col-md-12">
        <h6 class="text-secondary">{{ __('words.payment') }}</h6>
        <p class="text-muted small mb-2">
            {{ __('Select which payment options this shop offers and which acquirer processes each one.') }}
        </p>
        <div class="checkout-payment-options">
            @foreach ($checkoutOptions as $optionKey => $option)
                @php
                    $row = $shopCheckoutConfig[$optionKey] ?? ['enabled' => false, 'acquirer' => $option['acquirers'][0] ?? null];
                    $isEnabled = (bool) ($row['enabled'] ?? false);
                    $selectedAcquirer = $row['acquirer'] ?? ($option['acquirers'][0] ?? null);
                    $icon = $paymentOptionIcons[$option['icon'] ?? $optionKey] ?? null;
                @endphp
                <div class="checkout-payment-option-row">
                    <div class="form-check m-0">
                        <input class="form-check-input checkout-option-enabled" type="checkbox"
                            name="checkout_payment_options[{{ $optionKey }}][enabled]" value="1"
                            id="checkout-option-{{ $optionKey }}"
                            data-option="{{ $optionKey }}"
                            @checked($isEnabled)
                            @disabled(! $editable)>
                        <label class="form-check-label" for="checkout-option-{{ $optionKey }}">
                            @if ($icon)
                                <img class="checkout-payment-option-icon" src="{{ $icon }}"
                                    alt="{{ $option['label'] }}" width="40" height="20"
                                    style="height:20px;width:auto;max-width:40px;max-height:20px;object-fit:contain;"
                                    onerror="this.style.display='none'">
                            @endif
                            <span>{{ $option['label'] }}</span>
                        </label>
                    </div>
                    <div class="checkout-payment-option-acquirer">
                        @if ($editable)
                            <select class="form-control checkout-option-acquirer"
                                name="checkout_payment_options[{{ $optionKey }}][acquirer]"
                                data-option="{{ $optionKey }}"
                                @disabled(! $isEnabled)>
                                @foreach ($option['acquirers'] as $acquirer)
                                    <option value="{{ $acquirer }}" @selected($selectedAcquirer === $acquirer)>
                                        {{ $acquirerLabels[$acquirer] ?? ucfirst($acquirer) }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <input type="hidden" name="checkout_payment_options[{{ $optionKey }}][acquirer]"
                                value="{{ $selectedAcquirer }}">
                            <div class="form-control bg-light">
                                {{ $acquirerLabels[$selectedAcquirer] ?? ($selectedAcquirer ?: '—') }}
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="col-md-12">
        <x-form.input type="select" name="meta[site_mode]"
            label="{!! __('words.site_mode') !!}" :options="['live' => 'live', 'test' => 'test']"
            :value="$shop->site_mode" />
    </div>

    <div class="col-md-12 acquirer-credentials" data-acquirer="quickpay" @style([
        'display: none' => ! $showQuickpay,
    ])>
        <h4>Quickpay API Keys</h4>
        <hr>
        <div class="row">
            <div class="col-md-6">
                <x-form.input type="text" name="{{ $editable ? 'meta[quickpay_api_key]' : '' }}"
                    :readonly="! $editable" label="{!! __('words.shop_api_kay') !!}"
                    :value="old('meta.quickpay_api_key', $shop->quickpay_api_key)" />
            </div>
            <div class="col-md-6">
                <x-form.input type="text" name="{{ $editable ? 'meta[quickpay_secret_key]' : '' }}"
                    :readonly="! $editable" label="{!! __('words.shop_secrate_key') !!}"
                    :value="old('meta.quickpay_secret_key', $shop->quickpay_secret_key)" />
            </div>
        </div>
    </div>

    <div class="col-md-12 acquirer-credentials" data-acquirer="elavon" @style([
        'display: none' => ! $showElavon,
    ])>
        <h4>Elavon API Keys</h4>
        <hr>
        <div class="row">
            @foreach (['elavon_merchant_alias', 'elavon_public_key', 'elavon_secret_key'] as $field)
                <div class="col-md-4">
                    <x-form.input type="text" name="{{ $editable ? 'meta['.$field.']' : '' }}"
                        :readonly="! $editable" label="{{ __('words.' . $field) }}"
                        :value="old('meta.'.$field, $shop->$field)" />
                </div>
            @endforeach
        </div>
    </div>

    <div class="col-md-12 acquirer-credentials" data-acquirer="surfboard" @style([
        'display: none' => ! $showSurfboard,
    ])>
        <h4>Surfboard API Keys</h4>
        <hr>
        <div class="row">
            @foreach (['surfboard_terminalId', 'surfboard_merchantId', 'surfboard_storeId'] as $field)
                <div class="col-md-4">
                    <x-form.input type="text" name="{{ $editable ? 'meta['.$field.']' : '' }}"
                        :readonly="! $editable" label="{{ __('words.' . $field) }}"
                        :value="old('meta.'.$field, $shop->$field)" />
                </div>
            @endforeach
        </div>
    </div>

    <div class="col-md-12 acquirer-credentials" data-acquirer="dintero" @style([
        'display: none' => ! $showDintero,
    ])>
        <h4>Dintero</h4>
        <hr>
        <div class="row">
            <div class="col-md-6">
                <x-form.input type="text" name="{{ $editable ? 'meta[dintero_account_id]' : '' }}"
                    :readonly="! $editable" label="{{ __('Dintero account id') }}"
                    :value="old('meta.dintero_account_id', $shop->dintero_account_id)" />
            </div>
            <div class="col-md-6">
                <x-form.input type="text" name="{{ $editable ? 'meta[dintero_onboarding_status]' : '' }}"
                    :readonly="! $editable" label="{{ __('Dintero onboarding status') }}"
                    :value="old('meta.dintero_onboarding_status', $shop->dintero_onboarding_status)" />
            </div>
        </div>
    </div>

    <div class="col-md-12 acquirer-credentials" data-acquirer="two" @style([
        'display: none' => ! $showTwo,
    ])>
        <h4>Two API Keys</h4>
        <hr>
        <div class="row">
            <div class="col-md-6">
                <x-form.input type="text" name="{{ $editable ? 'meta[two_api_key]' : '' }}"
                    :readonly="! $editable" label="Two API Key" :value="old('meta.two_api_key', $shop->two_api_key)" />
            </div>
            <div class="col-md-6">
                <x-form.input type="text" name="{{ $editable ? 'meta[two_secret_key]' : '' }}"
                    :readonly="! $editable" label="Two Secret Key"
                    :value="old('meta.two_secret_key', $shop->two_secret_key)" />
            </div>
        </div>
    </div>

    <div class="col-md-12 ">
        <h6 class="text-secondary">{!! __('words.shop_default_currency') !!}</h6>
        <div class="d-flex border mb-3 rounded">

            @foreach (App\Constants\Constants::ADDITIONAL_CURRENCIES as $currency)
                <div class="form-check m-2">

                    <input class="form-check-input" @if ($shop->checkDefaultCurrency($currency)) checked="true" @endif
                        name="default_currency" type="radio" id="default_currency_{{ $currency }}"
                        value="{{ $currency }}">
                    <label class="form-check-label" for="default_currency_{{ $currency }}">
                        {{ $currency }}
                    </label>
                </div>
            @endforeach
        </div>

    </div>
    <div class="col-12">
        <div class="form-group">
            <label for="fallback_payment_method">{{ __('words.fallback_payment_method') }}</label>
            <select name="meta[fallback_payment_method]" class="form-control"
                id="fallback_payment_method">
                <option value="">{{ __('words.choose_a_fallback_method') }}</option>
                <option @if ($shop->fallback_payment_method == 'surfboard') selected @endif value="surfboard">Surfboard</option>
                <option @if ($shop->fallback_payment_method == 'elavon') selected @endif value="elavon">Elavon</option>
                <option @if ($shop->fallback_payment_method == 'quickpay') selected @endif value="quickpay">Quickpay</option>
                <option @if ($shop->fallback_payment_method == 'dintero') selected @endif value="dintero">Dintero</option>
            </select>
        </div>
    </div>
    <div class="col-md-12 ">
        <h6 class="text-secondary">{!! __('words.shop_currencies') !!}</h6>
        <div class="d-flex border mb-3 rounded">
            @foreach (App\Constants\Constants::ADDITIONAL_CURRENCIES as $currency)
                <div class="form-check m-2">
                    <input class="form-check-input" @if ($shop->checkCurrency($currency)) checked="true" @endif
                        name="currencies[]" type="checkbox" id="{{ $currency }}-currency"
                        value="{{ $currency }}">
                    <label class="form-check-label" for="{{ $currency }}-currency">
                        {{ $currency }}
                    </label>
                </div>
            @endforeach
        </div>

    </div>
    <div class="col-md-12">
        @php
            $paymentMethods = App\Models\PaymentMethod::all();
        @endphp
        <h5>
            {{ __('words.footer_payment_methods') }}
        </h5>
        @php
            $methodArray = $shop->footerPaymentMethod ? json_decode($shop->footerPaymentMethod) : [];
        @endphp
        <div class="d-flex border mb-3 rounded flex-wrap">
            @foreach ($paymentMethods as $item)
                <div class="form-check m-2">
                    <input class="form-check-input" name="meta[footerPaymentMethod][]"
                        type="checkbox" @if (in_array($item->id, $methodArray)) checked @endif value="{{ $item->id }}"
                        id="method{{ $item->id }}" />
                    <label class="form-check-label" for="method{{ $item->id }}"> {{ $item->name }} </label>
                </div>
            @endforeach
        </div>
    </div>

    @if ($editable)
        <script>
            (function() {
                function syncCheckoutPaymentUi() {
                    const used = new Set();

                    document.querySelectorAll('.checkout-option-enabled').forEach((checkbox) => {
                        const option = checkbox.dataset.option;
                        const select = document.querySelector(
                            '.checkout-option-acquirer[data-option="' + option + '"]'
                        );
                        if (select) {
                            select.disabled = !checkbox.checked;
                            if (checkbox.checked && select.value) {
                                used.add(select.value);
                            }
                        }
                    });

                    const fallback = document.getElementById('fallback_payment_method');
                    if (fallback && fallback.value) {
                        used.add(fallback.value);
                    }

                    document.querySelectorAll('.acquirer-credentials').forEach((block) => {
                        const acquirer = block.dataset.acquirer;
                        block.style.display = used.has(acquirer) ? '' : 'none';
                    });
                }

                document.querySelectorAll('.checkout-option-enabled, .checkout-option-acquirer').forEach((el) => {
                    el.addEventListener('change', syncCheckoutPaymentUi);
                });

                const fallback = document.getElementById('fallback_payment_method');
                if (fallback) {
                    fallback.addEventListener('change', syncCheckoutPaymentUi);
                }

                syncCheckoutPaymentUi();
            })();
        </script>
    @endif

</div>
