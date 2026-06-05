<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>

    <link rel="stylesheet" href="{{ asset('assets/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/dashboard/css/style.css') }}">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"
        integrity="sha384-DyZ88mC6Up2uqS4h/KRgHuoeGwBcD4Ng9SiP4dIRy0EXTlnuz47vAwmeGwVChigm" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    @stack('styles')
    @livewireStyles

    <style>
        .forms {
            box-shadow: rgba(100, 100, 111, 0.2) 0px 7px 29px 0px;
            padding: 20px;
        }

        .activeBtn {
            background-color: #223557 !important;
            color: #fff;
        }

        .admin-tools-header {
            background: #223557;
            color: #fff;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
        }

        .admin-tools-header a {
            color: #fff;
        }
    </style>
</head>

<body>
    <div class="admin-tools-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="h4 mb-0">@yield('page_heading', __('Admin'))</h1>
            @hasSection('page_subheading')
                <p class="mb-0 small opacity-75">@yield('page_subheading')</p>
            @endif
        </div>
        <div class="d-flex gap-2">
            @yield('header_actions')
            <a href="{{ filament_panel_url('shops') }}" class="btn btn-light btn-sm">
                <i class="fa fa-arrow-left"></i> {{ __('Back to shops') }}
            </a>
        </div>
    </div>

    <div class="container-fluid pb-5">
        @yield('content')
    </div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="{{ asset('assets/bootstrap/js/bootstrap.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"
        integrity="sha512-VEd+nq25CkR676O+pLBnDW09R7VQX9Mdiij052gVCp5yVH3jGtH70Ho/UUv4mJDsEdTvqRCFZg0NKGiojGnUCw=="
        crossorigin="anonymous"></script>

    @if (session()->has('success') || session()->has('message'))
        <script>
            toastr.success(@json(session('success') ?? session('message')));
        </script>
    @endif

    @if ($errors->any())
        @foreach ($errors->all() as $error)
            <script>
                toastr.error(@json($error));
            </script>
        @endforeach
    @endif

    @stack('scripts')
    @livewireScripts
</body>

</html>
