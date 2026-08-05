<x-dashboard.external>
    @include('dashboard.external.button.partials.api-docs-styles')

    @php
        $createSubscriptionUrl = route('iziipay.createSubscription', $paymentMethodAccess->key);
        $cancelSubscriptionUrl = route('iziipay.cancelSubscription', $paymentMethodAccess->key);
        $apiBase = rtrim(url('/api/iziipay'), '/');
    @endphp

    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
        <div>
            <a href="{{ route('external.buttonPayment.view', $paymentApi) }}" class="text-decoration-none text-muted small">
                <i class="fas fa-arrow-left me-1"></i> {{ __('Back to button') }}
            </a>
            <h3 class="mb-0 mt-1">{{ __('Subscription Button API Documentation') }}</h3>
            <p class="text-muted mb-0">{{ $paymentApi->domain }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('external.buttonPayment.view', $paymentApi) }}" class="btn btn-outline-secondary">
                <i class="fas fa-eye me-1"></i> {{ __('View subscriptions') }}
            </a>
            <a href="{{ route('external.buttonPayment.edit', $paymentApi) }}" class="btn btn-outline-primary">
                <i class="fas fa-edit me-1"></i> {{ __('Edit') }}
            </a>
        </div>
    </div>

    <div class="api-docs">
        <aside class="api-docs__sidebar">
            <h6>{{ __('API Reference') }}</h6>
            <div class="api-docs__base">{{ $apiBase }}</div>

            <div class="api-docs__group">
                <div class="api-docs__group-title">{{ __('Subscriptions') }}</div>
                <nav class="api-docs__nav">
                    <a href="#op-create"><span class="api-method api-method--post">POST</span> {{ __('Create subscription') }}</a>
                    <a href="#op-cancel"><span class="api-method api-method--post">POST</span> {{ __('Cancel subscription') }}</a>
                </nav>
            </div>

            <div class="api-docs__group">
                <div class="api-docs__group-title">{{ __('Other') }}</div>
                <nav class="api-docs__nav">
                    <a href="#op-renewal"><span class="api-method api-method--post">CLI</span> {{ __('Renewal command') }}</a>
                </nav>
            </div>
        </aside>

        <div class="api-docs__content">
            <div class="api-docs__intro">
                <p class="mb-2">
                    {{ __('Creates a subscription: customer pays the first period now, card is saved, then renewals are charged every interval_days via artisan command.') }}
                </p>
                <p class="mb-3">
                    <strong>{{ __('Elavon:') }}</strong> {{ __('vaulted card + MIT renewals.') }}
                    <strong class="ms-2">{{ __('Surfboard:') }}</strong> {{ __('tokenized first payment + MIT renewals (requires surfboard_mit_terminalId).') }}
                </p>
                <div class="api-docs__keys row g-3">
                    <div class="col-md-6">
                        <div class="text-uppercase small text-muted fw-semibold mb-1">{{ __('Plugin key') }}</div>
                        <code class="user-select-all">{{ $paymentMethodAccess->key }}</code>
                    </div>
                    <div class="col-md-6">
                        <div class="text-uppercase small text-muted fw-semibold mb-1">{{ __('Source key') }}</div>
                        <code class="user-select-all">{{ $paymentApi->key }}</code>
                    </div>
                </div>
            </div>

            <section class="api-op" id="op-create">
                <div class="api-op__header">
                    <span class="api-method api-method--post">POST</span>
                    <h2 class="api-op__title">{{ __('Create subscription') }}</h2>
                    <span class="api-op__path">/create-subscription/{{ $paymentMethodAccess->key }}</span>
                </div>
                <div class="api-op__body">
                    <p class="text-muted mb-0">{{ __('Creates a pending subscription and returns a hosted payment URL for the first charge.') }}</p>

                    <h3>{{ __('cURL Example') }}</h3>
                    <pre class="api-code"><code>curl --location --request POST '{{ $createSubscriptionUrl }}' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--data-raw '{
    "source_key": "{{ $paymentApi->key }}",
    "name": "Jane Doe",
    "email": "jane@example.com",
    "amount": 199,
    "currency": "NOK",
    "interval_days": 30,
    "phone": "1234567890",
    "country": "Norway",
    "address": "123 Main Street",
    "post_code": "0150",
    "description": "Monthly membership",
    "orderId": "plan-1",
    "preferred_acquirer": "elavon"
}'</code></pre>

                    <h3>{{ __('Request Body') }}</h3>
                    <pre class="api-code"><code>{
    "source_key": "{{ $paymentApi->key }}",
    "name": "Jane Doe",
    "email": "jane@example.com",
    "amount": 199,
    "currency": "NOK",
    "interval_days": 30,
    "phone": "1234567890",
    "country": "Norway",
    "address": "123 Main Street",
    "post_code": "0150",
    "description": "Monthly membership",
    "orderId": "plan-1",
    "preferred_acquirer": "elavon"
}</code></pre>

                    <h3>{{ __('Response Body') }}</h3>
                    <pre class="api-code"><code>{
    "url": "https://hpp.example/session...",
    "subscription": {
        "id": 1,
        "uuid": "01HXYZ...",
        "status": "PENDING",
        "amount": "199.00",
        "currency": "NOK",
        "interval_days": 30,
        "payment_method": "elavon",
        "payment_url": "https://hpp.example/session..."
    }
}</code></pre>

                    <h3>{{ __('Filter Rules') }}</h3>
                    <div class="table-responsive">
                        <table class="api-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Parameter') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Required') }}</th>
                                    <th>{{ __('Example') }}</th>
                                    <th>{{ __('Description') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>source_key</code></td>
                                    <td>string</td>
                                    <td>Yes</td>
                                    <td>{{ $paymentApi->key }}</td>
                                    <td>{{ __('This subscription button key.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>name</code></td>
                                    <td>string</td>
                                    <td>Yes</td>
                                    <td>Jane Doe</td>
                                    <td>{{ __('Customer name.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>email</code></td>
                                    <td>string</td>
                                    <td>Yes</td>
                                    <td>jane@example.com</td>
                                    <td>{{ __('Customer email.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>amount</code></td>
                                    <td>numeric</td>
                                    <td>Yes</td>
                                    <td>199</td>
                                    <td>{{ __('Charge amount each period. Must be at least 0.01.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>currency</code></td>
                                    <td>string</td>
                                    <td>Yes</td>
                                    <td>NOK</td>
                                    <td>{{ __('ISO currency code.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>interval_days</code></td>
                                    <td>integer</td>
                                    <td>Yes</td>
                                    <td>30</td>
                                    <td>{{ __('Days between renewal charges. Minimum 1.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>phone</code></td>
                                    <td>string</td>
                                    <td>No</td>
                                    <td>1234567890</td>
                                    <td>{{ __('Customer phone.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>country</code></td>
                                    <td>string</td>
                                    <td>No</td>
                                    <td>Norway</td>
                                    <td>{{ __('Customer country.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>address</code></td>
                                    <td>string</td>
                                    <td>No</td>
                                    <td>123 Main Street</td>
                                    <td>{{ __('Customer address.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>post_code</code></td>
                                    <td>string</td>
                                    <td>No</td>
                                    <td>0150</td>
                                    <td>{{ __('Customer postal code.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>taxValue</code></td>
                                    <td>string</td>
                                    <td>No</td>
                                    <td>25%</td>
                                    <td>{{ __('Tax rate label.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>taxTotal</code></td>
                                    <td>string</td>
                                    <td>No</td>
                                    <td>39.80</td>
                                    <td>{{ __('Tax amount.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>orderId</code></td>
                                    <td>string|integer</td>
                                    <td>No</td>
                                    <td>plan-1</td>
                                    <td>{{ __('Your internal plan / order reference.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>description</code></td>
                                    <td>string</td>
                                    <td>No</td>
                                    <td>Monthly membership</td>
                                    <td>{{ __('Subscription description.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>preferred_acquirer</code></td>
                                    <td>string</td>
                                    <td>No</td>
                                    <td>elavon</td>
                                    <td>{{ __('Allowed values: elavon, surfboard. Must be enabled on your account.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="api-op" id="op-cancel">
                <div class="api-op__header">
                    <span class="api-method api-method--post">POST</span>
                    <h2 class="api-op__title">{{ __('Cancel subscription') }}</h2>
                    <span class="api-op__path">/cancel-subscription/{{ $paymentMethodAccess->key }}</span>
                </div>
                <div class="api-op__body">
                    <p class="text-muted mb-0">{{ __('Stops future renewals for an active or past-due subscription.') }}</p>

                    <h3>{{ __('cURL Example') }}</h3>
                    <pre class="api-code"><code>curl --location --request POST '{{ $cancelSubscriptionUrl }}' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--data-raw '{
    "subscription_id": 123
}'</code></pre>

                    <h3>{{ __('Request Body') }}</h3>
                    <pre class="api-code"><code>{
    "subscription_id": 123
}</code></pre>

                    <h3>{{ __('Response Body') }}</h3>
                    <pre class="api-code"><code>{
    "status": true,
    "message": "Subscription canceled.",
    "subscription": {
        "id": 123,
        "status": "CANCELED",
        "canceled_at": "2026-08-05T10:00:00.000000Z",
        "next_charge_at": null
    }
}</code></pre>

                    <h3>{{ __('Filter Rules') }}</h3>
                    <div class="table-responsive">
                        <table class="api-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Parameter') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Required') }}</th>
                                    <th>{{ __('Example') }}</th>
                                    <th>{{ __('Description') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>subscription_id</code></td>
                                    <td>integer</td>
                                    <td>Yes</td>
                                    <td>123</td>
                                    <td>{{ __('The ID of the subscription to cancel.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="api-op" id="op-renewal">
                <div class="api-op__header">
                    <span class="api-method api-method--post">CLI</span>
                    <h2 class="api-op__title">{{ __('Renewal command') }}</h2>
                    <span class="api-op__path">external-button-subscriptions:charge</span>
                </div>
                <div class="api-op__body">
                    <p class="text-muted mb-0">{{ __('Scheduled daily. You can also run manually to charge due subscriptions:') }}</p>

                    <h3>{{ __('cURL Example') }}</h3>
                    <pre class="api-code"><code># Not an HTTP endpoint — run on the server:
php artisan external-button-subscriptions:charge
php artisan external-button-subscriptions:charge --limit=50</code></pre>

                    <h3>{{ __('Request Body') }}</h3>
                    <pre class="api-code"><code># Optional flags
--limit=50</code></pre>

                    <h3>{{ __('Response Body') }}</h3>
                    <pre class="api-code"><code>Charging due external button subscriptions...
Done. charged=3 failed=0 skipped=1</code></pre>
                </div>
            </section>
        </div>
    </div>

    @push('scripts')
        <script>
            document.querySelectorAll('.api-docs__nav a').forEach((link) => {
                link.addEventListener('click', function () {
                    document.querySelectorAll('.api-docs__nav a').forEach((item) => item.classList.remove('active'));
                    this.classList.add('active');
                });
            });
        </script>
    @endpush
</x-dashboard.external>
