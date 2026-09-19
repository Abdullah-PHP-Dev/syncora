@extends('layouts.app')

@section('title', 'Create Email Campaign')

@push('styles')
@include('layouts.partials.dash-styles')
@endpush

@section('content')
<div class="socialeaz-dash">

    <nav class="dash-subtitle small mb-2">
        <a href="{{ route('admin.email.dashboard') }}" class="dash-link">Email Marketing</a> /
        <a href="{{ route('admin.email.campaigns.index') }}" class="dash-link">Campaigns</a>
    </nav>

    <div class="email-hero mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4 class="mb-1">Create Email Campaign</h4>
                <p>Design, send and track your email campaigns to engage your audience and grow your business.</p>
            </div>
            <div class="d-none d-md-block text-end">
                <strong style="font-size:.9rem;">Reach the right audience<br>with powerful email campaigns.</strong>
                <p class="mb-0" style="font-size:.78rem;">Build beautiful emails, get higher opens, and drive more engagement.</p>
            </div>
        </div>
    </div>

    @if (session('error'))
        <div class="alert alert-danger d-flex align-items-center gap-2"><i class="bx bx-error-circle fs-5"></i> {{ session('error') }}</div>
    @endif

    <form action="{{ route('admin.email.campaigns.store') }}" method="POST">
        @csrf
        @include('admin.email.campaigns._form', ['campaign' => null])
    </form>

</div>
@endsection
