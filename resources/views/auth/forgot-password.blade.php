@extends('layouts.apps')
@section('title', __('Forgot password — Socialeaz'))
@section('meta_description', __('Request a password reset link for your Socialeaz workspace.'))
@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/auth-home.css') }}?v={{ filemtime(public_path('assets/css/auth-home.css')) }}">
@endpush
@section('content')
<div class="theme-page auth-home">
    <div class="auth-home-shell auth-home-recovery">
        @include('auth.partials.recovery-visual')
        <section class="auth-form-section" aria-labelledby="auth-form-title">
            <div class="auth-form-container">
                <div class="auth-form-header"><div class="auth-form-badge"><i class="bi bi-key" aria-hidden="true"></i></div><h2 id="auth-form-title">{{ __('Forgot your password?') }}</h2><p>{{ __('Enter your account email and we’ll send you a link to choose a new password.') }}</p></div>
                @if(session('status'))<div class="auth-alert auth-alert-success" role="status"><i class="bi bi-check-circle" aria-hidden="true"></i><span>{{ session('status') }}</span></div>@endif
                @if($errors->any())<div class="auth-alert auth-alert-danger" role="alert"><i class="bi bi-exclamation-circle" aria-hidden="true"></i><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                <form method="POST" action="{{ route('password.email') }}" class="auth-form">
                    @csrf
                    <div class="auth-field"><label for="email">{{ __('Email address') }}</label><div class="auth-input-wrap"><i class="bi bi-envelope" aria-hidden="true"></i><input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="you@company.com" autocomplete="email" required autofocus @error('email') aria-invalid="true" @enderror></div></div>
                    <button type="submit" class="auth-submit"><span>{{ __('Send reset link') }}</span><i class="bi bi-arrow-right" aria-hidden="true"></i></button>
                </form>
                <div class="auth-bottom-text"><a href="{{ route('login') }}">{{ __('Back to sign in') }}</a></div>
                <div class="auth-legal">{{ __('Need help accessing your account?') }} <a href="{{ route('contact') }}">{{ __('Contact & support') }}</a></div>
            </div>
        </section>
    </div>
</div>
@endsection
