<div class="row">

    <div class="col-md-12">
        <x-form.input type="select" name="meta[site_mode]" label="{!! __('words.site_mode') !!}" :options="['live' => 'live', 'test' => 'test']"
            :value="$shop->site_mode" />
    </div>


    @if (in_array('quickpay', explode(',', $shop->paymentMethod)) || $shop->fallback_payment_method == 'quickpay')
        <h4>Quickpay API Keys</h4>
        <hr>
        <div class="row">
            <div class="col-md-6">
                <x-form.input type="text" name="" readonly label="{!! __('words.shop_api_kay') !!}" :value="old('api_key', $shop->quickpay_api_key)" />
            </div>
            <div class="col-md-6">
                <x-form.input type="text" name="" readonly label="{!! __('words.shop_secrate_key') !!}"
                    :value="old('secret_key', $shop->quickpay_secret_key)" />
            </div>
        </div>
    @endif
    @if (in_array('elavon', explode(',', $shop->paymentMethod)) || $shop->fallback_payment_method == 'elavon')
        <h4>Elavon API Keys</h4>
        <hr>
        <div class="row">
            @foreach (['elavon_merchant_alias', 'elavon_public_key', 'elavon_secret_key'] as $field)
                <div class="col-md-4">
                    <x-form.input type="text" name="" readonly label="{{ __('words.' . $field) }}"
                        value="{{ $shop->$field }}" />
                </div>
            @endforeach
        </div>
    @endif
    @if (in_array('surfboard', explode(',', $shop->paymentMethod)) || $shop->fallback_payment_method == 'surfboard')
        <h4>Surfboard API Keys</h4>
        <hr>
        <div class="row">
            @foreach (['surfboard_terminalId', 'surfboard_merchantId', 'surfboard_storeId'] as $field)
                <div class="col-md-4">
                    <x-form.input type="text" name="" readonly label="{{ __('words.' . $field) }}"
                        value="{{ $shop->$field }}" />
                </div>
            @endforeach
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
