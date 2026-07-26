<x-dashboard.external>
    <div class="d-flex flex-wrap align-items-start justify-content-between mb-4 gap-3">
        <div>
            <a href="{{ route('external.buttonPayment') }}" class="text-decoration-none text-muted small">
                <i class="fas fa-arrow-left me-1"></i> {{ __('All buttons') }}
            </a>
            <h3 class="mb-1 mt-1">{{ __('Subscription button') }}</h3>
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

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <div class="text-uppercase small text-muted fw-semibold mb-1">{{ __('Source key') }}</div>
                <code>{{ $paymentApi->key }}</code>
            </div>
            <span class="badge badge-info">{{ __('Subscription') }}</span>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h5 class="mb-0">{{ __('Subscribers') }}</h5>
                <span class="badge bg-primary">{{ $subscriptions->total() }} {{ __('total') }}</span>
            </div>

            <form method="GET" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-uppercase small fw-semibold">{{ __('Search') }}</label>
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                            class="form-control" placeholder="Name, email, id…">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-uppercase small fw-semibold">{{ __('Status') }}</label>
                        <select name="status" class="form-control">
                            <option value="">{{ __('All') }}</option>
                            @foreach (['PENDING', 'ACTIVE', 'PAST_DUE', 'CANCELED', 'FAILED'] as $status)
                                <option value="{{ $status }}"
                                    {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>
                                    {{ $status }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">{{ __('Filter') }}</button>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('ID') }}</th>
                            <th>{{ __('Customer') }}</th>
                            <th>{{ __('Amount') }}</th>
                            <th>{{ __('Interval') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Next charge') }}</th>
                            <th>{{ __('Paid at') }}</th>
                            <th>{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($subscriptions as $subscription)
                            <tr>
                                <td class="fw-semibold">{{ $subscription->id }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $subscription->customer_name ?? '—' }}</div>
                                    <small class="text-muted">{{ $subscription->customer_email }}</small>
                                </td>
                                <td>
                                    {{ number_format((float) $subscription->amount, 2) }}
                                    {{ $subscription->currency }}
                                </td>
                                <td>{{ $subscription->interval_days }} {{ __('days') }}</td>
                                <td><span class="badge badge-secondary">{{ $subscription->status }}</span></td>
                                <td>{{ $subscription->next_charge_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td>{{ $subscription->paid_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td>
                                    @if (in_array(strtoupper((string) $subscription->status), ['ACTIVE', 'PAST_DUE', 'PENDING'], true))
                                        <form
                                            action="{{ route('external.buttonPayment.subscription.cancel', ['paymentApi' => $paymentApi, 'subscription' => $subscription]) }}"
                                            method="POST" class="d-inline"
                                            onsubmit="return confirm('Cancel this subscription?');">
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
                                <td colspan="8" class="text-center py-5 text-muted">
                                    {{ __('No subscribers yet.') }}
                                    <div class="mt-2">
                                        <a href="{{ route('external.buttonPayment.docs', $paymentApi) }}">
                                            {{ __('View integration docs') }}
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end mt-3">
                {{ $subscriptions->links() }}
            </div>
        </div>
    </div>
</x-dashboard.external>
