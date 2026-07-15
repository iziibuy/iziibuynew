<x-dashboard.enterprise>
    <div class="d-flex justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="mb-0 pb-0">
                {{ $enterprise->company_name }}
            </h1>
            <a class="mt-0 pt-0"
                href="{{ $enterprise->company_domain }}">{{ $enterprise->company_domain }} </a>
            @if ($enterprise->needs_elavon_resubscription)
                <div class="small text-warning mt-2">
                    {{ $enterprise->elavon_resubscription_message }}
                </div>
            @endif
        </div>
        <div class="d-flex flex-column gap-2">
            {{-- <a href="{{ route('external.download.plugin') }}" class="btn  btn-outline-primary ">

                <i class="fa fa-download"></i> <span> Download WordPress Plugin</span>
            </a> --}}

            <a href="{{ route('enterprise.edit') }}" class="btn  btn-outline-primary ">

                <i class="fa fa-edit"></i> <span> Edit</span>
            </a>
            @if ($enterprise->subscription->status == 1)
                <a href="{{ route('enterprise.cancel-subscription', $enterprise->subscription) }}"
                    class="btn  btn-outline-danger " onclick="return confirm('Are you sure ?')">

                    <i class="fa fa-times"></i> <span> Cancel subscription</span>
                </a>
            @else
                <a href="{{ route('enterprise.start-subscription', $enterprise->subscription) }}"
                    class="btn  btn-outline-success " onclick="return confirm('Are you sure ?')">
                    <i class="fa fa-play"></i> <span> Start subscription</span>
                </a>
            @endif


        </div>
    </div>
    <div class="row mt-5">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <table class="table">

                        <tr>
                            <th>
                                {{ __('words.key') }}
                            </th>
                            <td class="d-flex">

                                <input readonly disabled type="text" id="key" class="form-control"
                                    value="{{ $enterprise->key }}">
                                <button class="btn btn-primary btn-sm" data-text="{{ $enterprise->key }}"
                                    onclick="copyKey(this)"><i class="fa fa-copy"></i></button>
                            </td>
                        </tr>
                        @if ($enterprise->paymentMethod == 'quickpay')
                            @if ($enterprise->quickpay_api_key)
                                <tr>
                                    <th>
                                        {{ __('words.quickpay_api_key') }}
                                    </th>
                                    <td class="d-flex">

                                        <input readonly disabled type="text" id="key" class="form-control"
                                            value="{{ $enterprise->quickpay_api_key }}">
                                        <button class="btn btn-primary btn-sm"
                                            data-text="{{ $enterprise->quickpay_api_key }}"
                                            onclick="copyKey(this)"><i class="fa fa-copy"></i></button>
                                    </td>
                                </tr>
                            @endif
                            @if ($enterprise->quickpay_secret_key)
                                <tr>
                                    <th>
                                        {{ __('words.quickpay_secret_key') }}
                                    </th>
                                    <td class="d-flex">

                                        <input readonly disabled type="text" id="key" class="form-control"
                                            value="{{ $enterprise->quickpay_secret_key }}">
                                        <button class="btn btn-primary btn-sm"
                                            data-text="{{ $enterprise->quickpay_secret_key }}"
                                            onclick="copyKey(this)"><i class="fa fa-copy"></i></button>
                                    </td>
                                </tr>
                            @endif
                        @else
                            @foreach (['elavon_merchant_alias', 'elavon_public_key', 'elavon_secret_key'] as $field)
                                <tr>
                                    <th>{{ __('words.' . $field) }}</th>
                                    <td class="d-flex">
                                        <input readonly disabled type="text" id="key" class="form-control"
                                            value="{{ $enterprise->$field }}">
                                        <button class="btn btn-primary btn-sm"
                                            data-text="{{ $enterprise->$field }}" onclick="copyKey(this)"><i
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
                                    href="mailto:{{ $enterprise->company_email }}">{{ $enterprise->company_email }}</a>

                            </td>
                        </tr>
                        <tr>
                            <th>
                                {{ __('words.registration') }}
                            </th>
                            <td>
                                {{ $enterprise->company_registration }}

                            </td>
                        </tr>
                        <tr>
                            <th>
                                {{ __('words.address') }}
                            </th>
                            <td>
                                <ul style="list-style: none">
                                    @if (isset($enterprise->company_address))
                                        @foreach ($enterprise->company_address as $key => $address)
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
                                    class="badge badge-{{ $enterprise->status ? 'success' : 'danger' }}">{{ $enterprise->status ? 'On' : 'Off' }}</span>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                {{ __('words.last_paid_at') }}
                            </th>
                            <td>
                                {{ $enterprise?->last_paid_at?->format('d M, Y H:i ') ?? 'N/A' }}
                            </td>   
                        </tr>
                        <tr>
                            <th>
                                Total Paid :
                            </th>
                            <td>
                                {{ Iziibuy::price($enterprise->subscription->charges()->where('status', 1)->sum('amount') / 100) }}
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
                                    @if(@$subscriptionQuickpay?->metadata)
                                    <table class="table">

                                        @foreach ($subscriptionQuickpay->metadata as $key => $value)
                                            @if ($value)
                                                <tr>
                                                    <th>
                                                        {{ ucwords($key) }}
                                                    </th>
                                                    <td>
                                                        @if (is_array($value))
                                                            <ul>
                                                                @foreach ($value as $data)
                                                                    <li>{{ $data }}</li>
                                                                @endforeach
                                                            </ul>
                                                        @else
                                                            {{ $value }}
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach

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
</x-dashboard.enterprise>
