<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <title>{{ env('APP_NAME') }}</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="robots" content="noindex, follow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"
        integrity="sha384-DyZ88mC6Up2uqS4h/KRgHuoeGwBcD4Ng9SiP4dIRy0EXTlnuz47vAwmeGwVChigm" crossorigin="anonymous">
    <script defer src="https://use.fontawesome.com/releases/v5.15.4/js/all.js"
        integrity="sha384-rOA1PnstxnOBLzCLMcre8ybwbTmemjzdNlILg8O7z1lUkLXozs4DHonlDtnE7fpc" crossorigin="anonymous">
    </script>

    <link rel="stylesheet" href="{{ asset('assets/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/dashboard/css/style.css') }}">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.css">
    @stack('styles')
    <style>
        .delete-icon {
            position: absolute;
            top: -10px;
            background: #f0e5e5;
            padding: 2px 2px;
            line-height: 12px;
            border-radius: 19%;
        }

        .shop_details {
            font-size: 16px !important;
            border-bottom: 2px solid #000;
            padding-bottom: 5px
        }

        .customer_details {
            line-height: 1px !important;
            margin-bottom: 2px
        }
    </style>
</head>

<body>

    <div class="wrapper d-flex align-items-stretch">
        <nav id="sidebar" class="d-print-none">
            <div class="custom-menu">
                <button type="button" id="sidebarCollapse" class="btn btn-primary">
                    <i class="fa fa-bars"></i>
                    <span class="sr-only">Toggle Menu</span>
                </button>
            </div>
            <div class="p-4">


                <h4><a target="_blank" href="{{ route('shop.home', ['user_name' => auth()->user()->shop->user_name]) }}"
                        class="logo text-light">{{ auth()->user()->shop->name }} </a></h4>
                <ul class="list-unstyled components mb-5 d-print-none">



                    <x-dashboard.nav.item :link="route('shop.dashboard')" :title="__('words.dashboard')" id="dashboard" icon="fa-tachometer-alt"
                        :active="true" />
                    <x-dashboard.nav.item :hasDropdown="true" :title="__('words.dashboard_products_title')" id="products" icon="fa-arrow-right"
                        :active="true">
                        <x-dashboard.nav.item :link="route('shop.products.index')" :title="__('words.products_sec_title')" id="dashboard"
                            icon="fa-shopping-bag" />
                        <x-dashboard.nav.item :link="route('shop.categories.index')" :title="__('words.products_category_sec_title')" id="categories" icon="fa-list" />
                        <x-dashboard.nav.item :link="route('shop.boxes.index')" :title="__('words.products_boxes_sec_title')" id="boxes" icon="fa-boxes" />
                        <x-dashboard.nav.item :link="route('shop.storage.index')" :title="__('words.store_index_sec_title')" id="categories"
                            icon="fas fa-store mr-3" />
                    </x-dashboard.nav.item>


                    <x-dashboard.nav.item :hasDropdown="true" :title="__('words.store_index_sec_title_sidebar')" id="store" icon="fa-arrow-right"
                        :active="true">
                        <x-dashboard.nav.item :link="route('shop.store.profile')" :title="__('words.profile_sec_title')" id="profile"
                            icon="fa-solid fa-user" :active="true" />
                        <x-dashboard.nav.item :link="route('shop.managers')" :title="__('words.dashboard_managers')" id="managers" icon="fa-users-cog"
                            :active="Permission::check('manager', 'browse')" />
                        <x-dashboard.nav.item :link="route('shop.sliders.index')" :title="__('words.shop_slider_tsec_title')" id="slider" icon="fa-images"
                            :active="Permission::check('slider', 'browse')" />
                        <x-dashboard.nav.item :link="route('shop.translations')" :title="__('words.store_translations_sec_title')" icon="fa-solid fa-language" />
                        <x-dashboard.nav.item :link="route('shop.shop_translations')" :title="__('words.store_lang_sec_title')" icon="fa-solid fa-language" />
                        {{-- <x-dashboard.nav.item :link="route('shop.qrcodes.index')" :title="__('words.qrcodes_sec_title')" icon="fa-solid fa-qrcode" /> --}}
                        <x-dashboard.nav.item :link="route('shop.shippings.index')" :title="__('words.checkout_shipping')" id="shipping"
                            icon="fas fa-truck mr-3" :active="Permission::check('shipping', 'browse')" />

                    </x-dashboard.nav.item>

                    <x-dashboard.nav.item :hasDropdown="true" :title="__('words.my_finance')" id="finance" icon="fa-arrow-right"
                        :active="true">
                        <x-dashboard.nav.item :link="route('shop.order.index')" :title="__('words.shop_orders')" id="orders"
                            icon="fas fa-cash-register mr-3" :active="true" />
                        <x-dashboard.nav.item :link="route('shop.coupon.index')" :title="__('words.dashboard_coupons')" id="coupons"
                            icon="fas fa-tags mr-3" :active="Permission::check('coupon', 'browse')" />
                        <x-dashboard.nav.item :link="route('shop.report.index')" :title="__('words.reports_sec_title')" id="report"
                            icon="fas fa-chart-bar mr-3" :active="true" />

                        <x-dashboard.nav.item :link="route('shop.charges.index')" :title="__('words.subs_show_sec_title')" id="charges"
                            icon="fas fa-file-invoice-dollar mr-3" :active="true" />
                        <x-dashboard.nav.item :hasDropdown="true" :title="__('words.contract')" id="contract" icon="fa-arrow-right"
                            :active="true">
                            <x-dashboard.nav.item :link="route('shop.complete.signup')" :title="__('words.quickpay_contract')" id="completeSignUp"
                                icon="fas fa-chart-bar mr-3" :active="true" />
                            <x-dashboard.nav.item :link="route('shop.setup_payment_two')" :title="__('words.two_payment_menu')" id="paymentTwo"
                                icon="fas fa-chart-bar mr-3" :active="true" />
                        </x-dashboard.nav.item>


                    </x-dashboard.nav.item>




                    <x-dashboard.nav.item :hasDropdown="true" :title="__('words.service_index_sec_title')" id="booking" icon="fa-arrow-right"
                        :active="Permission::check('booking', 'browse') && auth()->user()->shop->can_provide_service">
                        <x-dashboard.nav.item :link="route('shop.manage-schedule.index')" :title="__('words.manage_schedule_title')" id="calender"
                            icon="fa fa-calendar mr-3" :active="true" />
                        <x-dashboard.nav.item :link="route('shop.booking.callender')" :title="__('words.calender_sec_title')" id="calender"
                            icon="fa fa-calendar mr-3" :active="true" />
                        <x-dashboard.nav.item :link="route('shop.booking.index')" :title="__('words.reservations')" id="booking"
                            icon="fas fa-tags mr-3" :active="true" />
                        <x-dashboard.nav.item :link="route('shop.booking.services.index')" :title="__('words.shop_nav_booking')" id="service"
                            icon="fas fa-user mr-3" :active="true" />
                        <x-dashboard.nav.item :link="route('shop.booking.price-groups.index')" :title="__('words.groups')" id="groups"
                            icon="fas fa-tags mr-3" :active="true" />
                        <x-dashboard.nav.item :link="route('shop.booking.resources.index')" :title="__('words.resource_label')" id="groups"
                            icon="fas fa-chart-bar mr-3" :active="true" />
                        <x-dashboard.nav.item :link="route('shop.booking.client.index')" :title="strip_tags(__('words.clients'))" id="clientIndex" icon="fas fa-users"
                            :active="Permission::check('personal_trainee', 'browse')" />
                        <x-dashboard.nav.item :link="route('shop.pt.report')" :title="__('words.pt_report')" id="groups"
                            icon="fas fa-chart-bar mr-3" :active="true" />
                    </x-dashboard.nav.item>

                    <x-dashboard.nav.item :hasDropdown="true" :title="__('words.personal_trainer')" id="personalTrainer"
                        icon="fa-arrow-right" :active="Permission::check('personal_trainee', 'browse') &&
                            auth()->user()->shop->can_provide_service">
                        <x-dashboard.nav.item :link="route('shop.levels.index')" :title="__('words.dashboard_levels')" id="levels"
                            icon="fa-solid fa-bars" :active="true" />
                        <x-dashboard.nav.item :link="route('shop.packageoptions.index')" :title="__('words.package_options')" id="packageOption"
                            icon="fa-solid fa-mars-double" :active="true" />
                        <x-dashboard.nav.item :link="route('shop.packages.index')" :title="__('words.packages')" id="packages"
                            icon="fa-solid fa-industry" :active="true" />
                    </x-dashboard.nav.item>





                    <x-dashboard.nav.item :active="Permission::check('personal_trainee', 'browse') &&
                        auth()->user()->shop->can_provide_service" :link="route('manager.dashboard')" icon="fa-solid fa-user"
                        :title="__('words.manager_dashboard')" id="manager" />
                    <x-dashboard.nav.item :link="route('shop.service.subscription')" icon="fa-arrow-right"
                        title=" <b>{!! __('words.not_provide_sevice_msg') !!}</b> <br>{{ __('words.not_provide_sevice_msg_2') }}"
                        :active="!auth()->user()->shop->can_provide_service" id="manager" />
                    <x-dashboard.nav.item :link="route('tickets.index')" icon="fas fa-file-alt" :title="__('words.ticket_index')"
                        :active="true" id="ticket" />




                    <x-language5 />
                    <li>
                        <a href="{{ route('logout') }}"
                            onclick="event.preventDefault();
            document.getElementById('logout-form').submit();">
                            <span class="fas fa-toggle-off mr-3"></span>{{ __('words.logout') }}</a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                            @csrf
                        </form>
                    </li>
                    @if (session()->has('login_redirect'))
                        <li class="">
                            <a href="{{ session()->get('login_redirect') }}">
                                <span class="fas fa-home mr-3"></span>
                                {{ __('words.return_back_to_shop') }}</a>
                        </li>
                    @endif
                    </li>

                    <div class="footer mt-5">
                        <p>
                            {!! __('words.copy_write_msg_1') !!} {{ now()->format('Y') }} {{ __('words.copy_write_msg_2') }}
                        </p>
                    </div>
            </div>
        </nav>

        <div id="content" class="p-md-5 pt-5">
            <div class="container-fluid">
                @if (($trialShop = auth()->user()->shop)?->onTrial())
                    <div class="alert alert-info d-flex justify-content-between align-items-center">
                        <span>{{ __('words.trial_days_left', ['days' => $trialShop->trialDaysLeft()]) }}</span>
                        <a href="{{ route('shop.subscription.payment') }}"
                            class="btn btn-sm btn-primary">{{ __('words.subscribe_now') }}</a>
                    </div>
                @endif
                {{ $slot }}
            </div>
            <form action="" method="post" id="delete-form" style="dislplay:none">
                @csrf
                @method('delete')
            </form>


        </div>

    </div>
    <script src="{{ asset('assets/dashboard/js/main.js') }}"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="{{ asset('assets/dashboard/js/popper.js') }}"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous">
    </script>
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('assets/dashboard/js/main.js') }}"></script>
    <script defer="defer" src="{{ asset('assets/dashboard/js/beacon.js') }}"
        data-cf-beacon="{&quot;rayId&quot;:&quot;68eb5ba4796d4d9f&quot;,&quot;token&quot;:&quot;cd0b4b3a733644fc843ef0b185f98241&quot;,&quot;version&quot;:&quot;2021.8.1&quot;,&quot;si&quot;:10}">
    </script>


    <script>
        function deleteImage(table, filename, id, field) {
            console.log(table);
            $.ajax({
                type: 'post',
                url: "{{ route('shop.remove.media') }}",
                data: {
                    'table': table,
                    'filename': filename,
                    'id': id,
                    'field': field,
                    '_token': '{{ csrf_token() }}'
                },
                success: function(response) {
                    location.reload();
                }
            })
        }
    </script>
    <script>
        function lan(e) {
            var currentUrl = window.location.href;
            var url = new URL(currentUrl);
            url.searchParams.set("lang", e);
            var newUrl = url.href;
            window.location = newUrl;
        }
    </script>
    <script type="text/javascript">
        function printDiv(divName) {

            var printContents = divName.parentNode.parentNode.children[0].innerHTML;
            var originalContents = document.body.innerHTML;
            document.body.innerHTML = printContents;
            window.print();
            document.body.innerHTML = originalContents;
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#locations').select2();
        });
    </script>
    <script>
        function cskDelete(url) {
            event.preventDefault();
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    let deleteForm = document.getElementById('delete-form');
                    deleteForm.action = url;
                    deleteForm.submit();
                }
            })
        }
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"
        integrity="sha512-VEd+nq25CkR676O+pLBnDW09R7VQX9Mdiij052gVCp5yVH3jGtH70Ho/UUv4mJDsEdTvqRCFZg0NKGiojGnUCw=="
        crossorigin="anonymous"></script>

    @if (session()->has('success'))
        <script>
            toastr.success("{{ session('success') }}")
        </script>
    @endif
    @if ($errors->any())
        @foreach ($errors->all() as $error)
            <script>
                toastr.error('{{ $error }}')
            </script>
        @endforeach
    @endif

    @stack('scripts')
    <script defer src="{{ asset('js/app.js') }}"></script>
    <script>
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    </script>
    <script src="https://tally.so/widgets/embed.js"></script>

    @if (auth()->user()->shop->needKYC == true)
        <script>
            Tally.openPopup('3y6Nex', {
                layout: 'modal', // Open as a centered modal
                width: 700, // Set the width of the modal
                onSubmit: () => {
                    window.location.href = "{{ route('shop.disablekyc') }}"
                },
            });
        </script>
    @endif
</body>

</html>
