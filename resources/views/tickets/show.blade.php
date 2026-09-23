@extends('layouts.app')
@section('title', $ticket->subject)
@section('content')
<a href="{{ route('tickets.index') }}">{{ __('Support tickets') }}</a>
<h3 class="mt-3">#{{ $ticket->id }} {{ $ticket->subject }}</h3>
<p class="text-muted">{{ __('workspace.'.$ticket->category) }} · {{ __('workspace.'.$ticket->status) }} · {{ __('workspace.'.$ticket->priority) }}</p>
<div class="row g-4">
<div class="{{ $staff ? 'col-lg-8' : 'col-12' }}">
@foreach($messages as $message)
<div class="card mb-4 {{ $message->is_internal ? 'border border-warning' : '' }}"><div class="card-body">
<div class="d-flex justify-content-between flex-wrap gap-2 mb-3"><strong>{{ $message->author->name }}</strong><small>{{ $message->created_at->format('Y-m-d H:i') }}</small></div>
@if($message->is_internal)<span class="badge bg-label-warning mb-3">{{ __('Internal note') }}</span>@endif
<div style="white-space: pre-wrap; overflow-wrap: anywhere">{{ $message->body }}</div>
</div></div>
@endforeach
{{ $messages->links() }}
<form class="card" method="POST" action="{{ route('tickets.reply', $ticket) }}">@csrf
<div class="card-body"><label class="form-label" for="body">{{ __('Reply') }}</label><textarea class="form-control mb-3" id="body" name="body" rows="5" required maxlength="10000">{{ old('body') }}</textarea>
@if($staff)<label class="d-block mb-3"><input class="form-check-input me-2" type="checkbox" name="is_internal" value="1">{{ __('Internal note — visible only to the team') }}</label>@endif
<button class="btn btn-primary">{{ __('Send reply') }}</button></div></form>
</div>
@if($staff)
<div class="col-lg-4">
<div class="card mb-4"><div class="card-body"><h5>{{ __('Customer') }}</h5><p>{{ $ticket->customer->name }}<br>{{ $ticket->customer->email }}</p><p class="mb-0">{{ $ticket->customer->subscription?->bundle?->name ?? __('No subscription') }}</p></div></div>
<form class="card" method="POST" action="{{ route('tickets.update', $ticket) }}">@csrf @method('PUT')
<div class="card-body"><h5>{{ __('Ticket management') }}</h5>
@foreach(['status' => \App\Models\SupportTicket::STATUSES, 'priority' => \App\Models\SupportTicket::PRIORITIES] as $key => $options)
<label class="form-label" for="{{ $key }}">{{ __('workspace.'.$key) }}</label><select class="form-select mb-4" id="{{ $key }}" name="{{ $key }}">@foreach($options as $option)<option value="{{ $option }}" @selected(old($key, $ticket->$key) === $option)>{{ __('workspace.'.$option) }}</option>@endforeach</select>
@endforeach
<label class="form-label" for="assigned_to">{{ __('Assigned to') }}</label><select class="form-select mb-4" id="assigned_to" name="assigned_to"><option value="">{{ __('Unassigned') }}</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected(old('assigned_to', $ticket->assigned_to) == $employee->id)>{{ $employee->name }}</option>@endforeach</select>
<button class="btn btn-primary">{{ __('Save changes') }}</button>
</div></form></div>
@endif
</div>
@endsection
