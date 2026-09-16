@extends('layouts.app')
@section('title', __('Subscription plans'))
@section('content')
<div class="d-flex justify-content-between mb-5"><h3>{{ __('Subscription plans') }}</h3><a class="btn btn-primary" href="{{ route('plans.create') }}">{{ __('Create plan') }}</a></div>
<p class="text-muted">{{ __('Plan changes appear in pricing and seller checkout. Deactivate a plan to stop new purchases without deleting existing subscriptions.') }}</p>
<div class="card"><div class="table-responsive"><table class="table"><thead><tr><th>{{ __('Name') }}</th><th>{{ __('Monthly price') }}</th><th>{{ __('Yearly price') }}</th><th>{{ __('Status') }}</th><th>{{ __('Action') }}</th></tr></thead><tbody>
@forelse($plans as $plan)<tr><td>{{ $plan->name }}</td><td>{{ number_format($plan->price, 2) }} {{ $plan->currency }}</td><td>{{ number_format($plan->yearly_price, 2) }} {{ $plan->currency }}</td><td>{{ $plan->is_active ? __('Active') : __('Disabled') }}</td><td><a href="{{ route('plans.edit', $plan) }}">{{ __('Edit') }}</a></td></tr>
@empty<tr><td colspan="5">{{ __('No plans yet.') }}</td></tr>@endforelse
</tbody></table></div><div class="card-body">{{ $plans->links() }}</div></div>
@endsection
