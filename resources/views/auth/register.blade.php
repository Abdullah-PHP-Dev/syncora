@extends('layouts.apps')
@section('title', __('Create Account — Socialeaz'))
@section('meta_description', __('Create your Socialeaz account and start managing your social media workspace.'))
@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/auth-home.css') }}?v={{ filemtime(public_path('assets/css/auth-home.css')) }}">
@endpush

@php
    $wizard = [
        'action' => route('register'),
        'csrf' => csrf_token(),
        'old' => ['organization_name' => old('organization_name'), 'email' => old('email'), 'terms' => (bool) old('terms')],
        'serverErrors' => $errors->toArray(),
        'urls' => ['terms' => route('terms'), 'privacy' => route('privacy'), 'login' => route('login')],
        'messages' => __('onboarding'),
    ];
@endphp

@section('content')
<div class="theme-page auth-home">
    <div class="auth-home-shell auth-home-register">
        @include('auth.partials.workspace-visual', ['mode' => 'register'])
        <section class="auth-form-section" aria-labelledby="auth-form-title">
            {{-- RegisterWizard.vue replaces this element. The plain form below is
                 the no-JavaScript fallback and posts the same fields. --}}
            <div id="register-wizard" data-props='@json($wizard)'>
                <div class="auth-form-container">
                    <div class="auth-form-header">
                        <h2 id="auth-form-title">{{ __('onboarding.title') }}</h2>
                        <p>{{ __('onboarding.subtitle') }}</p>
                    </div>
                    @if ($errors->any())
                        <div class="auth-alert auth-alert-danger" role="alert">
                            <i class="bi bi-exclamation-circle"></i>
                            <div><strong>{{ __('onboarding.check_details') }}</strong>
                                <ul class="mb-0 mt-1 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                            </div>
                        </div>
                    @endif
                    <form method="POST" action="{{ route('register') }}" class="auth-form">
                        @csrf
                        <div class="auth-field">
                            <label for="fb_organization_name">{{ __('onboarding.fields.organization_name') }}</label>
                            <div class="auth-input-wrap"><i class="bi bi-buildings"></i>
                                <input id="fb_organization_name" type="text" name="organization_name" value="{{ old('organization_name') }}" maxlength="150" autocomplete="organization" required>
                            </div>
                        </div>
                        <div class="auth-field">
                            <label for="fb_email">{{ __('onboarding.fields.email') }}</label>
                            <div class="auth-input-wrap"><i class="bi bi-envelope"></i>
                                <input id="fb_email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                            </div>
                        </div>
                        <div class="auth-field">
                            <label for="fb_password">{{ __('onboarding.fields.password') }}</label>
                            <div class="auth-input-wrap"><i class="bi bi-lock"></i>
                                <input id="fb_password" type="password" name="password" autocomplete="new-password" required>
                            </div>
                            <div class="auth-password-hint">{{ __('onboarding.password_hint') }}</div>
                        </div>
                        <div class="auth-field">
                            <label for="fb_password_confirmation">{{ __('onboarding.fields.password_confirmation') }}</label>
                            <div class="auth-input-wrap"><i class="bi bi-shield-check"></i>
                                <input id="fb_password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>
                            </div>
                        </div>
                        <div class="auth-terms">
                            <label class="auth-checkbox auth-checkbox-top"><input type="checkbox" name="terms" value="1" {{ old('terms') ? 'checked' : '' }} required><span></span></label>
                            <div>{{ __('onboarding.terms_prefix') }} <a href="{{ route('terms') }}">{{ __('onboarding.terms') }}</a> {{ __('onboarding.and') }} <a href="{{ route('privacy') }}">{{ __('onboarding.privacy') }}</a>.</div>
                        </div>
                        <button type="submit" class="auth-submit"><span>{{ __('onboarding.register') }}</span><i class="bi bi-arrow-right"></i></button>
                    </form>
                    <div class="auth-bottom-text">{{ __('onboarding.have_account') }} <a href="{{ route('login') }}">{{ __('onboarding.sign_in') }}</a></div>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
@vite('resources/js/register.js')
@endpush
