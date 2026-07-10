<x-dashboard.external>
    <h1>
        Payment Method
    </h1>
    <div class="card">
        <div class="card-body">
            <table class="table">
                <tr>
                    <th>
                        #
                    </th>
                    <th>
                        {{ __('words.domain') }}
                    </th>
                    <th>
                        {{ __('words.paid_at') }}
                    </th>
                    <th>
                        {{ __('words.actions') }}
                    </th>
                </tr>
                @foreach ($paymentMethodAccesses as $paymentMethodAccess)
                    <tr>
                        <td>
                            {{ $paymentMethodAccess->id }}
                        </td>
                        <td>
                            {{ $paymentMethodAccess->company_domain }} <span
                                class="badge badge-{{ $paymentMethodAccess->status ? 'success' : 'danger' }}">{{ $paymentMethodAccess->status ? 'On' : 'Off' }}</span>
                            @if ($paymentMethodAccess->needs_elavon_resubscription)
                                <div class="small text-warning">
                                    {{ $paymentMethodAccess->elavon_resubscription_message }}
                                </div>
                            @endif

                        </td>
                        <td>

                            <span>{{ $paymentMethodAccess->last_paid_at->format('d M, Y H:i') }}</span>
                        </td>
                        <td>
                            <div>
                                <a href="{{route('external.paymentMethodAccess',$paymentMethodAccess)}}" class="btn btn-info btn-sm" title="View"><i class="fa fa-eye"></i></a>
                              
                            </div>
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
</x-dashboard.external>
