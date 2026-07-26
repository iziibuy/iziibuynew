<x-dashboard.external>
    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
                <h3 class="mb-0">{{ __('words.button_payment_title') }}</h3>
                <a class="btn btn-primary" href="{{ route('external.buttonPayment.create') }}">
                    {{ __('Create Button') }}
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Domain') }}</th>
                            <th>{{ __('Success url') }}</th>
                            <th>{{ __('Failed url') }}</th>
                            <th>{{ __('Key') }}</th>
                            <th>{{ __('Created at') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($apis as $api)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    @if ($api->is_subscription)
                                        <span class="badge badge-info">{{ __('Subscription') }}</span>
                                    @else
                                        <span class="badge badge-secondary">{{ __('One-time') }}</span>
                                    @endif
                                </td>
                                <td>{{ $api->domain }}</td>
                                <td class="small">{{ $api->success_redirect_url }}</td>
                                <td class="small">{{ $api->failed_redirect_url }}</td>
                                <td><code>{{ $api->key }}</code></td>
                                <td>{{ $api->created_at }}</td>
                                <td>
                                    <a class="btn btn-primary btn-sm"
                                        href="{{ route('external.buttonPayment.edit', $api) }}">
                                        <i class="fa fa-edit"></i> {{ __('Edit') }}
                                    </a>
                                    <a href="{{ route('external.buttonPayment.view', $api) }}"
                                        class="btn btn-primary btn-sm">
                                        <i class="fa fa-eye"></i> {{ __('View') }}
                                    </a>
                                    <a href="{{ route('external.buttonPayment.docs', $api) }}"
                                        class="btn btn-outline-secondary btn-sm">
                                        <i class="fa fa-book"></i> {{ __('Docs') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    {{ __('No payment buttons yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-dashboard.external>
