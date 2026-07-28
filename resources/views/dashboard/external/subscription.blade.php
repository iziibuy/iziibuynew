<x-dashboard.external>
    <div class="container">
        <div class="row justify-content-center my-5">
            <div class="col-12 col-md-8 col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">{{ __('words.subscription_sec_title') }}</h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            @if ($paymentMethodAccess->needs_elavon_resubscription)
                                @include('partials.elavon-onboarding-message')
                            @endif

                            @include('partials.elavon-free-registration-notice')

                            @include('partials.elavon-hpp-placeholder-notice', [
                                'signupAmountNok' => $paymentMethodAccess->fee(),
                            ])

                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul>
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <p>
                                <b>{{ __('words.subscription_pera_1') }}</b>
                                <br>
                                @if (\App\Services\Elavon\ElavonOnboardingPromo::isFreeSubscriptionPeriod())
                                    {{ __('words.total') }}: {{ __('Free') }}
                                @else
                                    {{ __('words.total') }}: {{ Iziibuy::price($paymentMethodAccess->fee()) }}
                                @endif
                            </p>

                            <div class="text-center">
                                @if (filled($subscription->key) || filled($paymentMethodAccess->shopperId))
                                    <a href="{{ route('external.start-subscription', [$subscription, 'type' => 'new_card']) }}"
                                        class="btn btn-primary">{{ __('words.subscribe_with_new_card') }}</a>
                                @else
                                    <a href="{{ route('external.start-subscription', $subscription) }}"
                                        class="btn btn-primary">{{ __('words.start_running_subs_btn') }}</a>
                                @endif
                                <a class="btn btn-danger" href="{{ route('logout') }}"
                                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    {{ __('words.shop_logout') }}
                                </a>

                                <form id="logout-form" action="{{ route('logout') }}" method="POST"
                                    style="display: none;">
                                    @csrf
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-dashboard.external>
