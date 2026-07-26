<x-dashboard.external>
    <div class="d-flex flex-wrap align-items-start justify-content-between mb-4 gap-3">
        <div>
            <a href="{{ route('external.buttonPayment') }}" class="text-decoration-none text-muted small">
                <i class="fas fa-arrow-left me-1"></i> {{ __('All buttons') }}
            </a>
            <h3 class="mb-1 mt-1">{{ __('words.button_payment') }}</h3>
            <p class="text-muted mb-0">{{ $paymentApi->domain }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('external.buttonPayment.docs', $paymentApi) }}" class="btn btn-outline-secondary">
                <i class="fas fa-book me-1"></i> {{ __('Documentation') }}
            </a>
            <a href="{{ route('external.buttonPayment.edit', $paymentApi) }}" class="btn btn-primary">
                <i class="fas fa-edit me-1"></i> {{ __('Edit') }}
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-uppercase small text-muted fw-semibold mb-1">{{ __('Domain') }}</div>
                    <div class="fw-semibold text-break">{{ $paymentApi->domain }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-uppercase small text-muted fw-semibold mb-1">{{ __('Success URL') }}</div>
                    <div class="small text-break">{{ $paymentApi->success_redirect_url }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-uppercase small text-muted fw-semibold mb-1">{{ __('Failed URL') }}</div>
                    <div class="small text-break">{{ $paymentApi->failed_redirect_url }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-uppercase small text-muted fw-semibold mb-1">{{ __('Cancel callback') }}</div>
                    <div class="small text-break">
                        {{ $paymentApi->cancel_callback_url ?: __('Not configured') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <div class="text-uppercase small text-muted fw-semibold mb-1">{{ __('Source key') }}</div>
                <code id="source-key" class="user-select-all">{{ $paymentApi->key }}</code>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="copy-source-key">
                <i class="fas fa-copy me-1"></i> {{ __('Copy') }}
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h5 class="mb-0">{{ __('Orders') }}</h5>
                <span class="badge bg-primary">{{ $orders->total() }} {{ __('total') }}</span>
            </div>

            <form action="" method="GET" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label text-uppercase small fw-semibold">{{ __('Search') }}</label>
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                            class="form-control" placeholder="Name, email, order id…">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-uppercase small fw-semibold">{{ __('Paid between') }}</label>
                        <div class="input-group">
                            <input type="date" name="paid_from" value="{{ $filters['paid_from'] ?? '' }}"
                                class="form-control">
                            <span class="input-group-text">—</span>
                            <input type="date" name="paid_to" value="{{ $filters['paid_to'] ?? '' }}"
                                class="form-control">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-uppercase small fw-semibold">{{ __('Status') }}</label>
                        <select name="status" class="form-control">
                            <option value="">{{ __('All') }}</option>
                            <option value="PENDING"
                                {{ ($filters['status'] ?? '') === 'PENDING' ? 'selected' : '' }}>
                                {{ __('Pending') }}
                            </option>
                            <option value="COMPLETED"
                                {{ ($filters['status'] ?? '') === 'COMPLETED' ? 'selected' : '' }}>
                                {{ __('Completed') }}
                            </option>
                        </select>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a href="{{ route('external.buttonPayment.view', $paymentApi) }}"
                            class="btn btn-light border">{{ __('Reset') }}</a>
                        <button type="submit" class="btn btn-primary">{{ __('Apply filters') }}</button>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ __('ID') }}</th>
                            <th>{{ __('Order ID') }}</th>
                            <th>{{ __('Payment ID') }}</th>
                            <th>{{ __('Customer') }}</th>
                            <th>{{ __('Email') }}</th>
                            <th>{{ __('Amount') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Paid at') }}</th>
                            <th>{{ __('Created at') }}</th>
                            <th>{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr>
                                <td class="fw-semibold">{{ $order->id }}</td>
                                <td>{{ $order->orderId ?? '—' }}</td>
                                <td><span class="font-monospace">{{ $order->payment_id ?? '—' }}</span></td>
                                <td>
                                    <div class="fw-semibold">{{ $order->customer_name ?? '—' }}</div>
                                    @if ($order->description)
                                        <small class="text-muted">{{ $order->description }}</small>
                                    @endif
                                </td>
                                <td>{{ $order->customer_email ?? '—' }}</td>
                                <td>
                                    <div class="fw-semibold">
                                        {{ number_format((float) $order->amount, 2) }}
                                        {{ $order->currency }}
                                    </div>
                                </td>
                                <td>
                                    @php
                                        $status = strtoupper($order->status ?? 'PENDING');
                                        $statusClass =
                                            [
                                                'COMPLETED' => 'badge-success',
                                                'FAILED' => 'badge-danger',
                                                'CANCELED' => 'badge-secondary',
                                                'PENDING' => 'badge-warning',
                                            ][$status] ?? 'badge-light';
                                    @endphp
                                    <span class="badge {{ $statusClass }}">{{ $status }}</span>
                                </td>
                                <td>
                                    @php
                                        $paidAt = $order->paid_at
                                            ? \Illuminate\Support\Carbon::parse($order->paid_at)
                                            : null;
                                    @endphp
                                    {{ $paidAt?->format('Y-m-d H:i') ?? '—' }}
                                </td>
                                <td>{{ $order->created_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td>
                                    @if (strtoupper($order->status ?? 'PENDING') === 'PENDING')
                                        <form
                                            action="{{ route('external.buttonPayment.cancel', ['paymentApi' => $paymentApi, 'order' => $order->id]) }}"
                                            method="POST" class="d-inline"
                                            onsubmit="return confirm('Are you sure you want to cancel this order?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                {{ __('Cancel') }}
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <div class="fs-5 fw-semibold">{{ __('No orders found') }}</div>
                                    <p class="mb-0">
                                        {{ __('Try adjusting your filters or create a new payment request.') }}
                                    </p>
                                    <a href="{{ route('external.buttonPayment.docs', $paymentApi) }}"
                                        class="btn btn-sm btn-outline-secondary mt-3">
                                        {{ __('View integration docs') }}
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end mt-3">
                {{ $orders->appends(request()->query())->links() }}
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.getElementById('copy-source-key')?.addEventListener('click', async function() {
                const key = document.getElementById('source-key')?.textContent?.trim();
                if (!key) {
                    return;
                }

                try {
                    await navigator.clipboard.writeText(key);
                    this.textContent = '{{ __('Copied!') }}';
                    setTimeout(() => {
                        this.innerHTML = '<i class="fas fa-copy me-1"></i> {{ __('Copy') }}';
                    }, 1500);
                } catch (error) {
                    prompt('{{ __('Copy this source key:') }}', key);
                }
            });
        </script>
    @endpush
</x-dashboard.external>
