@props(['admin' => false])
@php
    $admin = $admin || ($editable ?? false);
@endphp
<div class="row">
    <div class="col-md-6">
        <x-form.input type="text" name="meta[name]" label="{!! __('words.shop_username') !!}" :value="old('name', $shop->name)" />
    </div>
    <div class="col-md-6 col-12">
        <x-form.input type="email" name="meta[contact_email]" label="{!! __('words.shop_contact_num') !!}" :value="old('contact_email', $shop->contact_email)" />
    </div>
    <div class="col-md-6 col-12">
        <x-form.input type="text" name="meta[contact_phone]" label="{!! __('words.shop_contact_email') !!}" :value="old('contact_phone', $shop->contact_phone)" />
    </div>
    <div class="col-md-6 col-12">
        <x-form.input type="text" name="meta[company_name]" label="{!! __('words.shop_company_name') !!}" :value="old('company_name', $shop->company_name)" />
    </div>
    <div class="col-md-6 col-12">
        <x-form.input type="text" name="meta[company_registration]" label="{!! __('words.shop_reg_num') !!}"
            :value="old('company_registration', $shop->company_registration)" />
    </div>
    @if ($admin)
        <div class="col-md-6 col-12">
            <x-form.input type="text" name="meta[company_url]" label="{!! __('words.company_url') !!}"
                :value="old('company_url', $shop->company_url)" />
        </div>
    @endif
    <div class="col-md-6 col-12">
        <x-form.input type="text" name="meta[city]" label="{!! __('words.city') !!}" :value="old('city', $shop->city)" />
    </div>
    <div class="col-md-6 col-12">
        <x-form.input type="text" name="meta[street]" label="{!! __('words.street') !!}" :value="old('street', $shop->street)" />
    </div>
    <div class="col-md-6 col-12">
        <x-form.input type="text" name="meta[post_code]" label="{!! __('words.invoice_postcode') !!}" :value="old('post_code', $shop->post_code)" />
    </div>
    <div class="col-md-12 mb-3">
        <label for="country">{{ __('words.invoice_country') }}</label>
        <select name="country" id="country" class="form-control">
            @foreach (App\Constants\Constants::COUNTRIES as $country)
                <option value="{{ $country }}" @selected($country == $shop->country)>{{ $country }}</option>
            @endforeach
        </select>
    </div>

</div>
