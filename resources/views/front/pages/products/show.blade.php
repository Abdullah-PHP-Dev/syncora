@extends('layouts.apps')
@php
    $product = config('marketing-products.'.$productKey);
    $copy = fn ($value) => $value[app()->getLocale()] ?? $value['en'];
@endphp
@section('title', $copy($product['title']).' — Socialeaz')
@section('meta_description', $copy($product['description']))
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/product-pages.css') }}?v={{ filemtime(public_path('assets/css/product-pages.css')) }}">
@endpush
@section('content')
<div class="theme-page feature-page feature-page-{{ $productKey }}">
    <section class="feature-hero" aria-labelledby="feature-title">
        <div class="feature-hero-copy">
            <span class="theme-label">@include('front.partials.home-icon', ['icon' => $product['icon']]) {{ $copy($product['title']) }}</span>
            <h1 id="feature-title">{{ $copy($product['headline']) }}<br><span>{{ $copy($product['accent']) }}</span></h1>
            <p>{{ $copy($product['description']) }}</p>
            <div class="theme-actions"><a href="{{ route('register') }}" class="theme-button theme-button-primary">{{ __('Start for free') }} <span aria-hidden="true">→</span></a><a href="{{ route('pricing') }}" class="theme-button theme-button-outline">{{ __('Compare plans') }}</a></div>
        </div>
        <div class="feature-preview" aria-label="{{ __('Product preview') }}">
            @include('front.pages.products.preview', ['visual' => $product['visual']])
        </div>
    </section>
    <section class="feature-benefits" aria-label="{{ __('Product benefits') }}">
        @foreach($product['features'] as $feature)
        <article><span class="theme-icon theme-icon-{{ $feature['color'] ?? ['blue', 'purple', 'green'][$loop->index % 3] }}">@include('front.partials.home-icon', ['icon' => $feature['icon']])</span><h2>{{ $copy($feature['title']) }}</h2><p>{{ $copy($feature['text']) }}</p></article>
        @endforeach
    </section>
    <section class="feature-workflow" aria-labelledby="feature-workflow-title">
        <div class="feature-section-heading"><p class="theme-eyebrow">{{ __('HOW IT WORKS') }}</p><h2 id="feature-workflow-title">{{ $copy($product['workflow_title']) }}</h2><p class="feature-workflow-description">{{ $copy($product['workflow_description']) }}</p></div>
        <div class="feature-steps">
            @foreach($product['steps'] as $step)
            @php
                $stepIcons = [
                    'content-creation' => ['spark', 'calendar', 'publish'],
                    'ai-assistant' => ['team', 'spark', 'publish'],
                    'engagement' => ['link', 'team', 'publish'],
                    'analytics' => ['link', 'chart', 'calendar'],
                    'scheduling-publishing' => ['spark', 'calendar', 'publish'],
                    'integrations' => ['link', 'shield', 'check'],
                    'collaboration' => ['team', 'shield', 'publish'],
                ];
            @endphp
            <article class="feature-step-card">
                <div class="feature-step-top"><span class="theme-icon theme-icon-{{ ['blue', 'purple', 'green'][$loop->index % 3] }}">@include('front.partials.home-icon', ['icon' => $stepIcons[$productKey][$loop->index]])</span><span class="feature-step-number">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span></div>
                <h3>{{ $copy($step['title']) }}</h3><p>{{ $copy($step['text']) }}</p>
                @unless($loop->last)<span class="feature-step-connector" aria-hidden="true">→</span>@endunless
            </article>
            @endforeach
        </div>
    </section>
    <section class="feature-faq" aria-labelledby="feature-faq-title">
        <div class="feature-section-heading"><p class="theme-eyebrow">{{ __('THE SMALL DETAILS') }}</p><h2 id="feature-faq-title">{{ __('Your questions, answered.') }}</h2></div>
        <div>@foreach($product['faq'] as $faq)<details @if($loop->first) open @endif><summary>{{ $copy($faq['question']) }}<span aria-hidden="true">+</span></summary><p>{{ $copy($faq['answer']) }}</p></details>@endforeach</div>
    </section>
    <x-related-products :exclude="$productKey" />
    <section class="theme-final-cta"><div><h2>{{ __('Ready for your next chapter?') }}</h2><p>{{ __('Bring your ideas, channels and team together with Socialeaz.') }}</p></div><a href="{{ route('register') }}" class="theme-button theme-button-white">{{ __('Start for free') }} <span aria-hidden="true">→</span></a></section>
</div>
@endsection
