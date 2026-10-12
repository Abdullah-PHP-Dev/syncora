@extends('layouts.apps')
@section('title', __('Privacy Policy') . ' — Socialeaz')
@section('meta_description', __('How Socialeaz collects, uses, and protects your data across every connected platform.'))
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/company-pages.css') }}?v={{ filemtime(public_path('assets/css/company-pages.css')) }}">
@endpush
@section('content')
@php($sections = [['id' => 'legal-section-1', 'label' => 'Information We Collect'], ['id' => 'legal-section-2', 'label' => 'How We Use Information'], ['id' => 'legal-section-3', 'label' => 'Sharing of Information'], ['id' => 'legal-section-4', 'label' => 'Data Retention'], ['id' => 'legal-section-5', 'label' => 'Security'], ['id' => 'legal-section-6', 'label' => 'Cookies'], ['id' => 'legal-section-7', 'label' => 'Your Rights'], ['id' => 'legal-section-8', 'label' => 'Children\'s Privacy'], ['id' => 'legal-section-9', 'label' => 'Changes to This Policy'], ['id' => 'legal-section-10', 'label' => 'Contact']])
<div class="theme-page company-page">
    <header class="company-legal-hero"><span class="theme-label">@include('front.partials.home-icon', ['icon' => 'shield']) {{ __('LEGAL & TRUST') }}</span><h1>{{ __('Privacy Policy') }}</h1><p>{{ __('How Socialeaz collects, uses, and protects your data across every connected platform.') }}</p><span class="company-updated">{{ __('Last updated: August 1, 2026') }}</span></header>
    <div class="company-legal-layout">
        <aside class="company-legal-sidebar"><nav aria-label="{{ __('On this page') }}"><h2>{{ __('On this page') }}</h2>@foreach($sections as $section)<a href="#{{ $section['id'] }}">{{ __($section['label']) }}</a>@endforeach</nav><div class="company-legal-help"><strong>{{ __('Questions? Let’s talk.') }}</strong><p>{{ __('Contact our team for help with this document.') }}</p><a href="mailto:support@socialeaz.com" dir="ltr">support@socialeaz.com</a></div></aside>
        <article class="legal-content">@include('front.pages.legal.privacy-'.(app()->getLocale() === 'ar' ? 'ar' : 'en'))</article>
    </div>
    <div class="company-legal-bottom"><a href="{{ route('terms') }}">{{ __('Terms of Service') }} <span aria-hidden="true">→</span></a><a href="{{ route('contact') }}">{{ __('Contact') }} <span aria-hidden="true">→</span></a></div>
</div>
@endsection
