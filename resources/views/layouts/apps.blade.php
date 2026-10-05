<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
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
    @stack('styles')
</head>
<body class="marketing-home">
@include('layouts.partials.header_modern')

<main>
    @yield('content')
</main>

@include('layouts.partials.footer_modern')

@stack('scripts')
</body>
</html>
