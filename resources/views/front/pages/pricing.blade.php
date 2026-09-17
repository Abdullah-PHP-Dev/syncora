@extends('layouts.main')
@section('title', __('Pricing — Socialeaz'))
@section('meta_description', __('Find the right plan for your social media workspace. Compare features, monthly pricing, and yearly billing.'))
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pricing.css') }}">
@endpush

@section('content')
@php
    $yearly = request('billing') === 'yearly';
    $maxSaving = $plans->filter(fn ($plan) => !$plan->is_free && $plan->price > 0)
        ->map(fn ($plan) => max(0, (int) floor((1 - $plan->yearly_price / ($plan->price * 12)) * 100)))->max() ?? 0;
    $trial = $plans->firstWhere('is_free', true);
    $formatPrice = fn ($amount) => number_format((float) $amount, 2);
@endphp
<div class="pricing-page" data-pricing-page>
    <section class="pricing-intro">
        <div class="pricing-intro-grid" aria-hidden="true"></div>
        <div class="container position-relative">
            <div class="pricing-locale">@include('partials.language-switcher')</div>
            <div class="pricing-eyebrow"><span></span>{{ __('A little less busywork. A lot more possibility.') }}</div>
            <h1>{{ __('Big ideas.') }}<br><span class="gradient-text">{{ __('A plan to match.') }}</span></h1>
            <p class="pricing-intro-copy">{{ __('Your content, campaigns, and conversations. One workspace that grows with you.') }}</p>
            <div class="pricing-billing" aria-label="{{ __('Billing period') }}">
                <a href="{{ route('pricing', ['billing' => 'monthly']) }}" data-billing="monthly" class="{{ !$yearly ? 'is-selected' : '' }}" @if(!$yearly) aria-current="true" @endif>{{ __('Monthly') }}</a>
                <a href="{{ route('pricing', ['billing' => 'yearly']) }}" data-billing="yearly" class="{{ $yearly ? 'is-selected' : '' }}" @if($yearly) aria-current="true" @endif>{{ __('Yearly') }}@if($maxSaving > 0)<span>{{ __('Save up to :percent%', ['percent' => $maxSaving]) }}</span>@endif</a>
            </div>
            <p class="pricing-billing-note">{{ __('Your pace. Your plan. Choose the billing period that works for you.') }}</p>
        </div>
    </section>

    <section class="pricing-plans-section" aria-label="{{ __('Subscription plans') }}">
        <div class="container">
            <div class="pricing-plan-grid">
            @forelse($plans as $plan)
                @php
                    $saving = !$plan->is_free && $plan->price > 0 ? max(0, (int) floor((1 - $plan->yearly_price / ($plan->price * 12)) * 100)) : 0;
                    $features = $plan->display_features;
                    if (!$features) {
                        $raw = $plan->features ?? [];
                        foreach (data_get($raw, 'limits', $plan->limits ?? []) as $key => $value) {
                            if (is_numeric($value)) {
                                $label = __(ucfirst(str_replace('_', ' ', $key)));
                                $features[] = (int) $value === -1 ? __('Unlimited :feature', ['feature' => $label]) : __(':count :feature', ['count' => number_format($value), 'feature' => $label]);
                            }
                        }
                        foreach (data_get($raw, 'features', $raw) as $key => $enabled) {
                            if ($enabled === true) $features[] = __(ucfirst(str_replace('_', ' ', $key)));
                        }
                    }
                    $canCheckout = auth()->check() && !auth()->user()->isTeamMember() && auth()->user()->hasRole('seller') && !$plan->is_free;
                    $monthlyUrl = $canCheckout ? route('admin.subscription.checkout', ['plan_id' => $plan->id, 'cycle' => 'monthly']) : (auth()->check() ? route('dashboard') : route('register'));
                    $yearlyUrl = $canCheckout ? route('admin.subscription.checkout', ['plan_id' => $plan->id, 'cycle' => 'yearly']) : $monthlyUrl;
                    $cta = auth()->check() ? ($canCheckout ? __('Choose :plan', ['plan' => $plan->name]) : __('Open your workspace')) : ($plan->is_free ? __('Start free') : __('Get started'));
                    $trialDays = data_get($plan->meta, 'trial_days');
                @endphp
                <article class="pricing-plan {{ $plan->is_popular ? 'pricing-plan-featured' : '' }}" data-plan>
                    @if($plan->is_popular)<div class="pricing-plan-recommendation"><i class="bi bi-stars" aria-hidden="true"></i>{{ __('Recommended plan') }}</div>@endif
                    <div class="pricing-plan-header">
                        <span class="pricing-plan-icon"><i class="bi {{ $plan->is_free ? 'bi-lightning-charge' : ($plan->is_popular ? 'bi-stars' : 'bi-layers') }}" aria-hidden="true"></i></span>
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
                    <a class="pricing-plan-cta" data-plan-cta data-monthly="{{ $monthlyUrl }}" data-yearly="{{ $yearlyUrl }}" href="{{ $yearly ? $yearlyUrl : $monthlyUrl }}">{{ $cta }}<i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                    <div class="pricing-plan-features"><h3>{{ __('Inside your workspace') }}</h3>
                        @if(count($features))<ul>@foreach($features as $feature)<li><i class="bi bi-check2" aria-hidden="true"></i><span>{{ $feature }}</span></li>@endforeach</ul>
                        @else<p class="pricing-features-empty">{{ __('Plan details are being updated. Check back soon for the full feature list.') }}</p>@endif
                    </div>
                </article>
            @empty
                <div class="pricing-empty"><i class="bi bi-layers" aria-hidden="true"></i><h2>{{ __('Good things are taking shape.') }}</h2><p>{{ __('Our plans are being updated. Please check back soon.') }}</p><a class="btn btn-black btn-socialeaz" href="{{ route('home') }}">{{ __('Back to home') }}</a></div>
            @endforelse
            </div>
            @if($plans->isNotEmpty())
            <div class="pricing-reassurance"><span><i class="bi bi-calendar-check" aria-hidden="true"></i>{{ __('Monthly or yearly billing') }}</span><span><i class="bi bi-translate" aria-hidden="true"></i>{{ __('English & Arabic workspace') }}</span><span><i class="bi bi-chat-square-heart" aria-hidden="true"></i>{{ __('Support when you need it') }}</span></div>
            @endif
        </div>
    </section>

    <section class="pricing-questions">
        <div class="container"><div class="row g-5">
            <div class="col-lg-5"><div class="pricing-section-label">{{ __('THE SMALL DETAILS') }}</div><h2>{{ __('Clear answers.') }}<br>{{ __('Confident choices.') }}</h2><p>{{ __('A few things to know before you make yourself at home.') }}</p></div>
            <div class="col-lg-7">
                @if($trial)<details class="pricing-faq" open><summary>{{ __('Can I start with a free plan?') }}<i class="bi bi-plus-lg" aria-hidden="true"></i></summary><p>{{ data_get($trial->meta, 'trial_days') ? __('Yes. :plan gives you :days days to explore your workspace. See the plan card for included features.', ['plan' => $trial->name, 'days' => data_get($trial->meta, 'trial_days')]) : __('Yes. Start with :plan and explore the features listed in its plan card.', ['plan' => $trial->name]) }}</p></details>@endif
                <details class="pricing-faq" @if(!$trial) open @endif><summary>{{ __('How does yearly billing work?') }}<i class="bi bi-plus-lg" aria-hidden="true"></i></summary><p>{{ __('Select Yearly to see the full annual price. The monthly equivalent appears below it, and any savings are calculated against twelve monthly payments.') }}</p></details>
                <details class="pricing-faq"><summary>{{ __('Where can I manage my subscription?') }}<i class="bi bi-plus-lg" aria-hidden="true"></i></summary><p>{{ __('Sign in to your workspace and open Subscription to review available plans and manage your subscription.') }}</p></details>
                <details class="pricing-faq"><summary>{{ __('Can I use the workspace in Arabic?') }}<i class="bi bi-plus-lg" aria-hidden="true"></i></summary><p>{{ __('Yes. Switch between English and Arabic, with a right-to-left layout for Arabic.') }}</p></details>
            </div>
        </div></div>
    </section>
    <section class="pricing-bottom"><div class="container"><div class="pricing-bottom-inner"><div><span>{{ __('LESS SWITCHING. MORE CREATING.') }}</span><h2>{{ __('Make room for your next big idea.') }}</h2></div><a class="btn btn-black btn-socialeaz" href="{{ auth()->check() ? route('dashboard') : route('register') }}">{{ auth()->check() ? __('Open your workspace') : __('Create your workspace') }}<i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></div></div></section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/pricing.js') }}" defer></script>
@endpush
