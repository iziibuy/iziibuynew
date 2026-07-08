<div class="row">
    @php
        $editable = $editable ?? false;
    @endphp

    <div class="col-md-12">
        <x-form.input type="select" name="meta[default_language]" label="{!! __('words.shop_default_language') !!}" :value="old('meta.default_language', $shop->default_language ?: 'no')" :options="['en' => 'English', 'no' => 'Norwegian', 'sv' => 'Swedish']" />
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label for="sell_digital_product">{!! __('words.product_type') !!}</label>
            @if ($editable)
                <select name="meta[sell_digital_product]" id="sell_digital_product" class="form-control">
                    <option value="1" @selected($shop->sell_digital_product == 1)>{{ __('words.digital') }}</option>
                    <option value="0" @selected($shop->sell_digital_product != 1)>{{ __('words.physical') }}</option>
                </select>
            @else
                <input type="text" readonly id="sell_digital_product" class="form-control"
                    value="{{ $shop->sell_digital_product == 1 ? __('words.digital') : __('words.physical') }}">
            @endif
        </div>
    </div>

    <div class="col-md-6 col-8">
        <div class="form-group">
            <label for="">{!! __('words.shop_username') !!}</label>
            <input name="user_name" type="text" class="form-control" value="{{ $shop->user_name }}" @readonly(! $editable)>
        </div>
    </div>
    <div class="col-md-6 col-12">
        <x-form.input type="text" name="meta[shop_name]" label="{!! __('words.shop_name') !!}" :value="old('shop_name') ?? $shop->name" />
    </div>
    <div class="col-md-6 col-12">
        <div style="position: relative">
            @if ($shop->logo != null)
                <a href="#" class="delete-icon"
                    onclick="deleteImage('metas','{{ $shop->logo }}','{{ $shop->metaId('logo') }}','column_value')">x</a>
                <img src="{{ Iziibuy::image($shop->logo) }}" alt="{{ $shop->name }}'s logo"width="100">
            @else
                <br>
            @endif
        </div>
        <x-form.input type="file" name="meta[logo]" label="{!! __('words.shop_logo') !!}" />
    </div>
    <div class="col-md-6 col-12">
        <div class="">
            @if ($shop->cover != null)
                <a href="#" class="delete-icon"
                    onclick="deleteImage('metas','{{ $shop->cover }}','{{ $shop->metaId('cover') }}','column_value')">x</a>
                <img src="{{ Iziibuy::image($shop->cover) }}" alt="{{ $shop->cover }}"width="100">
            @else
                <br>
            @endif
        </div>
        <x-form.input type="file" name="meta[cover]" label="{!! __('words.shop_cover') !!}" />
    </div>
    <div class="col-md-12 col-12">
        <x-form.input type="textarea" id="description" name="meta[description]" label="{!! __('words.shop_about') !!}" :value="old('meta.description', $shop->description)" />
    </div>
    <div class="col-md-6 col-12">
        <x-form.input type="select" name="meta[scanner_active]" label="{!! __('words.shop_scanner_status') !!}" :value="$shop->scanner_active"
            :options="[__('words.no'), __('words.yes')]" />
    </div>
    <div class="col-md-6 col-12">
        @php
            $scanner_devices = [__('words.both'), __('words.mobile'), __('words.pc')];
            $booleans = ['Yes' => __('words.yes'), 'No' => __('words.no')];
        @endphp
        <x-form.input type="select" name="meta[scanner_device]" label="{!! __('words.shop_scanner_device') !!}" :value="$shop->scanner_device"
            :options="$scanner_devices" />
    </div>
    <div class="col-md-6 col-12">
        <x-form.input type="select" name="meta[force_register]" label="{!! __('words.shop_force_register') !!}" :value="$shop->force_register"
            :options="$booleans" />
    </div>
    <div class="col-md-6 col-12">
        <x-form.input type="select" name="meta[shipping_force_register]" label="{!! __('words.shipping_force_register') !!}"
            :value="$shop->shipping_force_register" :options="$booleans" />
    </div>
    <div class="col-md-6 col-12">
        <x-form.input type="number" min="0" name="meta[package_validity]" label="{!! __('words.package_validity_days') !!}"
            :value="$shop->package_validity" />
    </div>
    <div class="col-md-6 col-12">
        <x-form.input type="number" min="1" name="meta[inactive_days]" label="{!! __('words.client_inactive_days') !!}"
            :value="$shop->inactive_days" />
    </div>
    <div class="col-md-6 col-12">
        <x-form.input type="number" min="1" name="meta[order_pending_hours]" label="{!! __('words.order_pending_hours') !!}"
            :value="$shop->order_pending_hours" />
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="">{{ __('words.qr_code_option') }}</label>
            <select name="meta[qr_code_option]" id="" class="form-control">
                <option @if ($shop->qr_code_option == 1) selected @endif value="1">
                    {{ __('words.direct_order_to_pay') }}</option>
                <option @if ($shop->qr_code_option == 2) selected @endif value="2">
                    {{ __('words.add_to_cart') }}</option>
            </select>
        </div>
    </div>
</div>
