@extends('layouts.apps')
@section('title', __('Terms of Service') . ' — Socialeaz')
@section('meta_description', __('The terms that govern your access to and use of Socialeaz.'))
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/company-pages.css') }}?v={{ filemtime(public_path('assets/css/company-pages.css')) }}">
@endpush
@section('content')
@php($sections = [['id' => 'legal-section-1', 'label' => 'The Service'], ['id' => 'legal-section-2', 'label' => 'Accounts'], ['id' => 'legal-section-3', 'label' => 'Connected Third-Party Platforms'], ['id' => 'legal-section-4', 'label' => 'Subscriptions & Billing'], ['id' => 'legal-section-5', 'label' => 'Acceptable Use'], ['id' => 'legal-section-6', 'label' => 'Your Content'], ['id' => 'legal-section-7', 'label' => 'Availability & Changes'], ['id' => 'legal-section-8', 'label' => 'Termination'], ['id' => 'legal-section-9', 'label' => 'Disclaimer & Limitation of Liability'], ['id' => 'legal-section-10', 'label' => 'Changes to These Terms'], ['id' => 'legal-section-11', 'label' => 'Contact']])
<div class="theme-page company-page">
    <header class="company-legal-hero"><span class="theme-label">@include('front.partials.home-icon', ['icon' => 'shield']) {{ __('LEGAL & TRUST') }}</span><h1>{{ __('Terms of Service') }}</h1><p>{{ __('The terms that govern your access to and use of Socialeaz.') }}</p><span class="company-updated">{{ __('Last updated: August 1, 2026') }}</span></header>
    <div class="company-legal-layout">
        <aside class="company-legal-sidebar"><nav aria-label="{{ __('On this page') }}"><h2>{{ __('On this page') }}</h2>@foreach($sections as $section)<a href="#{{ $section['id'] }}">{{ __($section['label']) }}</a>@endforeach</nav><div class="company-legal-help"><strong>{{ __('Questions? Let’s talk.') }}</strong><p>{{ __('Contact our team for help with this document.') }}</p><a href="mailto:support@socialeaz.com" dir="ltr">support@socialeaz.com</a></div></aside>
        <article class="legal-content">@include('front.pages.legal.terms-'.(app()->getLocale() === 'ar' ? 'ar' : 'en'))</article>
    </div>
    <div class="company-legal-bottom"><a href="{{ route('privacy') }}">{{ __('Privacy Policy') }} <span aria-hidden="true">→</span></a><a href="{{ route('contact') }}">{{ __('Contact') }} <span aria-hidden="true">→</span></a></div>
</div>
@endsection
