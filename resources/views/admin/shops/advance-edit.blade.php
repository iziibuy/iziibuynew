@extends('layouts.admin-tools')

@section('title', __('Advance edit') . ' — ' . $shop->user_name)
@section('page_heading', __('Advance edit'))
@section('page_subheading', $shop->user_name)

@section('content')
    @php
        $user = $shop->user;
    @endphp

    <div class="row">
        <div class="col-md-12">
            <nav>
                <div class="nav nav-tabs pl-3" id="nav-tab" role="tablist" style="padding: 10px 0 10px 30px">
                    <button class="nav-link btn btn-primary" id="company-tab" type="button"
                        onclick="storeLastActiveTab('company')">{{ __('words.company') }}</button>
                    <button class="nav-link btn btn-primary" id="store-tab" data-bs-target="#store" type="button"
                        onclick="storeLastActiveTab('store')">{{ __('words.store') }}</button>
                    <button class="nav-link btn btn-primary" data-bs-toggle="tab" id="payment-tab"
                        data-bs-target="#payment" type="button" role="tab" aria-controls="nav-contact"
                        aria-selected="false" onclick="storeLastActiveTab('payment')">{{ __('words.payment') }}</button>
                    <button class="nav-link btn btn-primary" data-bs-toggle="tab" id="general-tab"
                        data-bs-target="#general" type="button" role="tab" aria-controls="nav-contact"
                        aria-selected="false" onclick="storeLastActiveTab('general')">{{ __('words.general') }}</button>
                    <button class="nav-link btn btn-primary" data-bs-toggle="tab" id="menus-tab" data-bs-target="#menus"
                        type="button" role="tab" aria-controls="nav-contact" aria-selected="false"
                        onclick="storeLastActiveTab('menus')">{{ __('words.menus') }}</button>
                    <button class="nav-link btn btn-primary" data-bs-toggle="tab" id="settings-tab"
                        data-bs-target="#settings" type="button" role="tab" aria-controls="nav-contact"
                        aria-selected="false"
                        onclick="storeLastActiveTab('settings')">{{ __('words.settings') }}</button>
                    <button class="nav-link btn btn-primary" data-bs-toggle="tab" id="links-tab" data-bs-target="#links"
                        type="button" role="tab" aria-controls="nav-contact" aria-selected="false"
                        onclick="storeLastActiveTab('links')">{{ __('words.links') }}</button>
                    <button class="nav-link btn btn-primary" data-bs-toggle="tab" id="colors-tab" data-bs-target="#colors"
                        type="button" role="tab" aria-controls="nav-contact" aria-selected="false"
                        onclick="storeLastActiveTab('colors')">{{ __('words.colors') }}</button>
                </div>
            </nav>

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <form id="form" action="{{ route('admin.profile.update', $shop) }}" method="POST"
                            enctype="multipart/form-data">
                            @csrf

                            <div class="card-body shadow-lg">
                                <div class="forms" id="nav-tabContent">
                                    <div style="display: block;" id="store" role="tabpanel"
                                        aria-labelledby="nav-profile-tab">
                                        @include('dashboard.shop.profile.tabs.store_info_forms', [
                                            'shop' => $shop,
                                        ])
                                    </div>
                                    <div style="display: none" id="payment">
                                        @include('dashboard.shop.profile.tabs.payment_info_forms', [
                                            'shop' => $shop,
                                        ])
                                    </div>
                                    <div style="display: none" id="company">
                                        @include('dashboard.shop.profile.tabs.company_info_forms', [
                                            'shop' => $shop,
                                        ])
                                    </div>
                                    <div style="display: none" id="general" role="tabpanel"
                                        aria-labelledby="nav-contact-tab">
                                        @include('dashboard.shop.profile.tabs.general_info_forms', [
                                            'shop' => $shop,
                                        ])
                                    </div>
                                    <div style="display: none; padding: 0 20px" id="settings" role="tabpanel"
                                        aria-labelledby="nav-contact-tab">
                                        @include('dashboard.shop.profile.tabs.settings', [
                                            'shop' => $shop,
                                        ])
                                    </div>
                                    <div style="display: none;" id="menus" role="tabpanel"
                                        aria-labelledby="nav-contact-tab">
                                        @include('dashboard.shop.profile.tabs.menus', [
                                            'shop' => $shop,
                                        ])
                                    </div>
                                    <div style="display: none; padding: 0 20px" id="links" role="tabpanel"
                                        aria-labelledby="nav-contact-tab">
                                        <livewire:links :shop="$shop" />
                                    </div>
                                    <div id="colors" style="display: none; padding: 0 20px" role="tabpanel"
                                        aria-labelledby="nav-contact-tab">
                                        @include('dashboard.shop.profile.tabs.colors', [
                                            'shop' => $shop,
                                        ])
                                    </div>

                                    <button class="btn btn-primary" type="submit">
                                        <i class="fa fa-plus-square" aria-hidden="true"></i>
                                        {!! __('words.change_btn') !!}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.20/summernote-lite.min.css"
        integrity="sha512-ZbehZMIlGA8CTIOtdE+M81uj3mrcgyrh6ZFeG33A4FHECakGrOsTPlPQ8ijjLkxgImrdmSVUHn1j+ApjodYZow=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
@endpush

@push('scripts')
    <script>
        function storeLastActiveTab(tabId) {
            const tabs = ['store', 'company', 'settings', 'links', 'menus', 'payment', 'general', 'colors'];

            for (const tab of tabs) {
                if (tab === tabId) {
                    $('#' + tab + '-tab').addClass('activeBtn');
                    $('#' + tab).show();
                } else {
                    $('#' + tab).hide();
                    $('#' + tab + '-tab').removeClass('activeBtn');
                }
            }

            localStorage.setItem('lastActiveTab', tabId);
        }

        function getLastActiveTab() {
            return localStorage.getItem('lastActiveTab');
        }
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.20/summernote-lite.min.js"
        integrity="sha512-lVkQNgKabKsM1DA/qbhJRFQU8TuwkLF2vSN3iU/c7+iayKs08Y8GXqfFxxTZr1IcpMovXnf2N/ZZoMgmZep1YQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        $(document).ready(function() {
            $('#terms').summernote({
                height: 300,
            });
            $('#description').summernote({
                height: 300,
            });

            $('#locations').select2();

            const lastActiveTab = getLastActiveTab();

            if (lastActiveTab) {
                const lastActiveButton = document.getElementById(lastActiveTab + '-tab');

                if (lastActiveButton) {
                    lastActiveButton.click();
                }
            }

            if ($("select[name='selling_location_mode']").val() < 1) {
                $('#locationsdiv').hide();
            }

            $("select[name='selling_location_mode']").change(function(event) {
                $('#locationsdiv').hide();

                if (event.target.value > 0) {
                    $('#locationsdiv').show();
                }
            });
        });
    </script>
@endpush
