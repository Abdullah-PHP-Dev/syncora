@extends('layouts.app')
@section('title', __('New ticket'))
@section('content')
<h3>{{ __('New ticket') }}</h3>
<form class="card" method="POST" action="{{ route('tickets.store') }}">@csrf
<div class="card-body row g-4">
<div class="col-12"><label class="form-label" for="subject">{{ __('Subject') }}</label><input class="form-control" id="subject" name="subject" value="{{ old('subject') }}" required maxlength="255"></div>
@foreach(['category' => \App\Models\SupportTicket::CATEGORIES, 'priority' => \App\Models\SupportTicket::PRIORITIES] as $key => $options)
<div class="col-md-6"><label class="form-label" for="{{ $key }}">{{ __('workspace.'.$key) }}</label><select class="form-select" id="{{ $key }}" name="{{ $key }}">@foreach($options as $option)<option value="{{ $option }}" @selected(old($key, $key === 'priority' ? 'normal' : 'general') === $option)>{{ __('workspace.'.$option) }}</option>@endforeach</select></div>
@endforeach
<div class="col-12"><label class="form-label" for="body">{{ __('How can we help?') }}</label><textarea class="form-control" id="body" name="body" rows="7" required maxlength="10000">{{ old('body') }}</textarea></div>
<div><button class="btn btn-primary">{{ __('Create ticket') }}</button> <a class="btn btn-outline-secondary" href="{{ route('tickets.index') }}">{{ __('Cancel') }}</a></div>
</div></form>
@endsection
