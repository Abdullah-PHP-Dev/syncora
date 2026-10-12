@extends('layouts.apps')
@section('title', __('Create Account — Socialeaz'))
@section('meta_description', __('Create your Socialeaz account and start managing your social media workspace.'))
@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/auth-home.css') }}?v={{ filemtime(public_path('assets/css/auth-home.css')) }}">
@endpush

@section('content')
<div class="theme-page auth-home">
    <div class="auth-home-shell auth-home-register">
        @include('auth.partials.workspace-visual', ['mode' => 'register'])
        <section class="auth-form-section" aria-labelledby="auth-form-title">
            <div class="auth-form-container">
            <div class="auth-form-header">
                <div class="auth-form-badge auth-form-badge-purple">
                    <i class="bi bi-stars"></i>
                </div>

                <h2 id="auth-form-title">{{ __('Create your workspace') }}</h2>

                <p>{{ __('Start your 14-day free trial. No credit card required.') }}</p>
            </div>

            @if ($errors->any())
                <div class="auth-alert auth-alert-danger">
                    <i class="bi bi-exclamation-circle"></i>

                    <div>
                        <strong>{{ __('Please check your details.') }}</strong>

                        <ul class="mb-0 mt-1 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('register') }}" class="auth-form">
                @csrf

                {{-- Name --}}
                <div class="auth-field">
                    <label for="name">{{ __('Full name') }}</label>

                    <div class="auth-input-wrap">
                        <i class="bi bi-person"></i>

                        <input
                                type="text"
                                id="name"
                                name="name"
                                value="{{ old('name') }}"
                                placeholder="{{ __('Your name') }}"
                                autocomplete="name"
                                required
                                autofocus
                        >
                    </div>
                </div>

                {{-- Email --}}
                <div class="auth-field">
                    <label for="email">{{ __('Work email') }}</label>

                    <div class="auth-input-wrap">
                        <i class="bi bi-envelope"></i>

                        <input
                                type="email"
                                id="email"
                                name="email"
                                value="{{ old('email') }}"
                                placeholder="you@company.com"
                                autocomplete="email"
                                required
                        >
                    </div>
                </div>

                {{-- Password --}}
                <div class="auth-field">
                    <label for="password">{{ __('Password') }}</label>

                    <div class="auth-input-wrap">
                        <i class="bi bi-lock"></i>

                        <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="{{ __('Create a strong password') }}"
                                autocomplete="new-password"
                                required
                        >

                        <button
                                type="button"
                                class="auth-password-toggle"
                                data-password-toggle="password"
                                aria-label="{{ __('Show password') }}"
                        >
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>

                    <div class="auth-password-hint">
                        <span></span>{{ __('Use at least 8 characters') }}</div>
                </div>

                {{-- Confirm Password --}}
                <div class="auth-field">
                    <label for="password_confirmation">{{ __('Confirm password') }}</label>

                    <div class="auth-input-wrap">
                        <i class="bi bi-shield-check"></i>

                        <input
                                type="password"
                                id="password_confirmation"
                                name="password_confirmation"
                                placeholder="{{ __('Repeat your password') }}"
                                autocomplete="new-password"
                                required
                        >

                        <button
                                type="button"
                                class="auth-password-toggle"
                                data-password-toggle="password_confirmation"
                                aria-label="{{ __('Show password') }}"
                        >
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                {{-- Terms --}}
                <div class="auth-terms">
                    <label class="auth-checkbox auth-checkbox-top">
                        <input
                                type="checkbox"
                                name="terms"
                                value="1"
                                {{ old('terms') ? 'checked' : '' }}
                                required
                        >
                        <span></span>
                    </label>

                    <div>{{ __('I agree to the') }} <a href="{{ route('terms') }}">{{ __('Terms of Service') }}</a> {{ __('and') }} <a href="{{ route('privacy') }}">{{ __('Privacy Policy') }}</a>.
                    </div>
                </div>

                {{-- Submit --}}
                <button type="submit" class="auth-submit">
                    <span>{{ __('Create free workspace') }}</span>
                    <i class="bi bi-arrow-right"></i>
                </button>
            </form>

            <div class="auth-bottom-text">{{ __('Already have an account?') }} <a href="{{ route('login') }}">{{ __('Sign in') }}</a>
            </div>

            <div class="auth-legal">{{ __('Your account starts with a 14-day free trial. You can cancel anytime.') }}</div>
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                const inputId = this.getAttribute('data-password-toggle');
                const input = document.getElementById(inputId);
                const icon = this.querySelector('i');

                if (!input) {
                    return;
                }

                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                    this.setAttribute('aria-label', @json(__('Hide password')));
                } else {
                    input.type = 'password';
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                    this.setAttribute('aria-label', @json(__('Show password')));
                }
            });
        });
    });
</script>
@endpush
