@extends('layouts.app')
@section('title', __('Subscribers'))
@section('content')
<h3>{{ __('Subscribers') }}</h3>
<form class="d-flex gap-3 mb-5" method="GET"><input name="search" class="form-control" value="{{ request('search') }}" placeholder="{{ __('Search by name or email') }}" aria-label="{{ __('Search by name or email') }}"><button class="btn btn-primary">{{ __('Search') }}</button></form>
<div class="card"><div class="table-responsive"><table class="table"><thead><tr><th>{{ __('Name') }}</th><th>{{ __('Email') }}</th><th>{{ __('Subscription') }}</th><th>{{ __('Status') }}</th><th>{{ __('Expires') }}</th></tr></thead><tbody>
@forelse($subscribers as $subscriber)<tr><td>{{ $subscriber->name }}</td><td>{{ $subscriber->email }}</td><td>{{ $subscriber->subscription?->bundle?->name ?? __('No subscription') }}</td><td>{{ $subscriber->subscription ? __('workspace.'.$subscriber->subscription->status) : '—' }}</td><td>{{ $subscriber->subscription?->end_date?->format('Y-m-d') ?? '—' }}</td></tr>
@empty<tr><td colspan="5">{{ __('No subscribers found.') }}</td></tr>@endforelse
</tbody></table></div><div class="card-body">{{ $subscribers->links() }}</div></div>
@endsection
