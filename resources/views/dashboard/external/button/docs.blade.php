<x-dashboard.external>
    @include('dashboard.external.button.partials.api-docs-styles')

    @php
        $createPaymentUrl = route('iziipay.createPayment', $paymentMethodAccess->key);
        $cancelPaymentUrl = route('iziipay.cancel.surfboard.payment', $paymentMethodAccess->key);
        $apiBase = rtrim(url('/api/iziipay'), '/');
    @endphp

    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
        <div>
            <a href="{{ route('external.buttonPayment.view', $paymentApi) }}" class="text-decoration-none text-muted small">
                <i class="fas fa-arrow-left me-1"></i> {{ __('Back to button') }}
            </a>
            <h3 class="mb-0 mt-1">{{ __('Payment Button API Documentation') }}</h3>
            <p class="text-muted mb-0">{{ $paymentApi->domain }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('external.buttonPayment.view', $paymentApi) }}" class="btn btn-outline-secondary">
                <i class="fas fa-eye me-1"></i> {{ __('View orders') }}
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
                <div class="api-docs__group-title">{{ __('Integration') }}</div>
                <nav class="api-docs__nav">
                    <a href="#op-js"><span class="api-method api-method--js">JS</span> {{ __('Embed button') }}</a>
                </nav>
            </div>

            <div class="api-docs__group">
                <div class="api-docs__group-title">{{ __('Payments') }}</div>
                <nav class="api-docs__nav">
                    <a href="#op-create"><span class="api-method api-method--post">POST</span> {{ __('Create payment') }}</a>
                    <a href="#op-cancel"><span class="api-method api-method--post">POST</span> {{ __('Cancel payment') }}</a>
                </nav>
            </div>

            <div class="api-docs__group">
                <div class="api-docs__group-title">{{ __('Callbacks') }}</div>
                <nav class="api-docs__nav">
                    <a href="#op-callback"><span class="api-method api-method--get">GET</span> {{ __('Cancel callback') }}</a>
                </nav>
            </div>
        </aside>

        <div class="api-docs__content">
            <div class="api-docs__intro">
                <p class="mb-3">
                    {{ __('Use these endpoints to create one-off payments from your site. Embed the JavaScript button, or call the create-payment API directly with your plugin key.') }}
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

            <section class="api-op" id="op-js">
                <div class="api-op__header">
                    <span class="api-method api-method--js">JS</span>
                    <h2 class="api-op__title">{{ __('Embed payment button') }}</h2>
                    <span class="api-op__path">iziipay.js</span>
                </div>
                <div class="api-op__body">
                    <p class="text-muted mb-3">{{ __('Add this snippet to your site. Replace amount and order details as needed.') }}</p>
                    <h3>{{ __('Snippet') }}</h3>
                    <pre class="api-code"><code>&lt;div id="iziipay"&gt;&lt;/div&gt;
&lt;script src="{{ asset('payment/iziipay.js') }}"&gt;&lt;/script&gt;
&lt;script&gt;
    Iziipay.init('#iziipay', {
        apiKey: "{{ $paymentMethodAccess->key }}",
        buttonText: 'Pay now',
        source_key: "{{ $paymentApi->key }}",
        amount: "300",
        taxValue: "10%",
        taxTotal: "27.27",
        orderId: "300",
        description: "T-Shirt Purchase",
        currency: "NOK",
        company_name: "Acme AS",
        organization_number: "123456789",
    });
&lt;/script&gt;</code></pre>
                </div>
            </section>

            <section class="api-op" id="op-create">
                <div class="api-op__header">
                    <span class="api-method api-method--post">POST</span>
                    <h2 class="api-op__title">{{ __('Create payment') }}</h2>
                    <span class="api-op__path">/create-payment/{{ $paymentMethodAccess->key }}</span>
                </div>
                <div class="api-op__body">
                    <p class="text-muted mb-0">{{ __('Creates a new external order and returns a hosted payment URL.') }}</p>

                    <h3>{{ __('cURL Example') }}</h3>
                    <pre class="api-code"><code>curl --location --request POST '{{ $createPaymentUrl }}' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--data-raw '{
    "source_key": "{{ $paymentApi->key }}",
    "name": "John Doe",
    "email": "john.doe@example.com",
    "phone": "1234567890",
    "country": "Norway",
    "address": "123 Main Street",
    "post_code": "12345",
    "amount": 100.00,
    "currency": "NOK",
    "taxValue": "10%",
    "taxTotal": "9.09",
    "description": "T-Shirt Purchase",
    "orderId": 1234,
    "company_name": "Acme AS",
    "organization_number": "123456789"
}'</code></pre>

                    <h3>{{ __('Request Body') }}</h3>
                    <pre class="api-code"><code>{
    "source_key": "{{ $paymentApi->key }}",
    "name": "John Doe",
    "email": "john.doe@example.com",
    "phone": "1234567890",
    "country": "Norway",
    "address": "123 Main Street",
    "post_code": "12345",
    "amount": 100.00,
    "currency": "NOK",
    "taxValue": "10%",
    "taxTotal": "9.09",
    "description": "T-Shirt Purchase",
    "orderId": 1234,
    "company_name": "Acme AS",
    "organization_number": "123456789"
}</code></pre>

                    <h3>{{ __('Response Body') }}</h3>
                    <pre class="api-code"><code>{
    "url": "https://payment-gateway.com/payment-link",
    "order": {
        "id": 123,
        "uuid": "01HXYZ...",
        "status": "PENDING",
        "amount": "100.00",
        "currency": "NOK",
        "orderId": "1234"
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
                                    <td>{{ __('Unique API key for this payment button.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>name</code></td>
                                    <td>string</td>
                                    <td>Yes</td>
                                    <td>John Doe</td>
                                    <td>{{ __('Customer full name.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>email</code></td>
                                    <td>string</td>
                                    <td>Yes</td>
                                    <td>john.doe@example.com</td>
                                    <td>{{ __('Customer email address.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>phone</code></td>
                                    <td>string</td>
                                    <td>No</td>
                                    <td>1234567890</td>
                                    <td>{{ __('Customer phone number.') }}</td>
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
                                    <td>{{ __('Customer street address.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>post_code</code></td>
                                    <td>string</td>
                                    <td>No</td>
                                    <td>12345</td>
                                    <td>{{ __('Customer postal / ZIP code.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>amount</code></td>
                                    <td>numeric</td>
                                    <td>Yes</td>
                                    <td>100.00</td>
                                    <td>{{ __('Payment amount to charge.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>currency</code></td>
                                    <td>string</td>
                                    <td>Yes</td>
                                    <td>NOK</td>
                                    <td>{{ __('ISO currency code.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>taxValue</code></td>
                                    <td>string</td>
                                    <td>No</td>
                                    <td>10%</td>
                                    <td>{{ __('Tax rate label.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>taxTotal</code></td>
                                    <td>string</td>
                                    <td>No</td>
                                    <td>9.09</td>
                                    <td>{{ __('Tax amount.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>description</code></td>
                                    <td>string</td>
                                    <td>No</td>
                                    <td>T-Shirt Purchase</td>
                                    <td>{{ __('Order description shown to the customer.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>orderId</code></td>
                                    <td>string|integer</td>
                                    <td>No</td>
                                    <td>1234</td>
                                    <td>{{ __('Your internal order reference.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>company_name</code></td>
                                    <td>string</td>
                                    <td>No</td>
                                    <td>Acme AS</td>
                                    <td>{{ __('Updates the plugin profile company name. Not stored on the order.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>organization_number</code></td>
                                    <td>string</td>
                                    <td>No</td>
                                    <td>123456789</td>
                                    <td>{{ __('Updates the plugin profile organization number. Not stored on the order.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="api-op" id="op-cancel">
                <div class="api-op__header">
                    <span class="api-method api-method--post">POST</span>
                    <h2 class="api-op__title">{{ __('Cancel payment') }}</h2>
                    <span class="api-op__path">/cancel-payment/{{ $paymentMethodAccess->key }}</span>
                </div>
                <div class="api-op__body">
                    <p class="text-muted mb-0">{{ __('Cancels an existing external order payment. Supported for Surfboard.') }}</p>

                    <h3>{{ __('cURL Example') }}</h3>
                    <pre class="api-code"><code>curl --location --request POST '{{ $cancelPaymentUrl }}' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--data-raw '{
    "order_id": 123
}'</code></pre>

                    <h3>{{ __('Request Body') }}</h3>
                    <pre class="api-code"><code>{
    "order_id": 123
}</code></pre>

                    <h3>{{ __('Response Body') }}</h3>
                    <pre class="api-code"><code>{
    "status": true,
    "code": 200,
    "data": {
        "orderStatus": "CANCELED",
        "message": "Order canceled successfully"
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
                                    <td><code>order_id</code></td>
                                    <td>integer</td>
                                    <td>Yes</td>
                                    <td>123</td>
                                    <td>{{ __('The ID of the external order to cancel.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="api-op" id="op-callback">
                <div class="api-op__header">
                    <span class="api-method api-method--get">GET</span>
                    <h2 class="api-op__title">{{ __('Cancel order callback') }}</h2>
                    <span class="api-op__path">{YOUR_CALLBACK_URL}?order_id={order_id}</span>
                </div>
                <div class="api-op__body">
                    <p class="mb-2">
                        <strong>{{ __('Important:') }}</strong>
                        {{ __('Administrators will call YOUR callback URL (Cancel Callback URL) to notify you about order cancellations.') }}
                    </p>
                    <ol class="mb-3">
                        <li>{{ __('Set your callback URL when creating/editing the payment button.') }}</li>
                        <li>{{ __('When an order is canceled, a GET request is sent to your callback URL.') }}</li>
                        <li>{{ __('Your server processes the cancellation on your side.') }}</li>
                    </ol>

                    <h3>{{ __('cURL Example') }}</h3>
                    <pre class="api-code"><code>curl --location --request GET 'https://yoursite.com/api/cancel-order?order_id=123'</code></pre>

                    <h3>{{ __('Request Body') }}</h3>
                    <pre class="api-code"><code>// Query string only — no JSON body
GET {YOUR_CALLBACK_URL}?order_id=123</code></pre>

                    <h3>{{ __('Response Body') }}</h3>
                    <pre class="api-code"><code>{
    "status": "received",
    "order_id": "123"
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
                                    <td><code>order_id</code></td>
                                    <td>integer</td>
                                    <td>Yes</td>
                                    <td>123</td>
                                    <td>{{ __('The ID of the external order to cancel.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h3>{{ __('Example PHP handler') }}</h3>
                    <pre class="api-code"><code>Route::get('/api/cancel-order', function (Request $request) {
    $orderId = $request->query('order_id');

    // Process cancellation on your end

    return response()->json([
        'status' => 'received',
        'order_id' => $orderId,
    ]);
});</code></pre>

                    <h3>{{ __('Example Node.js handler') }}</h3>
                    <pre class="api-code"><code>app.get('/api/cancel-order', (req, res) => {
    const orderId = req.query.order_id;

    // Process cancellation on your end

    res.json({
        status: 'received',
        order_id: orderId,
    });
});</code></pre>
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
