<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    @include('layouts.partials.google-analytics')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/images/home-theme/favicon-32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('assets/images/home-theme/apple-touch-icon.png') }}">

    @include('layouts.partials.marketing-seo')

    @include('layouts.partials.fonts')

    {{-- One bundle for the layout's stylesheets (see resources/css/marketing.css). --}}
    @vite(['resources/css/marketing.css', 'resources/js/apps.js'])
    @stack('styles')
</head>
<body class="marketing-home">
@include('layouts.partials.header_modern')

<main>
    @yield('content')
</main>

@include('layouts.partials.footer_modern')

<script src="{{ asset('assets/js/marketing-header.js') }}" defer></script>
@stack('scripts')
</body>
</html>
