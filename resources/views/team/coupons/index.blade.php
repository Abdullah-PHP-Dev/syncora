@extends('layouts.app')
@section('title', __('coupons.title'))
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin-plans.css') }}?v={{ filemtime(public_path('assets/css/admin-plans.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/admin-coupons.css') }}?v={{ filemtime(public_path('assets/css/admin-coupons.css')) }}">
@endpush
@section('content')
<div class="plans-page">
<header class="plans-heading"><div><span class="plans-eyebrow">{{ __('coupons.title') }}</span><h1>{{ __('coupons.heading') }}</h1><p>{{ __('coupons.description') }}</p></div><a class="plans-button plans-button-primary" href="{{ route('coupons.create') }}"><i class="bi bi-plus-lg" aria-hidden="true"></i>{{ __('coupons.create') }}</a></header>
<div class="plans-stats">
    <div class="plans-stat"><span class="plans-stat-icon"><i class="bi bi-ticket-perforated" aria-hidden="true"></i></span><div><span>{{ __('coupons.total') }}</span><strong>{{ $stats['total'] }}</strong></div></div>
    <div class="plans-stat"><span class="plans-stat-icon"><i class="bi bi-check-circle" aria-hidden="true"></i></span><div><span>{{ __('coupons.completed') }}</span><strong>{{ $stats['completed'] }}</strong></div></div>
    <div class="plans-stat"><span class="plans-stat-icon"><i class="bi bi-piggy-bank" aria-hidden="true"></i></span><div><span>{{ __('coupons.savings') }}</span>@forelse($stats['discount'] as $saving)<strong class="coupon-savings">{{ number_format($saving->amount, 2) }} <small>{{ $saving->currency }}</small></strong>@empty<strong>0.00</strong>@endforelse</div></div>
</div>
<section class="coupon-panel">
<div class="plans-toolbar"><h2>{{ __('coupons.catalog') }}</h2><form method="GET" class="plans-filters"><div class="plans-search"><i class="bi bi-search" aria-hidden="true"></i><input type="search" name="search" value="{{ request('search') }}" maxlength="100" placeholder="{{ __('coupons.search') }}" aria-label="{{ __('coupons.search') }}"></div><select name="status" aria-label="{{ __('coupons.status') }}"><option value="">{{ __('coupons.all') }}</option>@foreach(['active', 'inactive', 'archived'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ __('coupons.'.($status === 'archived' ? 'archived_label' : $status)) }}</option>@endforeach</select><button class="plans-button plans-button-filter">{{ __('Filter') }}</button></form></div>
<div class="table-responsive"><table class="table coupon-table"><thead><tr><th>{{ __('coupons.code') }}</th><th>{{ __('coupons.discount') }}</th><th>{{ __('coupons.usage') }}</th><th>{{ __('coupons.status') }}</th><th>{{ __('coupons.ends_at') }}</th><th>{{ __('Actions') }}</th></tr></thead><tbody>
@forelse($coupons as $coupon)<tr><td><a class="coupon-code" href="{{ route('coupons.edit', $coupon) }}">{{ $coupon->code }}</a><small>{{ $coupon->name }}</small></td><td><strong>{{ number_format($coupon->value, 2) }}{{ $coupon->type === 'percentage' ? '%' : ' '.$coupon->currency }}</strong><small>{{ __('coupons.'.$coupon->type) }}</small></td><td>{{ $coupon->completed_count }} / {{ $coupon->usage_limit ?? '∞' }}<small>{{ $coupon->reserved_count }} {{ __('coupons.reserved') }}</small></td><td><span class="plan-badge {{ $coupon->is_active && !$coupon->trashed() ? 'plan-badge-active' : 'plan-badge-disabled' }}">{{ $coupon->trashed() ? __('coupons.archived_label') : __('coupons.'.($coupon->is_active ? 'active' : 'inactive')) }}</span></td><td>{{ $coupon->ends_at?->format('Y-m-d H:i') ?? '—' }}</td><td><a class="plans-button plans-button-filter" href="{{ route('coupons.edit', $coupon) }}"><i class="bi bi-pencil" aria-hidden="true"></i>{{ __('coupons.edit') }}</a></td></tr>
@empty<tr><td colspan="6"><div class="plans-empty"><span class="plan-symbol"><i class="bi bi-ticket-perforated" aria-hidden="true"></i></span><h3>{{ __('coupons.no_coupons') }}</h3><p>{{ __('coupons.no_coupons_help') }}</p><a class="plans-button plans-button-primary" href="{{ route('coupons.create') }}">{{ __('coupons.create') }}</a></div></td></tr>@endforelse
</tbody></table></div>
@if($coupons->hasPages())<div class="plans-pagination">{{ $coupons->links() }}</div>@endif
</section><aside class="plans-note"><i class="bi bi-shield-check" aria-hidden="true"></i><p>{{ __('coupons.pending_help') }}</p></aside>
</div>
@endsection
