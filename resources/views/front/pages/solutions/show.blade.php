@extends('layouts.apps')
@php
    $solution = config('marketing-solutions.'.$solutionKey);
    $copy = fn ($value) => $value[app()->getLocale()] ?? $value['en'];
@endphp
@section('title', $copy($solution['title']).' — Socialeaz')
@section('meta_description', $copy($solution['description']))
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/solutions.css') }}?v={{ filemtime(public_path('assets/css/solutions.css')) }}">
@endpush
@section('content')
<div class="theme-page solution-page">
    <section class="solution-hero" aria-labelledby="solution-title">
        <div><span class="theme-label">@include('front.partials.home-icon', ['icon' => $solution['icon']]) {{ $copy($solution['title']) }}</span><h1 id="solution-title">{{ $copy($solution['headline']) }}<br><span>{{ $copy($solution['accent']) }}</span></h1><p>{{ $copy($solution['description']) }}</p><div class="theme-actions"><a href="{{ route('register') }}" class="theme-button theme-button-primary">{{ __('Start for free') }} <span aria-hidden="true">→</span></a><a href="{{ route('pricing') }}" class="theme-button theme-button-outline">{{ __('Compare plans') }}</a></div></div>
        <div class="solution-hero-art"><img class="hero-illustration" src="{{ asset('assets/images/home-theme/solution-'.$solutionKey.'.jpg') }}" width="1536" height="1024" alt="{{ __('Socialeaz workspace illustration') }}" fetchpriority="high"></div>
    </section>
    <section class="solution-benefits" aria-labelledby="solution-benefits-title">
        <div class="solution-section-heading"><p class="theme-eyebrow">{{ __('BUILT AROUND YOUR WORK') }}</p><h2 id="solution-benefits-title">{{ __('A workflow that fits you.') }}</h2></div>
        <div class="solution-benefit-grid">@foreach($solution['features'] as $feature)<article><span class="theme-icon theme-icon-{{ $feature['color'] }}">@include('front.partials.home-icon', ['icon' => $feature['icon']])</span><h3>{{ $copy($feature['title']) }}</h3><p>{{ $copy($feature['text']) }}</p><a href="{{ route('product.'.$feature['product']) }}">{{ __('Explore this feature') }} <span aria-hidden="true">↗</span></a></article>@endforeach</div>
    </section>
    <section class="solution-workflow" aria-labelledby="solution-workflow-title">
        <div class="solution-section-heading"><p class="theme-eyebrow">{{ __('HOW IT WORKS') }}</p><h2 id="solution-workflow-title">{{ $copy($solution['workflow_title']) }}</h2><p>{{ $copy($solution['workflow_description']) }}</p></div>
        <div class="solution-step-grid">@foreach($solution['steps'] as $step)<article><div class="solution-step-top"><span class="theme-icon theme-icon-{{ ['blue','purple','green'][$loop->index] }}">@include('front.partials.home-icon', ['icon' => $solution['step_icons'][$loop->index]])</span><span class="solution-step-number">{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</span></div><h3>{{ $copy($step['title']) }}</h3><p>{{ $copy($step['text']) }}</p></article>@endforeach</div>
    </section>
    <section class="solution-faq" aria-labelledby="solution-faq-title"><div class="solution-section-heading"><p class="theme-eyebrow">{{ __('THE SMALL DETAILS') }}</p><h2 id="solution-faq-title">{{ __('Your questions, answered.') }}</h2><a href="{{ route('contact') }}" class="theme-learn">{{ __('Talk to our team') }} <span aria-hidden="true">→</span></a></div><div>@foreach($solution['faq'] as $faq)<details @if($loop->first) open @endif><summary>{{ $copy($faq['question']) }}<span aria-hidden="true">+</span></summary><p>{{ $copy($faq['answer']) }}</p></details>@endforeach</div></section>
    <section class="solution-explore" aria-labelledby="solution-explore-title"><div class="solution-section-heading"><p class="theme-eyebrow">{{ __('MORE WAYS TO WORK') }}</p><h2 id="solution-explore-title">{{ __('Find your place in Socialeaz.') }}</h2></div><div class="solution-explore-grid">@foreach(config('marketing-solutions') as $slug => $related)@if($slug !== $solutionKey)<a href="{{ route('solutions.'.$slug) }}"><span class="theme-icon theme-icon-{{ $related['color'] }}">@include('front.partials.home-icon', ['icon' => $related['icon']])</span><strong>{{ $copy($related['title']) }}</strong><span aria-hidden="true">→</span></a>@endif @endforeach</div></section>
    <section class="theme-final-cta"><div><h2>{{ $copy($solution['cta']) }}</h2><p>{{ __('Bring your ideas, channels and team together with Socialeaz.') }}</p></div><div class="theme-actions"><a href="{{ route('register') }}" class="theme-button theme-button-white">{{ __('Start for free') }} <span aria-hidden="true">→</span></a><a href="{{ route('pricing') }}" class="theme-button theme-button-ghost">{{ __('Compare plans') }}</a></div></section>
</div>
@endsection
