@extends('layouts.verification')
@section('title', __('verification.link.page_title'))

@section('content')
@php
    $titles = ['confirm' => 'confirm_title', 'done' => 'done_title', 'already' => 'already_title', 'expired' => 'expired_title', 'invalid' => 'invalid_title'];
    $tone = in_array($state, ['done', 'already'], true) ? 'is-success' : (in_array($state, ['expired', 'invalid'], true) ? 'is-warning' : '');
@endphp
<div class="ve-panel">
    <div class="ve-icon {{ $tone }}" aria-hidden="true">
        @if (in_array($state, ['done', 'already'], true))
            <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        @elseif (in_array($state, ['expired', 'invalid'], true))
            <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 16.5v.5"/></svg>
        @else
            <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
        @endif
    </div>
    <h1 class="ve-title">{{ __('verification.link.'.$titles[$state]) }}</h1>

    @if ($state === 'confirm')
        <p class="ve-lead">{!! __('verification.link.confirm_body', ['email' => '<strong dir="ltr">'.e($maskedEmail).'</strong>']) !!}</p>
        {{-- Opening the link never verifies: mail scanners open links too. Pressing this does. --}}
        <form method="POST" action="{{ route('verification.link.confirm', ['token' => $token]) }}" class="ve-actions">
            @csrf
            <button type="submit" class="ve-button">{{ __('verification.link.confirm_button') }}</button>
        </form>
    @elseif ($state === 'done')
        <p class="ve-lead">{{ $signedIn ? __('verification.link.continue_hint') : __('verification.link.done_body') }}</p>
        <div class="ve-actions"><a href="{{ $next }}" class="ve-button">{{ $signedIn ? __('verification.link.continue') : __('verification.link.sign_in') }}</a></div>
    @elseif ($state === 'already')
        <p class="ve-lead">{{ __('verification.link.already_body') }}</p>
        <div class="ve-actions"><a href="{{ $next }}" class="ve-button">{{ $signedIn ? __('verification.link.continue') : __('verification.link.sign_in') }}</a></div>
    @else
        <p class="ve-lead">{{ __('verification.link.'.$state.'_body') }}</p>
        <div class="ve-actions"><a href="{{ $retry }}" class="ve-button">{{ __('verification.link.new_email') }}</a></div>
    @endif
</div>
@endsection
