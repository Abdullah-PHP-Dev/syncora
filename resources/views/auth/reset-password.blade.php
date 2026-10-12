@extends('layouts.apps')
@section('title', __('Reset password — Socialeaz'))
@section('meta_description', __('Choose a new password for your Socialeaz workspace.'))
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
                <div class="auth-form-header"><div class="auth-form-badge"><i class="bi bi-shield-lock" aria-hidden="true"></i></div><h2 id="auth-form-title">{{ __('Choose a new password') }}</h2><p>{{ __('Set a new password to return to your workspace.') }}</p></div>
                @if($errors->any())<div class="auth-alert auth-alert-danger" role="alert"><i class="bi bi-exclamation-circle" aria-hidden="true"></i><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                <form method="POST" action="{{ route('password.store') }}" class="auth-form">
                    @csrf
                    <input type="hidden" name="token" value="{{ $request->route('token') }}">
                    <div class="auth-field"><label for="email">{{ __('Email address') }}</label><div class="auth-input-wrap"><i class="bi bi-envelope" aria-hidden="true"></i><input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" placeholder="you@company.com" autocomplete="username" required autofocus @error('email') aria-invalid="true" @enderror></div></div>
                    <div class="auth-field"><label for="password">{{ __('Password') }}</label><div class="auth-input-wrap"><i class="bi bi-lock" aria-hidden="true"></i><input id="password" type="password" name="password" placeholder="{{ __('Create a strong password') }}" autocomplete="new-password" required @error('password') aria-invalid="true" @enderror><button type="button" class="auth-password-toggle" data-password-toggle="password" aria-label="{{ __('Show password') }}"><i class="bi bi-eye" aria-hidden="true"></i></button></div></div>
                    <div class="auth-field"><label for="password_confirmation">{{ __('Confirm password') }}</label><div class="auth-input-wrap"><i class="bi bi-shield-check" aria-hidden="true"></i><input id="password_confirmation" type="password" name="password_confirmation" placeholder="{{ __('Repeat your password') }}" autocomplete="new-password" required><button type="button" class="auth-password-toggle" data-password-toggle="password_confirmation" aria-label="{{ __('Show password') }}"><i class="bi bi-eye" aria-hidden="true"></i></button></div></div>
                    <button type="submit" class="auth-submit"><span>{{ __('Reset password') }}</span><i class="bi bi-arrow-right" aria-hidden="true"></i></button>
                </form>
                <div class="auth-bottom-text"><a href="{{ route('login') }}">{{ __('Back to sign in') }}</a></div>
            </div>
        </section>
    </div>
</div>
@endsection
@push('scripts')
<script>
    document.querySelectorAll('[data-password-toggle]').forEach(button => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.passwordToggle);
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-label', show ? @json(__('Hide password')) : @json(__('Show password')));
            button.querySelector('i').classList.toggle('bi-eye', !show);
            button.querySelector('i').classList.toggle('bi-eye-slash', show);
        });
    });
</script>
@endpush
