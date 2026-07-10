<x-dashboard.external>
    <div class="d-flex justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="mb-0 pb-0">
                {{ $paymentMethodAccess->company_name }}
            </h1>
            <a class="mt-0 pt-0"
                href="{{ $paymentMethodAccess->company_domain }}">{{ $paymentMethodAccess->company_domain }} </a>
        </div>
       
    </div>
    <div class="row mt-5">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    @if ($paymentMethodAccess->needs_elavon_resubscription)
                        <div class="alert alert-warning" role="alert">
                            {{ $paymentMethodAccess->elavon_resubscription_message }}
                        </div>
                    @endif

                    <table class="table">

                        <tr>
                            <th>
                                {{ __('words.key') }}
                            </th>
                            <td class="d-flex">

                                <input readonly disabled type="text" id="key" class="form-control"
                                    value="{{ $paymentMethodAccess->key }}">
                                <button class="btn btn-primary btn-sm" data-text="{{ $paymentMethodAccess->key }}"
                                    onclick="copyKey(this)"><i class="fa fa-copy"></i></button>
                            </td>
                        </tr>
                        @if ($paymentMethodAccess->paymentMethod == 'quickpay')
                            @if ($paymentMethodAccess->quickpay_api_key)
                                <tr>
                                    <th>
                                        {{ __('words.quickpay_api_key') }}
                                    </th>
                                    <td class="d-flex">

                                        <input readonly disabled type="text" id="key" class="form-control"
                                            value="{{ $paymentMethodAccess->quickpay_api_key }}">
                                        <button class="btn btn-primary btn-sm"
                                            data-text="{{ $paymentMethodAccess->quickpay_api_key }}"
                                            onclick="copyKey(this)"><i class="fa fa-copy"></i></button>
                                    </td>
                                </tr>
                            @endif
                            @if ($paymentMethodAccess->quickpay_secret_key)
                                <tr>
                                    <th>
                                        {{ __('words.quickpay_secret_key') }}
                                    </th>
                                    <td class="d-flex">

                                        <input readonly disabled type="text" id="key" class="form-control"
                                            value="{{ $paymentMethodAccess->quickpay_secret_key }}">
                                        <button class="btn btn-primary btn-sm"
                                            data-text="{{ $paymentMethodAccess->quickpay_secret_key }}"
                                            onclick="copyKey(this)"><i class="fa fa-copy"></i></button>
                                    </td>
                                </tr>
                            @endif
                        @elseif ($paymentMethodAccess->paymentMethod == 'surfboard')
                            @foreach (['surfboard_terminalId', 'surfboard_merchantId', 'surfboard_storeId'] as $field)
                                <tr>
                                    <th>{{ __('words.' . $field) }}</th>
                                    <td class="d-flex">
                                        <input readonly disabled type="text" id="key" class="form-control"
                                            value="{{ $paymentMethodAccess->$field }}">
                                        <button class="btn btn-primary btn-sm"
                                            data-text="{{ $paymentMethodAccess->$field }}" onclick="copyKey(this)"><i
                                                class="fa fa-copy"></i></button>
                                    </td>
                                </tr>
                            @endforeach
                        @else
                            @foreach (['elavon_merchant_alias', 'elavon_public_key', 'elavon_secret_key'] as $field)
                                <tr>
                                    <th>{{ __('words.' . $field) }}</th>
                                    <td class="d-flex">
                                        <input readonly disabled type="text" id="key" class="form-control"
                                            value="{{ $paymentMethodAccess->$field }}">
                                        <button class="btn btn-primary btn-sm"
                                            data-text="{{ $paymentMethodAccess->$field }}" onclick="copyKey(this)"><i
                                                class="fa fa-copy"></i></button>
                                    </td>
                                </tr>
                            @endforeach

                        @endif
                        <tr>
                            <th>
                                {{ __('words.email') }}
                            </th>
                            <td>
                                <a
                                    href="mailto:{{ $paymentMethodAccess->company_email }}">{{ $paymentMethodAccess->company_email }}</a>

                            </td>
                        </tr>
                        <tr>
                            <th>
                                {{ __('words.registration') }}
                            </th>
                            <td>
                                {{ $paymentMethodAccess->company_registration }}

                            </td>
                        </tr>
                        <tr>
                            <th>
                                {{ __('words.address') }}
                            </th>
                            <td>
                                <ul style="list-style: none">
                                    @if (isset($paymentMethodAccess->company_address))
                                        @foreach ($paymentMethodAccess->company_address as $key => $address)
                                            <li>
                                                <strong>
                                                    {{ ucwords($key) }} :
                                                </strong>
                                                {{ $address }}
                                            </li>
                                        @endforeach

                                    @endif
                                </ul>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                {{ __('words.status') }}
                            </th>
                            <td>
                                <span
                                    class="badge badge-{{ $paymentMethodAccess->status ? 'success' : 'danger' }}">{{ $paymentMethodAccess->status ? __('words.on') : __('words.off') }}</span>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                {{ __('words.last_paid_at') }}
                            </th>
                            <td>
                                {{ $paymentMethodAccess?->last_paid_at?->format('d M, Y H:i ') ?? 'N/A' }}
                            </td>
                        </tr>
                        <tr>
                            <th>
                                {{ __('words.total_paid') }} :
                            </th>
                            <td>
                                {{ Iziibuy::price($paymentMethodAccess->subscription->charges()->where('status', 1)->sum('amount') / 100) }}
                            </td>
                        </tr>

                    </table>
                    <div class="accordion" id="accordionExample">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingOne">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                                    {{ __('words.payment_information') }}
                                </button>
                            </h2>
                            <div id="collapseOne" class="accordion-collapse collapse " aria-labelledby="headingOne"
                                data-bs-parent="#accordionExample">
                                <div class="accordion-body">
                                    @if (! empty($billingStatus))
                                        <table class="table">
                                            <tr>
                                                <th>{{ __('words.provider') }}</th>
                                                <td>{{ ucfirst($billingStatus['provider'] ?? 'elavon') }}</td>
                                            </tr>
                                            <tr>
                                                <th>{{ __('words.status') }}</th>
                                                <td>{{ ($billingStatus['active'] ?? false) ? __('words.active') : __('words.inactive') }}</td>
                                            </tr>
                                            @if (! empty($billingStatus['last_paid_at']))
                                                <tr>
                                                    <th>{{ __('words.last_paid') }}</th>
                                                    <td>{{ $billingStatus['last_paid_at'] }}</td>
                                                </tr>
                                            @endif
                                        </table>
                                    @endif
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

        </div>


    </div>
    @push('scripts')
        <script>
            function copyKey(el) {
                // Get the text field
                var copyText = el;


                // Select the text field

                // Copy the text inside the text field
                navigator.clipboard.writeText(copyText.dataset.text);

                // Alert the copied text
                alert("Copied the text: " + copyText.dataset.text);
            }
        </script>
    @endpush
</x-dashboard.external>
