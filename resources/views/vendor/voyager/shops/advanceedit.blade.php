@extends('voyager::master')

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.20/summernote-lite.min.css"
        integrity="sha512-ZbehZMIlGA8CTIOtdE+M81uj3mrcgyrh6ZFeG33A4FHECakGrOsTPlPQ8ijjLkxgImrdmSVUHn1j+ApjodYZow=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        .forms {
            box-shadow: rgba(100, 100, 111, 0.2) 0px 7px 29px 0px;
            padding: 20px
        }

        .activeBtn {
            background-color: #223557 !important;
            color: #fff;
        }
    </style>
    @livewireStyles
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"
        integrity="sha384-DyZ88mC6Up2uqS4h/KRgHuoeGwBcD4Ng9SiP4dIRy0EXTlnuz47vAwmeGwVChigm" crossorigin="anonymous">

@stop

@section('page_title', '')

@section('page_header')
    <h1 class="page-title">
        Edit Shop
    </h1>


@stop

@php
    $user = $shop->user;
@endphp

@section('content')

    <div class="page-content edit-add container-fluid">
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

                                        <div class="" style="display: block;" id="store" role="tabpanel"
                                            aria-labelledby="nav-profile-tab">

                                            @include('dashboard.shop.profile.tabs.store_info_forms', [
                                                'shop' => $shop,
                                                'editable' => true,
                                            ])
                                        </div>
                                        <div class="" style="display: none" id="payment">
                                            @include('dashboard.shop.profile.tabs.payment_info_forms', [
                                                'shop' => $shop,
                                                'editable' => true,
                                            ])
                                        </div>
                                        <div class="" style="display: none" id="company">

                                            @include('dashboard.shop.profile.tabs.company_info_forms', [
                                                'shop' => $shop,
                                                'editable' => true,
                                            ])
                                        </div>

                                        <div class="" style="display: none" id="general" role="tabpanel"
                                            aria-labelledby="nav-contact-tab">
                                            @include('dashboard.shop.profile.tabs.general_info_forms', [
                                                'shop' => $shop,
                                                'editable' => true,
                                            ])

                                        </div>
                                        <div class="" style="display: none;padding:0 20px" id="settings"
                                            role="tabpanel" aria-labelledby="nav-contact-tab">
                                            @include('dashboard.shop.profile.tabs.settings', [
                                                'shop' => $shop,
                                                'editable' => true,
                                            ])

                                        </div>
                                        <div class="" style="display: none;" id="menus" role="tabpanel"
                                            aria-labelledby="nav-contact-tab">
                                            @include('dashboard.shop.profile.tabs.menus', [
                                                'shop' => $shop,
                                                'editable' => true,
                                            ])

                                        </div>
                                        <div class="" style="display: none;padding:0 20px" id="links"
                                            role="tabpanel" aria-labelledby="nav-contact-tab">
                                            <livewire:links :shop="$shop" />

                                        </div>
                                        <div class="" id="colors" style="display: none;padding:0 20px" role="tabpanel"
                                            aria-labelledby="nav-contact-tab">
                                            @include('dashboard.shop.profile.tabs.colors', [
                                                'shop' => $shop,
                                                'editable' => true,
                                            ])

                                        </div>
                                        <button class="btn btn-primary" onclick="return $('#form').submit()"> <i
                                                class="fa fa-plus-square" aria-hidden="true"></i>
                                            {!! __('words.change_btn') !!}</button>
                                    </div>



                                </div>




                        </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>





@stop

@section('javascript')
    @livewireScripts
    <script>
        var params = {};
        var $file;



        $('document').ready(function() {
            $('.toggleswitch').bootstrapToggle();

            //Init datepicker for date fields if data-datepicker attribute defined
            //or if browser does not handle date inputs
            $('.form-group input[type=date]').each(function(idx, elt) {
                if (elt.hasAttribute('data-datepicker')) {
                    elt.type = 'text';
                    $(elt).datetimepicker($(elt).data('datepicker'));
                } else if (elt.type != 'date') {
                    elt.type = 'text';
                    $(elt).datetimepicker({
                        format: 'L',
                        extraFormats: ['YYYY-MM-DD']
                    }).datetimepicker($(elt).data('datepicker'));
                }
            });



            $('.side-body input[data-slug-origin]').each(function(i, el) {
                $(el).slugify();
            });




            $('[data-toggle="tooltip"]').tooltip();
        });
    </script>

    <script>
        // Function to store the last active tab ID in local storage
        function storeLastActiveTab(tabId) {


            const tabs = ['store', 'company', 'settings', 'links', 'menus', 'payment', 'general','colors'];
            for (const tab of tabs) {
                if (tab == tabId) {
                    $("#" + tab + "-tab").addClass('activeBtn');

                    $("#" + tab).show();
                } else {
                    $("#" + tab).hide();
                    $("#" + tab + "-tab").removeClass('activeBtn');
                }
            }

            localStorage.setItem('lastActiveTab', tabId);
            console.log(localStorage.getItem('lastActiveTab'))
        }

        // Function to retrieve the last active tab ID from local storage
        function getLastActiveTab() {
            return localStorage.getItem('lastActiveTab');
        }
    </script>
    <script>
        $(document).ready(function() {
            $('#terms').summernote({
                height: 300
            });
            $('#description').summernote({
                height: 300
            });

            const lastActiveTab = getLastActiveTab();

            console.log(document.getElementById(lastActiveTab + '-tab'));
            if (lastActiveTab) {
                // Activate the last active tab
                const lastActiveButton = document.getElementById(lastActiveTab + '-tab');
                if (lastActiveButton) {
                    lastActiveButton.click();
                }
            }

        });
    </script>
    <script>
        if ($("select[name='selling_location_mode']").val() < 1) {
            $('#locationsdiv').hide()
        }

        $("select[name='selling_location_mode']").change(e => {
            $('#locationsdiv').hide()
            if (e.target.value > 0) {
                $('#locationsdiv').show()
            }
        })
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.20/summernote-lite.min.js"
        integrity="sha512-lVkQNgKabKsM1DA/qbhJRFQU8TuwkLF2vSN3iU/c7+iayKs08Y8GXqfFxxTZr1IcpMovXnf2N/ZZoMgmZep1YQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        $('#description').summernote();
    </script>
@stop
