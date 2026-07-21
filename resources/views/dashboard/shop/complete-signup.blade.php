<x-dashboard.shop>

    <h3><span class="text-primary opacity-25">{!! __('words.contrtact_sec_title') !!} </span> {{ __('words.dashboard') }}</h3>
    <div class="row mt-3">
        @if (auth()->user()->shop->contract_signed == 0)
            <div class="col-lg-12">
                <div class="card ">
                    <div class="card-header">
                        {!! __('words.contract_sec_pera') !!}
                    </div>
                    <div class="card-body">
                        <p class="bg-success p-2 text-light">
                            {!! __('words.contract_massage_sec_subtitle') !!}
                        </p>
                        <h3>{!! __('words.contarct_msg_sec_title') !!}</h3>
                        <p>{!! __('words.contract_msg_sec_pera') !!}
                            {{ __('words.contract_msg_sec_pera_3') }}
                        </p>


                        <form action="{{ route('shop.post.complete.signup') }}" method="post">
                            @csrf

                            @php
                                $paymentMethods = [
                                    'visa' => 'Visa',
                                    'mastercard' => 'Mastercard',
                                    'amex' => 'Amex',
                                    'vipps' => 'Vipps',
                                    'googlepay' => 'Google Pay',
                                    'applepay' => 'Apple Pay',
                                ];
                            @endphp

                            <div class="form-group">
                                @foreach ($paymentMethods as $key => $value)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="paymentMethods[]"
                                            value="{{ $key }}" id="{{ $key }}">
                                        <label class="form-check-label" for="{{ $key }}">
                                            {{ $value }}
                                        </label>
                                    </div>
                                @endforeach

                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-success">{!! __('words.contract_order_btn') !!}</button>
                            </div>
                            <div class="form-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="terms" value="terms"
                                        id="terms" checked>
                                    <label class="form-check-label" for="terms">
                                        {!! __('words.contract_terms') !!}
                                        <a href="https://iziibuy.com/page/betingelser">{{ __('words.betingelser') }}</a>
                                    </label>
                                </div>
                                <p>{!! __('words.contract_footer') !!} </p>
                            </div>

                        </form>

                    </div>
                </div>
            </div>
        @else
            <p>
            <h1>{!! __('words.contract_has_panding') !!} </h1>
            </p>
            <p>{{ __('words.contract_has_panding_pera_2') }} </p>

            <div class="row g-3">
                @foreach (auth()->user()->shop->gateway_contract_signed ? json_decode(auth()->user()->shop->gateway_contract_signed) : [] as $gateway => $status)
                    <div class="col-12">
                        @include("dashboard.shop.contract.$gateway", ['status' => $status])
                    </div>
                @endforeach
            </div>


        @endif
    </div>
</x-dashboard.shop>
