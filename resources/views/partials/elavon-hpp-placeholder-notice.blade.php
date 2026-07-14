@if (\App\Services\Elavon\ElavonOnboardingPromo::shouldShowHppPlaceholderNotice($signupAmountNok ?? 0))
    <div class="alert alert-info" role="alert">
        <strong>Merknad om Elavons betalingsside:</strong>
        Du kan se
        {{ number_format(\App\Services\Elavon\ElavonOnboardingPromo::HPP_PLACEHOLDER_ORDER_AMOUNT, 0) }}
        NOK vist på Elavons sikre betalingsside. Dette er en teknisk plassholder som kreves av betalingsgatewayen for
        kortverifisering. Du vil ikke bli belastet i løpet av den gratis registreringsperioden.
    </div>
@endif
