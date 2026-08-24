@extends('layouts.app')
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/subscription.css') }}" />
@endpush

@section('content')
    <div id="subscriptionApp">
        <subscription-plans></subscription-plans>
    </div>

@endsection