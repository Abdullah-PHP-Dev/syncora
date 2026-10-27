<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    @include('layouts.partials.google-tag-manager')
    @include('layouts.partials.google-analytics')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/images/home-theme/favicon-32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('assets/images/home-theme/apple-touch-icon.png') }}">

    @include('layouts.partials.marketing-seo')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    {{-- Only Arabic pages use Noto Sans Arabic (.marketing-home:dir(rtl)); Inter
         still covers their Latin text. Both are variable fonts, so the
         400-800 range is one file per script, not one per weight. --}}
    <link href="https://fonts.googleapis.com/css2?{{ app()->getLocale() === 'ar' ? 'family=Noto+Sans+Arabic:wght@400..800&' : '' }}family=Inter:wght@400..800&display=swap" rel="stylesheet">

    {{-- One bundle for the layout's stylesheets (see resources/css/marketing.css). --}}
    @vite(['resources/css/marketing.css', 'resources/js/apps.js'])
    @stack('styles')
</head>
<body class="marketing-home">
    @include('layouts.partials.google-tag-manager-noscript')
@include('layouts.partials.header_modern')

<main>
    @yield('content')
</main>

@include('layouts.partials.footer_modern')

<script src="{{ asset('assets/js/marketing-header.js') }}" defer></script>
@stack('scripts')
</body>
</html>
