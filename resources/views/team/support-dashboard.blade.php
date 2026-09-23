@extends('layouts.app')
@section('title', __('Customer support'))
@section('content')
<h3>{{ __('Customer support') }}</h3><p class="text-muted">{{ __('Review tickets, help subscribers, and follow up on open requests.') }}</p>
@include('team.ticket-summary')
@endsection
