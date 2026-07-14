@if (\App\Services\Elavon\ElavonOnboardingPromo::shouldShowHppPlaceholderNotice($signupAmountNok ?? 0))
    <div class="alert alert-info" role="alert">
        <strong>Note about Elavon's payment page:</strong>
        You may see
        {{ number_format(\App\Services\Elavon\ElavonOnboardingPromo::HPP_PLACEHOLDER_ORDER_AMOUNT, 0) }}
        NOK displayed on Elavon's secure payment page. This is a technical placeholder required by the payment gateway
        for card verification. You will <strong>not</strong> be charged during the free registration period.
    </div>
@endif
