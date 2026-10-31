<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.google-tag-manager')
    @include('layouts.partials.google-analytics')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Socialeaz')</title>
    <meta name="description" content="@yield('meta_description', 'AI-powered social media workspace for creators, teams and agencies.')">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('assets/css/home-assets.css') }}">
    @vite(['resources/css/apps.css', 'resources/js/apps.js'])
    <link rel="stylesheet" href="{{ asset('assets/css/home-amber.css') }}?v=studio-images-icons-3">
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/hero-illustrations.css') }}?v={{ filemtime(public_path('assets/css/hero-illustrations.css')) }}">
</head>
<body class="marketing-home amber-home">
    @include('layouts.partials.google-tag-manager-noscript')
<svg class="studio-logo-filters" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><defs>
    <filter id="studio-logo-filter" color-interpolation-filters="sRGB">
        <feColorMatrix type="saturate" values="0" />
        <feComponentTransfer>
            <feFuncR type="table" tableValues="0.251 0.251 0.251 0.251 0.88 0.90 0.92 0.94 0.95 0.96 0.97 0.98 0.99 1 1 1 1 1 1 1 1" />
            <feFuncG type="table" tableValues="0.271 0.271 0.271 0.271 0.62 0.66 0.70 0.74 0.77 0.80 0.83 0.85 0.87 0.89 0.91 0.93 0.95 0.97 0.98 0.99 1" />
            <feFuncB type="table" tableValues="0.325 0.325 0.325 0.325 0.05 0.07 0.10 0.14 0.19 0.25 0.32 0.39 0.47 0.55 0.63 0.71 0.79 0.86 0.92 0.97 1" />
        </feComponentTransfer>
    </filter>
</defs></svg>

@include('layouts.partials.header_studio')

<main>
    @yield('content')
</main>

@include('layouts.partials.footer_studio')

@stack('scripts')
</body>
</html>
