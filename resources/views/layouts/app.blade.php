<!doctype html>
<html lang="{{ app()->getLocale() }}"
      dir="{{ LaravelLocalization::getCurrentLocaleDirection() }}"
      class="layout-menu-fixed layout-compact">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>
        @yield('title', config('app.name', 'Laravel'))
    </title>

    <meta name="description" content="@yield('meta_description', '')" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/favicon/favicon.ico') }}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

    <link
            href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
            rel="stylesheet"
    />

    @php
        // rtlcss-generated mirrors of the theme's own layout CSS - only for
        // core.css/admin.css, which have zero built-in RTL support (hardcoded
        // left/right values that dir="rtl" alone doesn't flip). socialeaz-
        // admin.css is deliberately EXCLUDED from this swap: it already ships
        // its own hand-written `[dir="rtl"] .admin-sidebar {...}` overrides
        // (search that file for "RTL") that activate correctly the moment
        // <html dir="rtl"> is set below - running rtlcss over the whole file
        // double-flipped those already-correct overrides back to their wrong
        // LTR values (that selector has higher specificity than the base
        // rule, so the double-flipped version always won), which is what
        // pinned the sidebar to the left and broke the layout width in RTL.
        // Regenerate the two that ARE swapped with
        // `npm run build:rtl` if the LTR originals ever change. demo.css
        // isn't included here - it's generic widget/demo-page styling, not
        // layout chrome.
        $isRtl = LaravelLocalization::getCurrentLocaleDirection() === 'rtl';
    @endphp
    <!-- Iconify Icons -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/iconify-icons.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/select2.css') }}" />
    <!-- Core CSS -->
    <link rel="stylesheet" href="{{ asset($isRtl ? 'assets/vendor/css/core-rtl.css' : 'assets/vendor/css/core.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/demo.css') }}" />
    <link rel="stylesheet" href="{{ asset($isRtl ? 'assets/css/admin-rtl.css' : 'assets/css/admin.css') }}" />
    <link
            rel="stylesheet"
            href="{{ asset('assets/css/socialeaz-admin.css') }}"
    />
    <!-- Vendor CSS -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}" />
    <link  rel="stylesheet"  href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" >
    <!-- Helpers -->
    <script src="{{ asset('assets/vendor/js/helpers.js') }}"></script>

    <!-- Config -->
    <script src="{{ asset('assets/js/config.js') }}"></script>

    @stack('styles')
</head>

<body>

<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">

        <!-- SIDEBAR -->
        @include('layouts.partials.sidebar')

        <!-- MAIN CONTENT -->
        <div class="layout-page">

            <!-- Navbar placeholder (optional) -->
            @include('layouts.partials.navbar')

            <!-- Content -->
            <div class="content-wrapper">
                <div id="app"class="container-xxl flex-grow-1 container-p-y">

                    @yield('content')

                </div>
                @include('layouts.partials.footer')

            </div>

        </div>
    </div>
</div>
<!-- Core JS -->
<script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/popper/popper.js') }}"></script>
<script src="{{ asset('assets/vendor/js/bootstrap.js') }}"></script>

<script src="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
<script src="{{ asset('assets/vendor/js/menu.js') }}"></script>
<script src="{{ asset('assets/js/plugins/sweet-alert.js') }}"></script>
<script src="{{ asset('assets/js/plugins/select2.js') }}"></script>
<script src="{{ asset('assets/js/plugins/select2.min.js') }}"></script>
<!-- Vendors JS -->
<script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>


<script>
    document.addEventListener('DOMContentLoaded', function () {

        const navbar = document.getElementById('layout-navbar');

        if (!navbar) {
            return;
        }

        function handleNavbarScroll() {
            if (window.scrollY > 5) {
                navbar.classList.add('is-scrolled');
            } else {
                navbar.classList.remove('is-scrolled');
            }
        }

        handleNavbarScroll();

        window.addEventListener('scroll', handleNavbarScroll, {
            passive: true
        });

    });
</script>
<!-- Main JS -->
<script src="{{ asset('assets/js/main.js') }}"></script>
@vite(['resources/css/app.css', 'resources/js/app.js'])

@stack('scripts')

</body>
</html>