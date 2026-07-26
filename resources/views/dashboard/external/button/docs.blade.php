<x-dashboard.external>
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
        <div>
            <a href="{{ route('external.buttonPayment.view', $paymentApi) }}" class="text-decoration-none text-muted small">
                <i class="fas fa-arrow-left me-1"></i> {{ __('Back to button') }}
            </a>
            <h3 class="mb-0 mt-1">{{ __('Integration docs') }}</h3>
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
        </div>
    </div>

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-js-btn" data-bs-toggle="tab" data-bs-target="#tab-js"
                type="button" role="tab">{{ __('JavaScript button') }}</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-create-btn" data-bs-toggle="tab" data-bs-target="#tab-create"
                type="button" role="tab">{{ __('Create payment API') }}</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-cancel-btn" data-bs-toggle="tab" data-bs-target="#tab-cancel"
                type="button" role="tab">{{ __('Cancel payment API') }}</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-callback-btn" data-bs-toggle="tab" data-bs-target="#tab-callback"
                type="button" role="tab">{{ __('Cancel callback') }}</button>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-js" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">{{ __('Embed the payment button') }}</h5>
                    <p class="text-muted">{{ __('Add this snippet to your site. Replace amount and order details as needed.') }}</p>
                    <pre class="bg-light border rounded p-3 mb-0"><code class="text-dark">&lt;div id="iziipay"&gt;&lt;/div&gt;
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
    });
&lt;/script&gt;</code></pre>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-create" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-2">{{ __('Create payment') }}</h5>
                    <pre class="bg-light border rounded p-3"><code>POST {{ route('iziipay.createPayment', $paymentMethodAccess->key) }}</code></pre>
                    <p>{{ __('Creates a new external order and returns a payment link.') }}</p>

                    <h6 class="mt-4">{{ __('Body parameters') }}</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Parameter') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Required') }}</th>
                                    <th>{{ __('Description') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td>source_key</td><td>string</td><td>Yes</td><td>Unique API key for the payment source.</td></tr>
                                <tr><td>name</td><td>string</td><td>Yes</td><td>Customer's full name.</td></tr>
                                <tr><td>email</td><td>string</td><td>Yes</td><td>Customer's email address.</td></tr>
                                <tr><td>phone</td><td>string</td><td>Yes</td><td>Customer's phone number.</td></tr>
                                <tr><td>country</td><td>string</td><td>Yes</td><td>Customer's country of residence.</td></tr>
                                <tr><td>address</td><td>string</td><td>Yes</td><td>Customer's street address.</td></tr>
                                <tr><td>post_code</td><td>string</td><td>Yes</td><td>Customer's postal/ZIP code.</td></tr>
                                <tr><td>amount</td><td>float</td><td>Yes</td><td>Payment amount to be processed.</td></tr>
                                <tr><td>currency</td><td>string</td><td>Yes</td><td>Currency for the payment (e.g., NOK).</td></tr>
                                <tr><td>taxValue</td><td>string</td><td>Yes</td><td>Tax value (e.g., 10%).</td></tr>
                                <tr><td>taxTotal</td><td>string</td><td>Yes</td><td>Tax total (e.g., 27.4).</td></tr>
                                <tr><td>description</td><td>string</td><td>Yes</td><td>Order description.</td></tr>
                                <tr><td>orderId</td><td>integer</td><td>Yes</td><td>Your internal order id.</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <h6 class="mt-4">{{ __('Success response') }}</h6>
                    <pre class="bg-light border rounded p-3"><code>{
    "url": "https://payment-gateway.com/payment-link"
}</code></pre>

                    <h6 class="mt-4">{{ __('Error responses') }}</h6>
                    <pre class="bg-light border rounded p-3"><code>400 Bad Request
{ "error": "Invalid source key provided." }

404 Not Found
{ "error": "Payment method or API not found." }

500 Internal Server Error
{ "error": "An unexpected error occurred. Please try again later." }</code></pre>

                    <h6 class="mt-4">{{ __('cURL example') }}</h6>
                    <pre class="bg-light border rounded p-3 mb-0"><code>curl -X POST {{ route('iziipay.createPayment', $paymentMethodAccess->key) }} \
-H "Content-Type: application/json" \
-d '{
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
    "orderId": 1234
}'</code></pre>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-cancel" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-2">{{ __('Cancel payment') }}</h5>
                    <pre class="bg-light border rounded p-3"><code>POST {{ route('iziipay.cancel.surfboard.payment', $paymentMethodAccess->key) }}</code></pre>
                    <p>{{ __('Cancels an existing external order payment. Supported for Surfboard.') }}</p>

                    <h6 class="mt-4">{{ __('Body parameters') }}</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Parameter') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Required') }}</th>
                                    <th>{{ __('Description') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>order_id</td>
                                    <td>integer</td>
                                    <td>Yes</td>
                                    <td>The ID of the external order to cancel.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h6 class="mt-4">{{ __('Success response') }}</h6>
                    <pre class="bg-light border rounded p-3"><code>{
    "status": true,
    "code": 200,
    "data": {
        "orderStatus": "CANCELED",
        "message": "Order canceled successfully"
    }
}</code></pre>

                    <h6 class="mt-4">{{ __('cURL example') }}</h6>
                    <pre class="bg-light border rounded p-3 mb-0"><code>curl -X POST {{ route('iziipay.cancel.surfboard.payment', $paymentMethodAccess->key) }} \
-H "Content-Type: application/json" \
-d '{ "order_id": 123 }'</code></pre>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-callback" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">{{ __('Cancel order callback') }}</h5>
                    <p>
                        <strong>{{ __('Important:') }}</strong>
                        {{ __('Administrators will call YOUR callback URL (Cancel Callback URL) to notify you about order cancellations.') }}
                    </p>

                    <h6>{{ __('How it works') }}</h6>
                    <ol>
                        <li>{{ __('Set your callback URL when creating/editing the payment button.') }}</li>
                        <li>{{ __('When an order is canceled, a GET request is sent to your callback URL.') }}</li>
                        <li>{{ __('Your server processes the cancellation on your side.') }}</li>
                    </ol>

                    <h6 class="mt-4">{{ __('Request format') }}</h6>
                    <pre class="bg-light border rounded p-3"><code>GET {YOUR_CALLBACK_URL}?order_id={order_id}</code></pre>

                    <h6 class="mt-4">{{ __('Query parameters') }}</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Parameter') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Required') }}</th>
                                    <th>{{ __('Description') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>order_id</td>
                                    <td>integer</td>
                                    <td>Yes</td>
                                    <td>The ID of the external order to cancel.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h6 class="mt-4">{{ __('Example PHP handler') }}</h6>
                    <pre class="bg-light border rounded p-3"><code>Route::get('/api/cancel-order', function (Request $request) {
    $orderId = $request->query('order_id');

    // Process cancellation on your end

    return response()->json([
        'status' => 'received',
        'order_id' => $orderId,
    ]);
});</code></pre>

                    <h6 class="mt-4">{{ __('Example Node.js handler') }}</h6>
                    <pre class="bg-light border rounded p-3 mb-0"><code>app.get('/api/cancel-order', (req, res) => {
    const orderId = req.query.order_id;

    // Process cancellation on your end

    res.json({
        status: 'received',
        order_id: orderId,
    });
});</code></pre>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            // Fallback for Bootstrap 4 data-toggle if BS5 tabs are unavailable
            document.querySelectorAll('[data-bs-toggle="tab"]').forEach((button) => {
                button.addEventListener('click', function(event) {
                    event.preventDefault();
                    const target = document.querySelector(this.getAttribute('data-bs-target'));
                    if (!target) {
                        return;
                    }

                    document.querySelectorAll('.nav-tabs .nav-link').forEach((link) => link.classList.remove('active'));
                    document.querySelectorAll('.tab-pane').forEach((pane) => {
                        pane.classList.remove('show', 'active');
                    });

                    this.classList.add('active');
                    target.classList.add('show', 'active');
                });
            });
        </script>
    @endpush
</x-dashboard.external>
