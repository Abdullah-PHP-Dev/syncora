<!doctype html>
<html lang="en"
      class="layout-menu-fixed layout-compact">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>
        @yield('title', config('app.name', 'Laravel'))
    </title>

    <meta name="description" content="@yield('meta_description', '')" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/favicon/favicon.ico') }}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />

    <!-- Iconify Icons -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/iconify-icons.css') }}" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/core.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/demo.css') }}" />

    <!-- Vendor CSS -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}" />

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
                <div class="container-xxl flex-grow-1 container-p-y">

                    @yield('content')

                </div>
                <footer class="content-footer footer bg-footer-theme">
                    <div class="container-xxl">
                        <div
                                class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
                            <div class="mb-2 mb-md-0">
                                &#169;
                                <script>
                                    document.write(new Date().getFullYear());
                                </script>
                                , made with ❤️ by
                                <a href="https://themeselection.com" target="_blank" class="footer-link">ThemeSelection</a>
                            </div>
                            <div class="d-none d-lg-inline-block">
                                <a
                                        href="https://themeselection.com/item/category/admin-templates/"
                                        target="_blank"
                                        class="footer-link me-4"
                                >Admin Templates</a
                                >

                                <a href="https://themeselection.com/license/" class="footer-link me-4" target="_blank">License</a>
                                <a
                                        href="https://themeselection.com/item/category/bootstrap-admin-templates/"
                                        target="_blank"
                                        class="footer-link me-4"
                                >Bootstrap Dashboard</a
                                >

                                <a
                                        href="https://demos.themeselection.com/sneat-bootstrap-html-admin-template/documentation/"
                                        target="_blank"
                                        class="footer-link me-4"
                                >Documentation</a
                                >

                                <a
                                        href="https://github.com/themeselection/sneat-bootstrap-html-admin-template-free/issues"
                                        target="_blank"
                                        class="footer-link"
                                >Support</a
                                >
                            </div>
                        </div>
                    </div>
                </footer>
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

<!-- Vendors JS -->
<script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>

<!-- Main JS -->
<script src="{{ asset('assets/js/main.js') }}"></script>


@stack('scripts')

</body>
</html>