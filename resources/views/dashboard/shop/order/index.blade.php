<x-dashboard.shop>
    <h3><span class="text-primary opacity-25">{!! __('words.shop_orders') !!}</span></h3>
    <div class="card">
        <div class="card-header rounded bg-transparent p-0 m-0">
            <form action="{{ route('shop.order.index') }}" method="get">

                <div class="input-group">
                    <div class="input-group-prpend">
                        <select class="form-control" name="payment_status">
                            <option value="" selected>
                                {!! __('words.paid_or_unpaid') !!}

                            </option>

                            <option value="1" @if (request()->payment_status === '1') selected @endif>
                                {!! __('words.paid') !!}
                            </option>
                            <option value="0" @if (request()->payment_status === '0') selected @endif>
                                {!! __('words.unpaid') !!}
                            </option>
                        </select>
                    </div>
                    <select class="form-control" name="status">
                        <option value="all" @if (request()->status === 'all') selected @endif>{!! __('words.status_all') !!}
                        </option>
                        <option value="4" @if (request()->status === '4') selected @endif>{!! __('words.not_delivered') !!}
                        </option>
                        <option value="5" @if (request()->status === '5') selected @endif>{!! __('words.delivered') !!}
                        </option>
                    </select>

                    <div class="input-group-append">
                        <button class="btn btn-outline-primary"><i class="fa fa-filter" aria-hidden="true"></i></button>
                    </div>
                </div>
            </form>
            <form action="{{ route('shop.order.index') }}" method="get">
                <div class="input-group">
                    <input value="{{ request('search') }}" type="text" class="form-control"
                        placeholder="{{ __('words.search') }}" aria-label="Search" aria-describedby="basic-addon2"
                        name="search">
                    <div class="input-group-append">
                        <button class="btn btn-outline-primary"><i class="fa fa-search" aria-hidden="true"></i></button>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-body">
            @if ($orders->count() > 0)
                <div class="table-responsive">
                    <table class="table">
                        <thead>

                            <tr>
                                <th>{!! __('words.orders_order_date') !!}</th>
                                <th>{!! __('words.orders_id') !!}</th>
                                <th>
                                    {!! __('words.invoice_customer_details') !!}
                                </th>
                                <th>
                                    {!! __('words.invoice_address') !!}
                                </th>
                                <th>{!! __('words.cart_account_table_title') !!}</th>
                                <th>{{ __('Platform commission') }}</th>
                                <th>{!! __('words.orders_status') !!}</th>
                                <th>{!! __('words.orders_invoice') !!}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $order)
                                <tr>
                                    <td>{{ $order->created_at->format('d M Y') }}</td>
                                    <td> <span>{{ $order->id }}</span> <br> <small
                                            class="badge badge-primary">{{ $order->payment_method }}</small>
                                    </td>
                                    <td>First name : {{ $order->first_name }} <br>
                                        Last name : {{ $order->last_name }} <br>
                                        Email : {{ $order->email }} <br>
                                        Phone : {{ $order->phone }}
                                    </td>
                                    <td>
                                        State : {{ $order->state }} <br>
                                        City : {{ $order->city }} <br>
                                        Post Code : {{ $order->post_code }} <br>
                                        Address : {{ $order->address }}
                                    </td>



                                    <td>{{ Iziibuy::withSymbol($order->total, $order->currency) }}</td>
                                    <td>{{ Iziibuy::withSymbol($order->platform_amount, $order->currency) }}
                                        <br><small class="text-muted">{{ rtrim(rtrim(number_format($order->platform_percentage, 2), '0'), '.') }}%</small>
                                    </td>
                                    <td>
                                        <button
                                            class="badge @if ($order->status == 5) badge-success @elseif($order->status == 0) badge-warning @elseif($order->status == 3) badge-danger  @else badge-primary @endif border border-0">
                                            {!! $order->status() !!}
                                        </button>
                                    </td>
                                    <td class="d-flex flex-wrap justify-content-between">
                                        <div class="btn-group d-block mt-2">
                                            <a href="{{ route('shop.invoice', ['order' => $order->id]) }}"
                                                class="btn btn-info">{!! __('words.orders_invoice_btn') !!}</a>

                                            <a href="{{ route('shop.invoice.download', ['order' => $order->id]) }}"
                                                class="btn btn-info">{!! __('words.orders_download_btn') !!}</a>
                                            <button class="btn btn-primary"
                                                data-print=" <span class='shop_details'>{{ $order->shop->name . ' ' . $order->shop->street . ' ' . $order->shop->post_code . ' ' . $order->shop->country }}</span> <br> <span class='customer_details'> {{ $order->first_name }} {{ $order->last_name }} <br>  {{ $order->address }} <br> {{ $order->post_code }} {{ $order->city }} </span>"
                                                onClick="getprint(this)"><i class="fa fa-print"></i></button>
                                            @if (!in_array($order->status, [2, 3]) && $order->payment_status == 1)
                                                
                                                <a class="btn btn-danger "
                                                    href="{{ route('shop.orders.cancel', $order) }}"><i
                                                        class="fa fa-times-circle mr-2"></i>{{ __('words.cancel') }}</a>
                                            @endif

                                        </div>
                                        <form action="{{ route('shop.captureOrder', $order) }}" method="post">
                                            @csrf
                                            <select name="shipped" @if ($order->status != 4 || $order->payment_status == 0) disabled @endif
                                                id="" style="padding:5px" class="mt-2">
                                                <option value="0"
                                                    @if ($order->status == 4) selected @endif>
                                                    {{ __('words.not_delivered') }}
                                                </option>
                                                <option value="1"
                                                    @if ($order->status == 5) selected @endif>
                                                    {{ __('words.delivered') }}</option>
                                            </select>
                                            <input type="submit" name="submit" value=" {{ __('words.save') }}">
                                        </form>



                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $orders->appends(request()->query())->links() }}
            @else
                <h3 class="text-center">{!! __('words.order_not_found_mssg') !!} </h3>
            @endif

        </div>
    </div>
    @push('scripts')
        <script>
            function getprint(e) {
                var printContents = e.dataset.print.split(',').join('<br>');
                var originalContents = document.body.innerHTML;
                var div = document.createElement('div');
                div.style.fontSize = "24px";
                div.style.color = "#000000";
                div.innerHTML = printContents
                document.body.innerHTML = '';
                document.body.append(div);
                window.print();
                document.body.innerHTML = originalContents;
            }
        </script>
    @endpush
</x-dashboard.shop>
