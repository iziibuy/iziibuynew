<div class="row">

    @php
        $editable = $editable ?? false;
        $showQuickpay = $editable || in_array('quickpay', explode(',', $shop->paymentMethod)) || $shop->fallback_payment_method == 'quickpay';
        $showElavon = $editable || in_array('elavon', explode(',', $shop->paymentMethod)) || $shop->fallback_payment_method == 'elavon';
        $showSurfboard = $editable || in_array('surfboard', explode(',', $shop->paymentMethod)) || $shop->fallback_payment_method == 'surfboard';
        $showTwo = $editable || in_array('two', explode(',', $shop->paymentMethod));
        $activePaymentMethods = array_filter(explode(',', (string) $shop->paymentMethod));
        $gatewayOptions = [
            'quickpay' => 'QuickPay',
            'elavon' => 'Elavon',
            'surfboard' => 'Surfboard',
            'two' => 'Two',
            'dintero' => 'Dintero',
        ];
    @endphp

    @if ($editable)
        <div class="col-md-12">
            <h6 class="text-secondary">{{ __('words.payment') }}</h6>
            <div class="d-flex border mb-3 rounded flex-wrap">
                @foreach ($gatewayOptions as $gateway => $label)
                    <div class="form-check m-2">
                        <input class="form-check-input" name="payment_method[]" type="checkbox"
                            @checked(in_array($gateway, $activePaymentMethods)) value="{{ $gateway }}"
                            id="payment-method-{{ $gateway }}">
                        <label class="form-check-label" for="payment-method-{{ $gateway }}">{{ $label }}</label>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="col-md-12">
        <x-form.input type="select" name="meta[site_mode]" label="{!! __('words.site_mode') !!}" :options="['live' => 'live', 'test' => 'test']"
            :value="$shop->site_mode" />
    </div>

    @if ($showQuickpay)
        <h4>Quickpay API Keys</h4>
        <hr>
        <div class="row">
            <div class="col-md-6">
                <x-form.input type="text" name="{{ $editable ? 'meta[quickpay_api_key]' : '' }}"
                    :readonly="! $editable" label="{!! __('words.shop_api_kay') !!}" :value="old('meta.quickpay_api_key', $shop->quickpay_api_key)" />
            </div>
            <div class="col-md-6">
                <x-form.input type="text" name="{{ $editable ? 'meta[quickpay_secret_key]' : '' }}"
                    :readonly="! $editable" label="{!! __('words.shop_secrate_key') !!}"
                    :value="old('meta.quickpay_secret_key', $shop->quickpay_secret_key)" />
            </div>
        </div>
    @endif

    @if ($showElavon)
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
    @endif

    @if ($showSurfboard)
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
    @endif

    @if ($showTwo)
        <h4>Two API Keys</h4>
        <hr>
        <div class="row">
            <div class="col-md-6">
                <x-form.input type="text" name="{{ $editable ? 'meta[two_api_key]' : '' }}"
                    :readonly="! $editable" label="Two API Key" :value="old('meta.two_api_key', $shop->two_api_key)" />
            </div>
            <div class="col-md-6">
                <x-form.input type="text" name="{{ $editable ? 'meta[two_secret_key]' : '' }}"
                    :readonly="! $editable" label="Two Secret Key" :value="old('meta.two_secret_key', $shop->two_secret_key)" />
            </div>
        </div>
    @endif

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
            <select name="meta[fallback_payment_method]" class="form-control" id="">
                <option value="">{{ __('words.choose_a_fallback_method') }}</option>
                <option @if ($shop->fallback_payment_method == 'surfboard') selected @endif value="surfboard">Surfboard</option>
                <option @if ($shop->fallback_payment_method == 'elavon') selected @endif value="elavon">Elavon</option>
                <option @if ($shop->fallback_payment_method == 'quickpay') selected @endif value="quickpay">Quickpay</option>
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
                    <input class="form-check-input" name="meta[footerPaymentMethod][]" type="checkbox"
                        @if (in_array($item->id, $methodArray)) checked @endif value="{{ $item->id }}"
                        id="method{{ $item->id }}" />
                    <label class="form-check-label" for="method{{ $item->id }}"> {{ $item->name }} </label>
                </div>
            @endforeach
        </div>
    </div>

</div>
