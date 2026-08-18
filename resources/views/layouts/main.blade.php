<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>

    <meta charset="UTF-8">

    <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
    >

    <title>
        @yield('title', 'Socialeaz')
    </title>

    <meta
            name="description"
            content="@yield('meta_description', 'The AI-powered social media workspace for creators, teams and agencies.')"
    >
    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect"
          href="https://fonts.gstatic.com"
          crossorigin>

    <link
            href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
            rel="stylesheet"
    >
    {{-- Bootstrap --}}
    <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
            rel="stylesheet"
    >

    {{-- Bootstrap Icons --}}
    <link
            href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
            rel="stylesheet"
    >

    {{-- Your CSS --}}
    <link
            rel="stylesheet"
            href="{{ asset('assets/css/socialeaz.css') }}"
    >

    @stack('styles')

</head>

<body>

{{-- Header --}}
@include('layouts.partials.header')


{{-- Page Content --}}
<main>

    @yield('content')

</main>


{{-- Footer --}}
@include('layouts.partials.footer')


{{-- Bootstrap JS --}}
<script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

{{-- Your JS --}}
<script src="{{ asset('js/socialeaz.js') }}"></script>

@stack('scripts')

</body>

</html>