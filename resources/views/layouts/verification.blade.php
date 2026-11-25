{{-- Minimal, distraction-free layout for the email verification screens (no site navigation). --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title')</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/images/home-theme/favicon-32.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?{{ app()->getLocale() === 'ar' ? 'family=Noto+Sans+Arabic:wght@400..800&' : '' }}family=Inter:wght@400..800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/verify-email.css') }}?v={{ filemtime(public_path('assets/css/verify-email.css')) }}">
    @stack('head')
</head>
<body class="ve-body">
<main class="ve-shell">
    <div class="ve-card">
        <header class="ve-header">
            <a href="{{ route('home') }}" class="ve-logo" aria-label="Socialeaz">
                <img src="{{ asset('assets/images/home-theme/logo.webp') }}" alt="Socialeaz" width="145" height="40">
            </a>
            <nav class="ve-header-links" aria-label="{{ __('verification.screen.need_support') }}">
                <a href="{{ route('contact') }}" class="ve-support" target="_blank" rel="noopener">
                    <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 10h.01M12 10h.01M16 10h.01"/><path d="M21 12c0 4.4-4 8-9 8a9.8 9.8 0 0 1-3.6-.7L3 20l1.4-4A7.4 7.4 0 0 1 3 12c0-4.4 4-8 9-8s9 3.6 9 8Z"/></svg>
                    <span>{{ __('verification.screen.need_support') }}</span>
                </a>
                @foreach ($locales as $l)
                    @if ($l['code'] !== app()->getLocale())
                        <a href="{{ $l['url'] }}" class="ve-lang" hreflang="{{ $l['code'] }}" lang="{{ $l['code'] }}">{{ $l['name'] }}</a>
                    @endif
                @endforeach
            </nav>
        </header>
        <section class="ve-content">
            @yield('content')
        </section>
    </div>
</main>
@stack('scripts')
</body>
</html>
