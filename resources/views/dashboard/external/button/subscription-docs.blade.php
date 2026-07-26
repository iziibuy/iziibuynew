<x-dashboard.external>
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
        <div>
            <a href="{{ route('external.buttonPayment.view', $paymentApi) }}" class="text-decoration-none text-muted small">
                <i class="fas fa-arrow-left me-1"></i> {{ __('Back to button') }}
            </a>
            <h3 class="mb-0 mt-1">{{ __('Subscription integration docs') }}</h3>
            <p class="text-muted mb-0">{{ $paymentApi->domain }}</p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="text-uppercase small text-muted fw-semibold mb-1">{{ __('Plugin key') }}</div>
                    <code class="user-select-all">{{ $paymentMethodAccess->key }}</code>
                </div>
                <div class="col-md-6">
                    <div class="text-uppercase small text-muted fw-semibold mb-1">{{ __('Source key') }}</div>
                    <code class="user-select-all">{{ $paymentApi->key }}</code>
                </div>
            </div>
            <p class="mb-0 mt-3 text-muted">
                {{ __('Creates a subscription: customer pays the first period now, card is saved, then renewals are charged every interval_days via artisan command.') }}
            </p>
            <p class="mb-0 mt-2">
                <strong>{{ __('Elavon:') }}</strong> {{ __('vaulted card + MIT renewals.') }}
                <strong>{{ __('Surfboard:') }}</strong> {{ __('tokenized first payment + MIT renewals (requires surfboard_mit_terminalId).') }}
            </p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5>{{ __('Create subscription') }}</h5>
            <pre class="bg-light border rounded p-3"><code>POST {{ route('iziipay.createSubscription', $paymentMethodAccess->key) }}</code></pre>

            <h6 class="mt-3">{{ __('Body parameters') }}</h6>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ __('Parameter') }}</th>
                            <th>{{ __('Required') }}</th>
                            <th>{{ __('Description') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>source_key</td><td>Yes</td><td>This subscription button key</td></tr>
                        <tr><td>name</td><td>Yes</td><td>Customer name</td></tr>
                        <tr><td>email</td><td>Yes</td><td>Customer email</td></tr>
                        <tr><td>amount</td><td>Yes</td><td>Charge amount each period</td></tr>
                        <tr><td>currency</td><td>Yes</td><td>e.g. NOK</td></tr>
                        <tr><td>interval_days</td><td>Yes</td><td>Days between charges (min 1)</td></tr>
                        <tr><td>phone, country, address, post_code</td><td>No</td><td>Customer details</td></tr>
                        <tr><td>orderId, description, taxValue, taxTotal</td><td>No</td><td>Merchant metadata</td></tr>
                        <tr><td>preferred_acquirer</td><td>No</td><td>elavon or surfboard</td></tr>
                    </tbody>
                </table>
            </div>

            <h6 class="mt-3">{{ __('Success response') }}</h6>
            <pre class="bg-light border rounded p-3"><code>{
  "url": "https://hpp.../session...",
  "subscription": { "id": 1, "status": "PENDING", ... }
}</code></pre>

            <h6 class="mt-3">{{ __('cURL example') }}</h6>
            <pre class="bg-light border rounded p-3 mb-0"><code>curl -X POST {{ route('iziipay.createSubscription', $paymentMethodAccess->key) }} \
-H "Content-Type: application/json" \
-d '{
  "source_key": "{{ $paymentApi->key }}",
  "name": "Jane Doe",
  "email": "jane@example.com",
  "amount": 199,
  "currency": "NOK",
  "interval_days": 30,
  "description": "Monthly membership",
  "orderId": "plan-1"
}'</code></pre>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5>{{ __('Cancel subscription') }}</h5>
            <pre class="bg-light border rounded p-3"><code>POST {{ route('iziipay.cancelSubscription', $paymentMethodAccess->key) }}</code></pre>
            <pre class="bg-light border rounded p-3 mb-0"><code>{ "subscription_id": 123 }</code></pre>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5>{{ __('Renewal command') }}</h5>
            <p class="text-muted">{{ __('Scheduled daily. You can also run manually:') }}</p>
            <pre class="bg-light border rounded p-3 mb-0"><code>php artisan external-button-subscriptions:charge
php artisan external-button-subscriptions:charge --limit=50</code></pre>
        </div>
    </div>
</x-dashboard.external>
