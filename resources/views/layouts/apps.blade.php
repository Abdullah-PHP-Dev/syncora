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

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Arabic:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('assets/css/home-assets.css') }}?v={{ filemtime(public_path('assets/css/home-assets.css')) }}">
    @vite(['resources/css/apps.css', 'resources/js/apps.js'])
    <link rel="stylesheet" href="{{ asset('assets/css/footer-social.css') }}?v={{ filemtime(public_path('assets/css/footer-social.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/marketing-header.css') }}?v={{ filemtime(public_path('assets/css/marketing-header.css')) }}">
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/hero-illustrations.css') }}?v={{ filemtime(public_path('assets/css/hero-illustrations.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/brand-theme.css') }}?v={{ filemtime(public_path('assets/css/brand-theme.css')) }}" />
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
