<x-dashboard.retailer>
    <x-slot name="sidebar">
        @include('auth.retailer.user.includes.sidebar')
    </x-slot>
    <div class="container">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="card-header">
                            {{ __('words.retailer_profile_sec_title') }}
                        </div>
                        <div class="card card-stats mb-4 p-3">
                            <form action="{{ route('retailer.update_profile') }}" method="post">
                                @csrf
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label
                                                for="name">{{ __('words.checkout_form_first_name_label') }}</label>
                                            <input type="text" value="{{ old('name', auth()->user()->name) }}"
                                                name="name" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="last_name">{{ __('words.checkout_form_lastname') }}</label>
                                            <input type="text"
                                                value="{{ old('last_name', auth()->user()->last_name) }}"
                                                name="last_name" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label
                                                for="bank_account_number">{{ __('words.retailer_profile_bank_label') }}</label>
                                            <input type="text"
                                                value="{{ old('bank_account_number', auth()->user()->retailer->bank_account_number) }}"
                                                name="bank_account_number" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="email">{{ __('words.checkout_form_email') }}</label>
                                            <input type="email" value="{{ old('email', auth()->user()->email) }}"
                                                name="email" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="password">{{ __('words.password') }}</label>
                                            <input type="text" value="" name="password" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <button type="submit"
                                            class="btn btn-success">{{ __('words.cart_table_update_btn') }}</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
  

</x-dashboard.retailer>
