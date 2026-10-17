@extends('layouts.apps')
@section('title', __('Pricing — Socialeaz'))
@section('meta_description', __('Find the right plan for your social media workspace. Compare features, monthly pricing, and yearly billing.'))
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pricing.css') }}?v={{ filemtime(public_path('assets/css/pricing.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/package-cards.css') }}?v={{ filemtime(public_path('assets/css/package-cards.css')) }}">
@endpush

@section('content')
@php
    $yearly = request('billing') === 'yearly';
    $trial = $plans->firstWhere('is_free', true);
    $formatPrice = fn ($amount) => number_format((float) $amount, 2);
    $comparisonGroups = [
        __('Usage & capacity') => $features->whereIn('type', ['capacity', 'quota']),
        __('Workspace features') => $features->whereIn('type', ['boolean', 'benefit']),
    ];

@endphp
<div class="pricing-page theme-page" data-pricing-page>
    <section class="pricing-scale" aria-labelledby="pricing-scale-title">
        <div class="pricing-scale-copy">
            <span class="theme-label">@include('front.partials.home-icon', ['icon' => 'team']) {{ __('FOR TEAMS WITH BIG AMBITIONS') }}</span>
            <h2 id="pricing-scale-title">{{ __('More brands. Bigger ideas.') }}<br>{{ __('One connected workspace.') }}</h2>
            <p>{{ __('Managing multiple brands or a growing team? Talk to us about the right plan for your workflow.') }}</p>
            <a href="{{ route('contact') }}" class="theme-button theme-button-primary">{{ __('Talk to our team') }} <span aria-hidden="true">→</span></a>
        </div>
        <img class="hero-illustration" src="{{ asset('assets/images/home-theme/pricing-hero.jpg') }}" width="1536" height="1024" alt="{{ __('Creator presenting subscription plans with a wallet and billing calendar') }}" loading="lazy">
    </section>
    <section class="pricing-intro" aria-labelledby="pricing-title">
        <div class="pricing-hero-copy">
            <div class="pricing-eyebrow">@include('front.partials.home-icon', ['icon' => 'spark']) {{ __('CREATE · COLLABORATE · GROW') }}</div>
            <h1 id="pricing-title">{{ __('A plan for every') }}<br><span class="gradient-text">{{ __('stage of your growth.') }}</span></h1>
            <p class="pricing-intro-copy">{{ __('Everything you need to create, publish and grow. Choose the workspace that fits your ambition.') }}</p>
        </div>
    </section>

    <section class="pricing-plans-section" aria-label="{{ __('Subscription plans') }}">
        <div class="pricing-container">
            <div class="pricing-plans-toolbar">

                <div class="pricing-billing-controls">
            <div class="pricing-billing" aria-label="{{ __('Billing period') }}">
                <a href="{{ route('pricing', ['billing' => 'monthly']) }}" data-billing="monthly" class="{{ !$yearly ? 'is-selected' : '' }}" @if(!$yearly) aria-current="true" @endif>{{ __('Monthly') }}</a>
                <a href="{{ route('pricing', ['billing' => 'yearly']) }}" data-billing="yearly" class="{{ $yearly ? 'is-selected' : '' }}" @if($yearly) aria-current="true" @endif>{{ __('Yearly') }}@if($maxSaving > 0)<span>{{ __('Save up to :percent%', ['percent' => $maxSaving]) }}</span>@endif</a>
            </div>
            <p class="pricing-billing-note">{{ __('Your pace. Your plan. Choose the billing period that works for you.') }}</p>
                </div>
            </div>
            <div class="pricing-plan-grid">
            @forelse($plans as $plan)
                @php
                    $palette = ['mint', 'blue', 'violet', 'amber'];
                    $planStyle = match ($plan->slug) { 'free_trial' => 'mint', 'basic', 'starter' => 'blue', 'pro' => 'violet', 'business', 'empire' => 'amber', default => $palette[$loop->index % 4] };
                    $planIcon = ['mint' => 'sprout', 'blue' => 'publish', 'violet' => 'spark', 'amber' => 'building'][$planStyle];
                    $saving = $presentation->yearlySaving($plan);
                    $highlights = $presentation->highlights($plan, $features);
                    $additionalBenefits = array_values(array_diff($presentation->additionalBenefits($plan), $highlights));
                    $canCheckout = auth()->check() && !auth()->user()->isTeamMember() && auth()->user()->hasRole('seller') && !$plan->is_free;
                    $monthlyUrl = $canCheckout ? route('admin.subscription.checkout', ['plan_id' => $plan->id, 'cycle' => 'monthly']) : (auth()->check() ? route('dashboard') : route('register'));
                    $yearlyUrl = $canCheckout ? route('admin.subscription.checkout', ['plan_id' => $plan->id, 'cycle' => 'yearly']) : $monthlyUrl;
                    $cta = auth()->check() ? ($canCheckout ? __('Choose :plan', ['plan' => $plan->name]) : __('Open your workspace')) : ($plan->is_free ? __('Start free') : __('Get started'));
                    $trialDays = data_get($plan->meta, 'trial_days');
                @endphp
                <article class="pricing-plan se-package-card pricing-plan-{{ $planStyle }} {{ $plan->is_popular ? 'pricing-plan-featured' : '' }}" data-plan>
                    @if($plan->is_popular)<div class="pricing-plan-recommendation">@include('front.partials.home-icon', ['icon' => 'spark']){{ __('Recommended plan') }}</div>@endif
                    <div class="pricing-plan-header">
                        <span class="pricing-plan-icon">@include('front.partials.home-icon', ['icon' => $planIcon])</span>
                        <h2>{{ $plan->name }}</h2>
                        @if($plan->description)<p>{{ $plan->description }}</p>@endif
                    </div>
                    <div class="pricing-plan-cost" aria-live="polite" aria-atomic="true">
                        <span class="pricing-currency">{{ $plan->currency }}</span>
                        <div class="pricing-amount-line"><strong data-price data-monthly="{{ $formatPrice($plan->price) }}" data-yearly="{{ $formatPrice($plan->is_free ? $plan->price : $plan->yearly_price) }}">{{ $formatPrice($yearly && !$plan->is_free ? $plan->yearly_price : $plan->price) }}</strong><span>@if($plan->is_free){{ __('to get started') }}@else<span data-period data-monthly="{{ __('/ month') }}" data-yearly="{{ __('/ year') }}">{{ $yearly ? __('/ year') : __('/ month') }}</span>@endif</span></div>
                        <div class="pricing-cost-note">
                            @if($plan->is_free)
                                <span>{{ $trialDays ? __(':days-day free trial', ['days' => $trialDays]) : __('Free plan') }}</span>
                            @else
                                <span data-billing-note data-monthly="{{ __('Billed monthly') }}" data-yearly="{{ __(':amount :currency / month, billed yearly', ['amount' => $formatPrice($plan->yearly_price / 12), 'currency' => $plan->currency]) }}">{{ $yearly ? __(':amount :currency / month, billed yearly', ['amount' => $formatPrice($plan->yearly_price / 12), 'currency' => $plan->currency]) : __('Billed monthly') }}</span>
                            @endif
                        </div>
                        @if($saving > 0)<span class="pricing-saving" data-saving @if(!$yearly) hidden @endif>{{ __('Save :percent% with yearly billing', ['percent' => $saving]) }}</span>@endif
                    </div>
                    <div class="pricing-plan-features">
                        @include('front.partials.package-allowances')
                    </div>
                    <a class="pricing-plan-cta" data-plan-cta data-monthly="{{ $monthlyUrl }}" data-yearly="{{ $yearlyUrl }}" href="{{ $yearly ? $yearlyUrl : $monthlyUrl }}">{{ $cta }}<span aria-hidden="true">→</span></a>
                    @if($additionalBenefits)<details class="pricing-additional-benefits"><summary>{{ __('Additional benefits') }}</summary><ul>@foreach($additionalBenefits as $benefit)<li>{{ $benefit }}</li>@endforeach</ul></details>@endif
                    <a class="pricing-card-compare" href="#plan-comparison">{{ __('Compare all features') }} <span aria-hidden="true">↓</span></a>
                </article>
            @empty
                <div class="pricing-empty">@include('front.partials.home-icon', ['icon' => 'chart'])<h2>{{ __('Good things are taking shape.') }}</h2><p>{{ __('Our plans are being updated. Please check back soon.') }}</p><a class="theme-button theme-button-primary" href="{{ route('home') }}">{{ __('Back to home') }}</a></div>
            @endforelse
            </div>
            @if($plans->isNotEmpty())
            <div class="pricing-compare-link"><a href="#plan-comparison" class="theme-button theme-button-outline">{{ __('Compare plans') }} <span aria-hidden="true">↓</span></a></div>
            <div class="pricing-reassurance"><span>@include('front.partials.home-icon', ['icon' => 'calendar']){{ __('Monthly or yearly billing') }}</span><span>@include('front.partials.home-icon', ['icon' => 'team']){{ __('English & Arabic workspace') }}</span><span>@include('front.partials.home-icon', ['icon' => 'team']){{ __('Support when you need it') }}</span></div>
            @endif
        </div>
    </section>

    @if($plans->isNotEmpty())
    <section class="pricing-comparison" id="plan-comparison" aria-labelledby="pricing-comparison-title">
        <div class="pricing-comparison-heading">
            <p class="theme-eyebrow">{{ __('THE FULL PICTURE') }}</p>
            <h2 id="pricing-comparison-title">{{ __('Compare. Choose. Create.') }}</h2>
            <p>{{ __('See what is included in each plan, side by side.') }}</p>
        </div>
        <div class="pricing-comparison-controls">
            <div class="pricing-billing" aria-label="{{ __('Billing period') }}">
                <a href="{{ route('pricing', ['billing' => 'monthly']) }}" data-billing="monthly" class="{{ !$yearly ? 'is-selected' : '' }}" @if(!$yearly) aria-current="true" @endif>{{ __('Monthly') }}</a>
                <a href="{{ route('pricing', ['billing' => 'yearly']) }}" data-billing="yearly" class="{{ $yearly ? 'is-selected' : '' }}" @if($yearly) aria-current="true" @endif>{{ __('Yearly') }}</a>
            </div>
        </div>
        @foreach($comparisonGroups as $groupLabel => $groupFeatures)
            @if($groupFeatures->isNotEmpty())
            <details class="pricing-comparison-group" @if($loop->first) open @endif>
                <summary>{{ $groupLabel }} <span aria-hidden="true">+</span></summary>
                <div class="pricing-table-scroll" tabindex="0" role="region" aria-label="{{ $groupLabel }}">
                    <table class="pricing-comparison-table">
                        <caption class="pricing-sr-only">{{ $groupLabel }}</caption>
                        <thead><tr><th scope="col">{{ __('Included in your plan') }}</th>
                            @foreach($plans as $plan)
                            <th scope="col" class="{{ $plan->is_popular ? 'pricing-comparison-featured' : '' }}">
                                @php
                                    $comparisonCanCheckout = auth()->check() && !auth()->user()->isTeamMember() && auth()->user()->hasRole('seller') && !$plan->is_free;
                                    $comparisonMonthlyUrl = $comparisonCanCheckout ? route('admin.subscription.checkout', ['plan_id' => $plan->id, 'cycle' => 'monthly']) : (auth()->check() ? route('dashboard') : route('register'));
                                    $comparisonYearlyUrl = $comparisonCanCheckout ? route('admin.subscription.checkout', ['plan_id' => $plan->id, 'cycle' => 'yearly']) : $comparisonMonthlyUrl;
                                @endphp
                                <strong>{{ $plan->name }}</strong>
                                <div class="pricing-comparison-price"><span data-price data-monthly="{{ $formatPrice($plan->price) }}" data-yearly="{{ $formatPrice($plan->is_free ? $plan->price : $plan->yearly_price) }}">{{ $formatPrice($yearly && !$plan->is_free ? $plan->yearly_price : $plan->price) }}</span> {{ $plan->currency }} @if($plan->is_free)<span>{{ __(':days-day free trial', ['days' => data_get($plan->meta, 'trial_days')]) }}</span>@else<span data-period data-monthly="{{ __('/ month') }}" data-yearly="{{ __('/ year') }}">{{ $yearly ? __('/ year') : __('/ month') }}</span>@endif</div>
                                <a class="pricing-comparison-cta" data-plan-cta data-monthly="{{ $comparisonMonthlyUrl }}" data-yearly="{{ $comparisonYearlyUrl }}" href="{{ $yearly ? $comparisonYearlyUrl : $comparisonMonthlyUrl }}">{{ auth()->check() && !$comparisonCanCheckout ? __('Open your workspace') : __('Choose :plan', ['plan' => $plan->name]) }}</a>
                            </th>
                            @endforeach
                        </tr></thead>
                        <tbody>
                        @foreach($groupFeatures as $feature)
                            <tr data-feature="{{ $feature->key }}"><th scope="row">{{ $feature->name }}</th>
                            @foreach($plans as $plan)
                                @php($value = $plan->entitlement_settings[$feature->key] ?? null)
                                <td class="{{ $plan->is_popular ? 'pricing-comparison-featured' : '' }}">
                                    @if($feature->type === 'boolean' && $value === true)<span class="pricing-table-check" aria-label="{{ __('Included') }}">✓</span>
                                    @elseif($feature->type === 'boolean' && $value === false)<span class="pricing-table-unavailable" aria-label="{{ __('Not included') }}">—</span>
                                    @else{{ $presentation->value($plan, $feature) }}@endif
                                </td>
                            @endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
            @endif
        @endforeach
    </section>
    @endif

    <section class="pricing-payments" aria-labelledby="pricing-payments-title">
        <div class="pricing-payments-content">
        <div class="pricing-payments-copy">
            <span class="theme-label">@include('front.partials.home-icon', ['icon' => 'spark']) {{ __('FLEXIBLE PAYMENTS') }}</span>
            <h2 id="pricing-payments-title">{{ __('Your plan. Your way to pay.') }}</h2>
            <p>{{ __('Choose the payment option that works for you. Available methods are shown at checkout.') }}</p>
        </div>
        <div class="pricing-payment-grid">
            <div class="pricing-payment-method pricing-payment-card">
                <img src="{{ asset('assets/img/payment/card.png') }}" alt="Visa / Mastercard" width="120" height="50" loading="lazy">

            </div>
            <div class="pricing-payment-method">
                <img src="{{ asset('assets/img/payment/tammara.png') }}" alt="Tamara" width="120" height="50" loading="lazy">

            </div>
            <div class="pricing-payment-method">
                <img src="{{ asset('assets/img/payment/tabby-01.png') }}" alt="Tabby" width="120" height="50" loading="lazy">

            </div>
            <div class="pricing-payment-method">
                <span class="pricing-apple-pay" aria-label="Apple Pay"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M17.1 12.4c0-2.1 1.7-3.2 1.8-3.3-1-1.5-2.6-1.7-3.2-1.7-1.4-.2-2.8.9-3.5.9-.7 0-1.8-.9-3-.9-1.6 0-3.1.9-3.9 2.3-1.7 2.9-.4 7.3 1.2 9.6.8 1.1 1.7 2.3 2.9 2.2 1.2 0 1.6-.7 3.1-.7s1.9.7 3.2.7c1.3 0 2.1-1.1 2.9-2.2.9-1.3 1.3-2.6 1.3-2.7-.1 0-2.8-1.1-2.8-4.2ZM14.8 5.8c.6-.8 1.1-1.9 1-3-.9 0-2 .6-2.7 1.4-.6.7-1.2 1.8-1.1 2.9 1 .1 2.1-.5 2.8-1.3Z"/></svg>Pay</span>

            </div>
        </div>
        <p class="pricing-payment-note">{{ __('Installment payments are subject to provider approval and availability.') }}</p>
        </div>
        <div class="pricing-payments-art">
            <img class="hero-illustration" src="{{ asset('assets/images/home-theme/pricing-payments.jpg') }}" width="1536" height="1024" alt="{{ __('Illustration of a wallet, payment cards and billing calendar') }}" loading="lazy">
        </div>
    </section>

    <section class="pricing-questions">
        <div class="pricing-container"><div class="pricing-faq-grid">
            <div class="pricing-questions-copy"><div class="pricing-section-label">{{ __('THE SMALL DETAILS') }}</div><h2>{{ __('Clear answers.') }}<br>{{ __('Confident choices.') }}</h2><p>{{ __('A few things to know before you make yourself at home.') }}</p></div>
            <div class="pricing-questions-list">
                @if($trial)<details class="pricing-faq" open><summary>{{ __('Can I start with a free plan?') }}<span class="pricing-faq-plus" aria-hidden="true">+</span></summary><p>{{ data_get($trial->meta, 'trial_days') ? __('Yes. :plan gives you :days days to explore your workspace. See the plan card for included features.', ['plan' => $trial->name, 'days' => data_get($trial->meta, 'trial_days')]) : __('Yes. Start with :plan and explore the features listed in its plan card.', ['plan' => $trial->name]) }}</p></details>@endif
                <details class="pricing-faq" @if(!$trial) open @endif><summary>{{ __('How does yearly billing work?') }}<span class="pricing-faq-plus" aria-hidden="true">+</span></summary><p>{{ __('Select Yearly to see the full annual price. The monthly equivalent appears below it, and any savings are calculated against twelve monthly payments.') }}</p></details>
                <details class="pricing-faq"><summary>{{ __('Where can I manage my subscription?') }}<span class="pricing-faq-plus" aria-hidden="true">+</span></summary><p>{{ __('Sign in to your workspace and open Subscription to review available plans and manage your subscription.') }}</p></details>
                <details class="pricing-faq"><summary>{{ __('Can I use the workspace in Arabic?') }}<span class="pricing-faq-plus" aria-hidden="true">+</span></summary><p>{{ __('Yes. Switch between English and Arabic, with a right-to-left layout for Arabic.') }}</p></details>
            </div>
        </div></div>
    </section>
    <section class="pricing-bottom"><div class="pricing-container"><div class="pricing-bottom-inner"><div><span>{{ __('LESS SWITCHING. MORE CREATING.') }}</span><h2>{{ __('Make room for your next big idea.') }}</h2></div><a class="theme-button theme-button-primary" href="{{ auth()->check() ? route('dashboard') : route('register') }}">{{ auth()->check() ? __('Open your workspace') : __('Create your workspace') }}<span aria-hidden="true">→</span></a></div></div></section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/pricing.js') }}" defer></script>
@endpush
