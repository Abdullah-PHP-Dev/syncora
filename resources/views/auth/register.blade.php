<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Create Account — Socialeaz</title>

    <meta name="description" content="Create your Socialeaz account and start managing your social media workspace.">


    {{-- Bootstrap 5 --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    {{-- Bootstrap Icons --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    {{-- Socialeaz CSS --}}
    <link rel="stylesheet" href="{{ asset('assets/css/socialeaz.css') }}">
</head>

<body class="auth-page">
<div class="auth-shell auth-register-shell">

    {{-- LEFT SIDE --}}
    <section class="auth-visual">
        <div class="auth-grid"></div>
        <div class="auth-glow auth-glow-one"></div>
        <div class="auth-glow auth-glow-two"></div>

        <div class="auth-visual-content">

            <a href="{{ route('home') }}" class="auth-brand">
                <span class="auth-brand-logo"><img src="{{ asset('assets/img/logo/socialeaz-logo-64.png') }}" alt="Socialeaz"></span>
                <span>Socialeaz</span>
            </a>

            <div class="auth-hero-copy">
                <div class="auth-eyebrow">
                    <span class="auth-eyebrow-dot"></span>
                    START YOUR FREE WORKSPACE
                </div>

                <h1>
                    Turn your ideas
                    <span>into momentum.</span>
                </h1>

                <p>
                    Build campaigns, create content, manage channels
                    and understand your audience from one intelligent
                    workspace.
                </p>
            </div>

            {{-- Benefits --}}
            <div class="auth-benefits">
                <div class="auth-benefit">
                    <div class="auth-benefit-icon">
                        <i class="bi bi-stars"></i>
                    </div>

                    <div>
                        <strong>AI-powered content</strong>
                        <span>Generate ideas, captions and campaigns faster.</span>
                    </div>
                </div>

                <div class="auth-benefit">
                    <div class="auth-benefit-icon">
                        <i class="bi bi-calendar3"></i>
                    </div>

                    <div>
                        <strong>Plan everything</strong>
                        <span>Organize your entire social calendar in one place.</span>
                    </div>
                </div>

                <div class="auth-benefit">
                    <div class="auth-benefit-icon">
                        <i class="bi bi-bar-chart"></i>
                    </div>

                    <div>
                        <strong>Measure what matters</strong>
                        <span>Understand performance across every channel.</span>
                    </div>
                </div>
            </div>

            <div class="auth-testimonial">
                <div class="auth-stars">★★★★★</div>

                <p>
                    “Socialeaz gives our team one place to plan,
                    create and collaborate. We finally spend less
                    time managing tools and more time creating.”
                </p>

                <div class="auth-testimonial-user">
                    <div class="auth-avatar">SL</div>

                    <div>
                        <strong>Sarah Lane</strong>
                        <span>Social Lead · Growthify</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- RIGHT SIDE --}}
    <section class="auth-form-section">
        <div class="auth-form-container auth-register-form-container">

            <a href="{{ route('home') }}" class="auth-mobile-brand">
                <span class="auth-brand-logo"><img src="{{ asset('assets/img/logo/socialeaz-logo-64.png') }}" alt="Socialeaz"></span>
                Socialeaz
            </a>

            <div class="auth-form-header">
                <div class="auth-form-badge auth-form-badge-purple">
                    <i class="bi bi-stars"></i>
                </div>

                <h2>Create your workspace</h2>

                <p>
                    Start your 14-day free trial. No credit card required.
                </p>
            </div>

            @if ($errors->any())
                <div class="auth-alert auth-alert-danger">
                    <i class="bi bi-exclamation-circle"></i>

                    <div>
                        <strong>Please check your details.</strong>

                        <ul class="mb-0 mt-1 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            {{-- Social registration --}}
            <div class="auth-social-buttons">
                <a href="#" class="auth-social-btn">
                    <span class="auth-google-icon">G</span>
                    Sign up with Google
                </a>

                <a href="#" class="auth-social-btn">
                    <i class="bi bi-apple"></i>
                    Sign up with Apple
                </a>
            </div>

            <div class="auth-divider">
                <span></span>
                <small>OR CREATE WITH EMAIL</small>
                <span></span>
            </div>

            <form method="POST" action="{{ route('register') }}" class="auth-form">
                @csrf

                {{-- Name --}}
                <div class="auth-field">
                    <label for="name">Full name</label>

                    <div class="auth-input-wrap">
                        <i class="bi bi-person"></i>

                        <input
                                type="text"
                                id="name"
                                name="name"
                                value="{{ old('name') }}"
                                placeholder="Your name"
                                autocomplete="name"
                                required
                                autofocus
                        >
                    </div>
                </div>

                {{-- Email --}}
                <div class="auth-field">
                    <label for="email">Work email</label>

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
                    <label for="password">Password</label>

                    <div class="auth-input-wrap">
                        <i class="bi bi-lock"></i>

                        <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Create a strong password"
                                autocomplete="new-password"
                                required
                        >

                        <button
                                type="button"
                                class="auth-password-toggle"
                                data-password-toggle="password"
                                aria-label="Show password"
                        >
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>

                    <div class="auth-password-hint">
                        <span></span>
                        Use at least 8 characters
                    </div>
                </div>

                {{-- Confirm Password --}}
                <div class="auth-field">
                    <label for="password_confirmation">Confirm password</label>

                    <div class="auth-input-wrap">
                        <i class="bi bi-shield-check"></i>

                        <input
                                type="password"
                                id="password_confirmation"
                                name="password_confirmation"
                                placeholder="Repeat your password"
                                autocomplete="new-password"
                                required
                        >

                        <button
                                type="button"
                                class="auth-password-toggle"
                                data-password-toggle="password_confirmation"
                                aria-label="Show password"
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
                                required
                        >
                        <span></span>
                    </label>

                    <div>
                        I agree to the
                        <a href="#">Terms of Service</a>
                        and
                        <a href="#">Privacy Policy</a>.
                    </div>
                </div>

                {{-- Submit --}}
                <button type="submit" class="auth-submit">
                    <span>Create free workspace</span>
                    <i class="bi bi-arrow-right"></i>
                </button>
            </form>

            <div class="auth-bottom-text">
                Already have an account?

                <a href="{{ route('login') }}">
                    Sign in
                </a>
            </div>

            <div class="auth-legal">
                Your account starts with a 14-day free trial.
                You can cancel anytime.
            </div>
        </div>
    </section>
</div>

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
                    this.setAttribute('aria-label', 'Hide password');
                } else {
                    input.type = 'password';
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                    this.setAttribute('aria-label', 'Show password');
                }
            });
        });
    });
</script>

</body>
</html>