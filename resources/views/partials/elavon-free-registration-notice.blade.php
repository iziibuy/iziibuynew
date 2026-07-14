@if (\App\Services\Elavon\ElavonOnboardingPromo::isFreeSubscriptionPeriod())
    <div class="alert alert-success" role="alert">
        Subscription is free until 19 July 2026. You only need to register your payment card. No charge will be made
        during registration. Monthly billing starts from August 2026.
    </div>
@endif
