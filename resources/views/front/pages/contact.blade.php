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
            @foreach(config('marketing-faqs.contact') as $faq)
            <details @if($loop->first) open @endif><summary>{{ __($faq['question']) }}<span aria-hidden="true">+</span></summary><p>{{ __($faq['answer']) }}@isset($faq['link']) <a href="{{ route($faq['link']['route']) }}">{{ __($faq['link']['label']) }} →</a>@endisset</p></details>
            @endforeach
        </div>
    </section>
</div>
@endsection
