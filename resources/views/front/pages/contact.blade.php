@extends('layouts.apps')
@section('title', __('Contact & Support — Socialeaz'))
@section('meta_description', __('Get help with your Socialeaz workspace, subscriptions and team.'))
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/contact.css') }}?v={{ filemtime(public_path('assets/css/contact.css')) }}">
@endpush
@section('content')
@php
    $supportEmail = 'support@socialeaz.com';
    $canOpenTicket = auth()->check() && auth()->user()->hasAnyRole(['seller', 'admin', 'customer_support']);
@endphp
<div class="theme-page contact-page">
    <section class="contact-hero" aria-labelledby="contact-title">
        <span class="theme-label">@include('front.partials.home-icon', ['icon' => 'team']) {{ __('LET’S TALK') }}</span>
        <h1 id="contact-title">{{ __('A little help.') }}<br><span>{{ __('A lot more possibility.') }}</span></h1>
        <p>{{ __('Questions about your workspace, plans or growing your team? We’re here to help you move forward.') }}</p>
    </section>
    <section class="contact-grid" aria-label="{{ __('Ways to contact us') }}">
        <article class="contact-card">
            <span class="theme-icon theme-icon-purple">@include('front.partials.home-icon', ['icon' => 'spark'])</span>
            <h2>{{ __('Workspace support') }}</h2>
            <p>{{ __('Get help with connected accounts, publishing, AI tools and your subscription.') }}</p>
            <a class="theme-button theme-button-primary" href="{{ $canOpenTicket ? route('admin.tickets.create') : route('login') }}">{{ $canOpenTicket ? __('Open a support ticket') : __('Sign in for support') }} <span aria-hidden="true">→</span></a>
        </article>
        <article class="contact-card">
            <span class="theme-icon theme-icon-blue">@include('front.partials.home-icon', ['icon' => 'team'])</span>
            <h2>{{ __('Plans & partnerships') }}</h2>
            <p>{{ __('Let’s find the right setup for your brands, agency or growing team.') }}</p>
            <a class="theme-button theme-button-outline" href="mailto:{{ $supportEmail }}?subject={{ rawurlencode(__('Plans & partnerships')) }}">{{ __('Email our team') }} <span aria-hidden="true">→</span></a>
        </article>
        <article class="contact-card">
            <span class="theme-icon theme-icon-green">@include('front.partials.home-icon', ['icon' => 'calendar'])</span>
            <h2>{{ __('General questions') }}</h2>
            <p>{{ __('Have a question or feedback? Send us an email and tell us how we can help.') }}</p>
            <a class="contact-email" href="mailto:{{ $supportEmail }}" dir="ltr">{{ $supportEmail }}</a>
        </article>
    </section>
    <section class="contact-faq" id="support-faq" aria-labelledby="contact-faq-title">
        <div><p class="theme-eyebrow">{{ __('A GOOD PLACE TO START') }}</p><h2 id="contact-faq-title">{{ __('How can we help?') }}</h2><p>{{ __('Find a quick answer or reach out to our team.') }}</p></div>
        <div>
            <details open><summary>{{ __('How do I contact support?') }}<span aria-hidden="true">+</span></summary><p>{{ __('Sign in and open a support ticket to keep your request and replies in one place. You can also contact us by email.') }}</p></details>
            <details><summary>{{ __('Where can I compare subscription plans?') }}<span aria-hidden="true">+</span></summary><p>{{ __('Visit our pricing page to compare features and monthly or yearly billing.') }} <a href="{{ route('pricing') }}">{{ __('Compare plans') }} →</a></p></details>
            <details><summary>{{ __('What should I include in a support request?') }}<span aria-hidden="true">+</span></summary><p>{{ __('Describe what you were trying to do, what happened and the affected channel. Include a screenshot if helpful, and keep passwords and payment details private.') }}</p></details>
        </div>
    </section>
</div>
@endsection
