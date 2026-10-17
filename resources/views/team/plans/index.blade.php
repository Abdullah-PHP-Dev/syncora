@extends('layouts.app')
@section('title', __('Subscription plans'))
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin-plans.css') }}?v={{ filemtime(public_path('assets/css/admin-plans.css')) }}">
@endpush
@section('content')
<div class="plans-page">
    <header class="plans-heading">
        <div><span class="plans-eyebrow">{{ __('Package management') }}</span><h1>{{ __('Plans built for every stage') }}</h1><p>{{ __('Shape your merchant experience with the right pricing and benefits.') }}</p></div>
        <div class="package-heading-actions"><a class="plans-button plans-button-filter" href="{{ route('package-features.index') }}"><i class="bi bi-grid" aria-hidden="true"></i>{{ __('Feature catalog') }}</a><a class="plans-button plans-button-primary" href="{{ route('plans.create') }}"><i class="bi bi-plus-lg" aria-hidden="true"></i>{{ __('Create plan') }}</a></div>
    </header>

    <div class="plans-stats">
        @foreach([['bi-stack', 'Total plans', $stats['total'], 'Your complete package catalog'], ['bi-check-circle', 'Active plans', $stats['active'], 'Available for new subscriptions'], ['bi-stars', 'Recommended plans', $stats['recommended'], 'Highlighted in your pricing']] as [$icon, $label, $value, $caption])
        <div class="plans-stat"><span class="plans-stat-icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></span><div><span>{{ __($label) }}</span><strong>{{ number_format($value) }}</strong><small>{{ __($caption) }}</small></div></div>
        @endforeach
    </div>

    <section aria-label="{{ __('Plan catalog') }}">
        <div class="plans-toolbar">
            <div><h2>{{ __('Your plan catalog') }} <span>{{ $plans->total() }}</span></h2><p>{{ __('Manage pricing, visibility, and included benefits in one place.') }}</p></div>
            <form method="GET" action="{{ route('plans.index') }}" class="plans-filters" role="search">
                <div class="plans-search"><i class="bi bi-search" aria-hidden="true"></i><input type="search" name="search" value="{{ request('search') }}" maxlength="100" placeholder="{{ __('Search plans…') }}" aria-label="{{ __('Search plans') }}"></div>
                <select name="status" aria-label="{{ __('Plan status') }}"><option value="">{{ __('All statuses') }}</option><option value="active" @selected(request('status') === 'active')>{{ __('Active') }}</option><option value="disabled" @selected(request('status') === 'disabled')>{{ __('Disabled') }}</option></select>
                <button class="plans-button plans-button-filter" type="submit">{{ __('Filter') }}</button>
                @if(request()->filled('search') || request()->filled('status'))<a class="plans-reset" href="{{ route('plans.index') }}">{{ __('Reset') }}</a>@endif
            </form>
        </div>
        <div class="plans-grid">
            @forelse($plans as $plan)
            <article class="plan-tile {{ $plan->is_popular ? 'plan-tile-featured' : '' }}">
                <div class="plan-tile-top"><span class="plan-symbol"><i class="bi {{ $plan->is_free ? 'bi-gift' : ($plan->is_popular ? 'bi-lightning-charge' : 'bi-box-seam') }}" aria-hidden="true"></i></span><div class="plan-badges">@if($plan->is_popular)<span class="plan-badge plan-badge-popular"><i class="bi bi-stars" aria-hidden="true"></i>{{ __('Recommended') }}</span>@endif<span class="plan-badge {{ $plan->is_active ? 'plan-badge-active' : 'plan-badge-disabled' }}"><span></span>{{ $plan->is_active ? __('Active') : __('Disabled') }}</span></div></div>
                <h3>{{ $plan->name }}</h3><p class="plan-description">{{ $plan->description ?: __('A flexible package for your merchants.') }}</p>
                <div class="plan-price"><strong>{{ number_format($plan->price, 2) }}</strong><span>{{ $plan->currency }}<small>/ {{ __('month') }}</small></span></div>
                <div class="plan-yearly">{{ number_format($plan->yearly_price, 2) }} {{ $plan->currency }} <span>/ {{ __('year') }}</span></div>
                <div class="plan-benefits"><h4>{{ __('Included benefits') }}</h4><ul>@forelse(array_slice($plan->display_features, 0, 5) as $feature)<li><i class="bi bi-check2" aria-hidden="true"></i><span>{{ $feature }}</span></li>@empty<li class="plan-no-benefits">{{ __('Add benefits to help merchants choose this plan.') }}</li>@endforelse</ul>@if(count($plan->display_features) > 5)<p class="plan-more">{{ __(':count more benefits', ['count' => count($plan->display_features) - 5]) }}</p>@endif</div>
                <footer class="plan-tile-footer"><span>{{ $plan->is_free ? __('Free trial') : __('Paid plan') }}</span><a class="plans-button plan-edit" href="{{ route('plans.edit', $plan) }}" aria-label="{{ __('Edit plan') }}: {{ $plan->name }}"><i class="bi bi-pencil" aria-hidden="true"></i>{{ __('Edit plan') }}</a></footer>
            </article>
            @empty
            <div class="plans-empty"><span class="plan-symbol"><i class="bi bi-stack" aria-hidden="true"></i></span><h3>{{ __('No plans found') }}</h3><p>{{ __('Try another search or create a new plan to get started.') }}</p><a class="plans-button plans-button-primary" href="{{ route('plans.create') }}">{{ __('Create plan') }}</a></div>
            @endforelse
        </div>
        @if($plans->hasPages())<div class="plans-pagination">{{ $plans->links() }}</div>@endif
    </section>
    @include('team.plans.comparison')
    <aside class="plans-note"><i class="bi bi-info-circle" aria-hidden="true"></i><p>{{ __('Plan changes appear in pricing and seller checkout. Deactivate a plan to stop new purchases without deleting existing subscriptions.') }}</p></aside>
</div>
@endsection
