@extends('layouts.apps')
@section('title', __('Login — Socialeaz'))
@section('meta_description', __('Sign in to your Socialeaz workspace.'))
@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/auth-home.css') }}?v={{ filemtime(public_path('assets/css/auth-home.css')) }}">
@endpush

@section('content')
<div class="theme-page auth-home">
    <div class="auth-home-shell auth-home-login">
        @include('auth.partials.workspace-visual', ['mode' => 'login'])
        <section class="auth-form-section" aria-labelledby="auth-form-title">
            <div class="auth-form-container">
            <div class="auth-form-header">
                <div class="auth-form-badge">
                    <i class="bi bi-person"></i>
                </div>

                <h2 id="auth-form-title">{{ __('Welcome back') }}</h2>

                <p>{{ __('Sign in to continue to your workspace.') }}</p>
            </div>

            {{-- Validation errors --}}
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

            @if (session('status'))
                <div class="auth-alert auth-alert-success">
                    <i class="bi bi-check-circle"></i>

                    <span>{{ session('status') }}</span>
                </div>
            @endif

            {{-- Login form --}}
            <form method="POST" action="{{ route('login') }}" class="auth-form">
                @csrf

                {{-- Email --}}
                <div class="auth-field">
                    <label for="email">{{ __('Email address') }}</label>

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
                                autofocus
                        >
                    </div>
                </div>

                {{-- Password --}}
                <div class="auth-field">
                    <div class="d-flex justify-content-between">
                        <label for="password">{{ __('Password') }}</label>

                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="auth-forgot">{{ __('Forgot password?') }}</a>
                        @endif
                    </div>

                    <div class="auth-input-wrap">
                        <i class="bi bi-lock"></i>

                        <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="{{ __('Enter your password') }}"
                                autocomplete="current-password"
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
                </div>

                {{-- Remember --}}
                <div class="auth-remember">
                    <label class="auth-checkbox">
                        <input
                                type="checkbox"
                                name="remember"
                                {{ old('remember') ? 'checked' : '' }}
                        >

                        <span></span>{{ __('Remember me') }}</label>
                </div>

                {{-- Submit --}}
                <button type="submit" class="auth-submit">
                    <span>{{ __('Sign in') }}</span>
                    <i class="bi bi-arrow-right"></i>
                </button>
            </form>

            {{-- Register --}}
            <div class="auth-bottom-text">{{ __('Don\'t have a Socialeaz account?') }} <a href="{{ route('register') }}">{{ __('Create an account') }}</a>
            </div>

            <div class="auth-legal">{{ __('By continuing, you agree to our') }} <a href="{{ route('terms') }}">{{ __('Terms') }}</a> {{ __('and') }} <a href="{{ route('privacy') }}">{{ __('Privacy Policy') }}</a>.
            </div>
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
