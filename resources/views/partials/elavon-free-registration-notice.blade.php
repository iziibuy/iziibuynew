@if (\App\Services\Elavon\ElavonOnboardingPromo::isFreeSubscriptionPeriod())
    <div class="alert alert-success" role="alert">
        Abonnementet er gratis frem til 19. juli 2026. Du trenger bare å registrere betalingskortet ditt. Det påløper
        ingen kostnader under registreringen. Månedlig fakturering starter fra august 2026.
    </div>
@endif
