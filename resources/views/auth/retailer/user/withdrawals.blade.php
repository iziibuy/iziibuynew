<x-dashboard.retailer>
    <x-slot name="sidebar">
        @include('auth.retailer.user.includes.sidebar')
    </x-slot>
    <div class="container">
        <div class="card">
            <div class="card-body">
                <h3 class="d-print-none"><span
                        class="text-primary opacity-25 ">{{ auth()->user()->retailer->retailerType->label }}</span>
                    {{ __('words.retailer_withdreaw_sec_title') }} </h3>

                <div class="row mt-3 d-print-none">
                    <div class="col ">
                        <div class="card">
                            <div class="card-body">
                                <form action="{{ route('retailer.withdrawals') }}" method="get">
                                    <div class="row">
                                        <div class="form-group col-md-6 col-12">
                                            <label for="filter">{{ __('words.report_filter_label') }}</label>
                                            <select name="filter" class="form-control" id="">
                                                <option value="">All</option>
                                                <option value="1"
                                                    @if (1 == request()->filter) selected @endif>
                                                    Completed</option>
                                                <option value="0"
                                                    @if (0 == request()->filter) selected @endif>
                                                    Pending</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-3 col-12">
                                            <label for="from">{{ __('words.from') }}</label>
                                            <input type="date" class="form-control"
                                                @if (request()->filled('from')) value="{{ Carbon\Carbon::parse(request()->from)->format('Y-m-d') }}" @endif
                                                name="from">
                                        </div>
                                        <div class="form-group  col-md-3 col-12">
                                            <label for="to">{{ __('words.to') }}</label>
                                            <input type="date" class="form-control"
                                                @if (request()->filled('to')) value="{{ Carbon\Carbon::parse(request()->to)->format('Y-m-d') }}" @endif
                                                name="to">
                                        </div>
                                    </div>


                                    <button type="submit" class="btn btn-primary">
                                        {{ __('words.filter_btn') }}
                                    </button>
                                    <a href="{{ route('retailer.withdrawals') }}"
                                        class="btn btn-primary">{{ __('words.reset_btn') }}</a>

                                </form>
                            </div>

                        </div>
                    </div>
                </div>
                @dd('asd')
                <div class="row mt-3">
                    <div class="col">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <td colspan="4">
                                            <button class="btn btn-primary d-print-none" onclick="window.print()"><i
                                                    class="fa fa-print" aria-hidden="true"></i></button>
                                            <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                                data-bs-target="#exampleModal">
                                                {{ __('words.retailer_withdraw_req_btn') }}
                                            </button>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th>
                                            {{ __('words.date') }}
                                        </th>
                                        <th>
                                            {{ __('words.charge_amount') }}
                                        </th>
                                        <th>
                                            {{ __('words.dashboard_status') }}
                                        </th>
                                        <th>
                                            {{ __('words.cart_table_action') }}
                                        </th>

                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($withdrawals as $withdrawal)
                                        <tr>
                                            <td>
                                                {{ $withdrawal->created_at->format('d-m-Y') }}
                                            </td>
                                            <td>
                                                {{ $withdrawal->amount }} NOK
                                            </td>
                                            <th>
                                                {{ $withdrawal->status() }}
                                            </th>
                                            <th>
                                                @if ($withdrawal->status == 0)
                                                    <x-helpers.delete :url="route('retailer.withdrawal.destroy', $withdrawal)" :id="$withdrawal->id" />
                                                @endif
                                            </th>

                                        </tr>
                                    @endforeach
                                    <tr>
                                        <td colspan="4">
                                            {{ $withdrawals->links() }}
                                        </td>
                                    </tr>
                                </tbody>

                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Withdrawal request</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('retailer.withdraw') }}" method="post">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="amount">Amount (<span class="font-weight-bold">Available for withdraw
                                    {{ auth()->user()->totalBalance() }}NOK</span>)</label>
                            <input id="amount" type="number" class="form-control" name="amount" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Withdraw</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard.retailer>
