@extends('layouts.app')
@section('title', __('Support tickets'))
@section('content')
<div class="d-flex justify-content-between mb-5"><h3>{{ __('Support tickets') }}</h3><a class="btn btn-primary" href="{{ route('tickets.create') }}">{{ __('New ticket') }}</a></div>
<form class="row g-3 mb-5" method="GET">
<div class="col-md-4"><input class="form-control" name="search" value="{{ request('search') }}" placeholder="{{ __('Search tickets') }}" aria-label="{{ __('Search tickets') }}"></div>
@foreach(['status' => \App\Models\SupportTicket::STATUSES, 'priority' => \App\Models\SupportTicket::PRIORITIES] as $key => $options)
<div class="col-md-2"><select class="form-select" name="{{ $key }}" aria-label="{{ __('workspace.'.$key) }}"><option value="">{{ __('workspace.'.$key) }}</option>@foreach($options as $option)<option value="{{ $option }}" @selected(request($key) === $option)>{{ __('workspace.'.$option) }}</option>@endforeach</select></div>
@endforeach
@if($staff)<div class="col-md-2"><select class="form-select" name="assignment" aria-label="{{ __('Assigned to') }}"><option value="">{{ __('All assignments') }}</option><option value="mine" @selected(request('assignment') === 'mine')>{{ __('Assigned to me') }}</option><option value="unassigned" @selected(request('assignment') === 'unassigned')>{{ __('Unassigned') }}</option></select></div>@endif
<div class="col-md-2"><button class="btn btn-primary">{{ __('Filter') }}</button></div>
</form>
<div class="card"><div class="table-responsive"><table class="table"><thead><tr><th>{{ __('Subject') }}</th>@if($staff)<th>{{ __('Customer') }}</th>@endif<th>{{ __('Priority') }}</th><th>{{ __('Status') }}</th><th>{{ __('Assigned to') }}</th><th>{{ __('Updated') }}</th></tr></thead><tbody>
@forelse($tickets as $ticket)<tr><td><a href="{{ route('tickets.show', $ticket) }}">#{{ $ticket->id }} {{ $ticket->subject }}</a></td>@if($staff)<td>{{ $ticket->customer->name }}</td>@endif<td>{{ __('workspace.'.$ticket->priority) }}</td><td>{{ __('workspace.'.$ticket->status) }}</td><td>{{ $ticket->assignee?->name ?? __('Unassigned') }}</td><td>{{ $ticket->updated_at->diffForHumans() }}</td></tr>
@empty<tr><td colspan="6" class="text-center py-6">{{ __('No tickets found.') }}</td></tr>@endforelse
</tbody></table></div><div class="card-body">{{ $tickets->links() }}</div></div>
@endsection
