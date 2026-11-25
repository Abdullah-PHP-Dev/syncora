@extends('layouts.verification', ['locales' => $props['locales']])
@section('title', __('verification.screen.page_title'))

@push('head')
    @vite(['resources/js/verify-email.js'])
@endpush

@section('content')
    {{-- Vue replaces this; without JavaScript the person can still use the link in the email. --}}
    <div id="email-verification" data-props='@json($props)'>
        <div class="ve-panel">
            <h1 class="ve-title">{{ __('verification.screen.title') }}</h1>
            <p class="ve-lead">{{ __('verification.screen.sent_to') }}<br><strong dir="ltr">{{ $props['maskedEmail'] }}</strong></p>
            <noscript><p class="ve-meta">{{ __('verification.screen.or_link') }}</p></noscript>
        </div>
    </div>
@endsection
