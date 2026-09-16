<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ __('Login — Socialeaz') }}</title>
    <meta name="description" content="{{ __('Sign in to your Socialeaz workspace.') }}">

    {{-- Bootstrap 5 --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap{{ app()->getLocale() === 'ar' ? '.rtl' : '' }}.min.css" rel="stylesheet">

    {{-- Bootstrap Icons --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    {{-- Socialeaz CSS --}}
    <link rel="stylesheet" href="{{ asset('assets/css/socialeaz.css') }}">
</head>

<body class="auth-page">
<div class="auth-shell">

    {{-- LEFT SIDE --}}
    <section class="auth-visual">
        <div class="auth-grid"></div>
        <div class="auth-glow auth-glow-one"></div>
        <div class="auth-glow auth-glow-two"></div>

        <div class="auth-visual-content">

            {{-- Brand --}}
            <a href="{{ route('home') }}" class="auth-brand">
                <span class="auth-brand-logo"><img src="{{ asset('assets/img/logo/socialeaz-logo-64.png') }}" alt="Socialeaz"></span>
                <span>Socialeaz</span>
            </a>

            {{-- Main message --}}
            <div class="auth-hero-copy">
                <div class="auth-eyebrow">
                    <span class="auth-eyebrow-dot"></span>{{ __('THE AI SOCIAL MEDIA WORKSPACE') }}</div>

                <h1>{{ __('Your social team,') }}<span>{{ __('in one workspace.') }}</span>
                </h1>

                <p>{{ __('Plan, create, collaborate, schedule and measure your social media — with AI doing the busy work.') }}</p>
            </div>

            {{-- Mini workspace preview --}}
            <div class="auth-dashboard-preview">
                <div class="auth-preview-top">
                    <div class="d-flex align-items-center gap-2">
                        <span class="auth-preview-dot"></span>
                        <span class="auth-preview-dot"></span>
                        <span class="auth-preview-dot"></span>
                    </div>

                    <span class="auth-preview-status">
                        <i class="bi bi-circle-fill"></i>{{ __('All systems live') }}</span>
                </div>

                <div class="auth-preview-body">

                    {{-- Sidebar --}}
                    <div class="auth-preview-sidebar">
                        <div class="auth-preview-brand"><img src="{{ asset('assets/img/logo/socialeaz-logo-32.png') }}" alt="Socialeaz"></div>

                        <div class="auth-preview-nav active">
                            <i class="bi bi-grid"></i>
                        </div>

                        <div class="auth-preview-nav">
                            <i class="bi bi-stars"></i>
                        </div>

                        <div class="auth-preview-nav">
                            <i class="bi bi-calendar3"></i>
                        </div>

                        <div class="auth-preview-nav">
                            <i class="bi bi-bar-chart"></i>
                        </div>
                    </div>

                    {{-- Dashboard --}}
                    <div class="auth-preview-main">
                        <div class="auth-preview-heading">
                            <div>
                                <div class="auth-preview-small">{{ __('Tuesday, August 18') }}</div>

                                <div class="auth-preview-title">{{ __('Good morning 👋') }}</div>
                            </div>

                            <div class="auth-preview-create">{{ __('+ Create') }}</div>
                        </div>

                        {{-- KPIs --}}
                        <div class="auth-preview-kpis">
                            <div class="auth-preview-card">
                                <div class="auth-preview-small">{{ __('Published') }}</div>

                                <strong>248</strong>

                                <span>↑ 18.4%</span>
                            </div>

                            <div class="auth-preview-card">
                                <div class="auth-preview-small">{{ __('Engagement') }}</div>

                                <strong>8.72%</strong>

                                <span>↑ 12.1%</span>
                            </div>

                            <div class="auth-preview-card auth-preview-ai">
                                <div class="auth-preview-small">{{ __('AI Copilot') }}</div>

                                <strong>{{ __('12 posts ready') }}</strong>

                                <div class="auth-progress">
                                    <span></span>
                                </div>
                            </div>
                        </div>

                        {{-- Chart --}}
                        <div class="auth-preview-chart">
                            <div class="auth-preview-chart-header">
                                <strong>{{ __('Content performance') }}</strong>
                                <span>{{ __('Last 30 days') }}</span>
                            </div>

                            <div class="auth-bars">
                                <i style="height:35%"></i>
                                <i style="height:50%"></i>
                                <i style="height:42%"></i>
                                <i style="height:68%"></i>
                                <i style="height:58%"></i>
                                <i style="height:82%"></i>
                                <i style="height:94%"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="auth-visual-footer">
                <span>
                    <i class="bi bi-check-circle-fill"></i>{{ __('14-day free trial') }}</span>

                <span>
                    <i class="bi bi-check-circle-fill"></i>{{ __('No credit card required') }}</span>
            </div>
        </div>
    </section>

    {{-- RIGHT SIDE --}}
    <section class="auth-form-section">
        <div class="auth-form-container">
            @include('partials.language-switcher')

            {{-- Mobile brand --}}
            <a href="{{ route('home') }}" class="auth-mobile-brand">
                <span class="auth-brand-logo"><img src="{{ asset('assets/img/logo/socialeaz-logo-64.png') }}" alt="Socialeaz"></span>
                Socialeaz
            </a>

            <div class="auth-form-header">
                <div class="auth-form-badge">
                    <i class="bi bi-person"></i>
                </div>

                <h2>{{ __('Welcome back') }}</h2>

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

            {{-- Social login --}}
            <div class="auth-social-buttons">
                <a href="#" class="auth-social-btn">
                    <span class="auth-google-icon">G</span>{{ __('Continue with Google') }}</a>

                <a href="#" class="auth-social-btn">
                    <i class="bi bi-apple"></i>{{ __('Continue with Apple') }}</a>
            </div>

            <div class="auth-divider">
                <span></span>
                <small>{{ __('OR CONTINUE WITH EMAIL') }}</small>
                <span></span>
            </div>

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
            <div class="auth-bottom-text">{{ __('Don\'t have a Socialeaz account?') }}<a href="{{ route('register') }}">{{ __('Create an account') }}</a>
            </div>

            <div class="auth-legal">{{ __('By continuing, you agree to our') }}<a href="#">{{ __('Terms') }}</a>{{ __('and') }}<a href="#">{{ __('Privacy Policy') }}</a>.
            </div>
        </div>
    </section>
</div>

{{-- Bootstrap --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

{{-- Password toggle --}}
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

</body>
</html>