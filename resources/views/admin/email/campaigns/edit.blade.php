@extends('layouts.app')

@section('title', 'Edit Email Campaign')

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
                <h4 class="mb-1">Edit Email Campaign</h4>
                <p>{{ $campaign->name }}</p>
            </div>
            <span class="dash-badge dash-badge-{{ $campaign->status === 'scheduled' ? 'warning' : 'muted' }} text-capitalize" style="background:rgba(255,255,255,.18);color:#fff;">{{ $campaign->status }}</span>
        </div>
    </div>

    @if (session('error'))
        <div class="alert alert-danger d-flex align-items-center gap-2"><i class="bx bx-error-circle fs-5"></i> {{ session('error') }}</div>
    @endif

    <form action="{{ route('admin.email.campaigns.update', $campaign) }}" method="POST">
        @csrf
        @method('PATCH')
        @include('admin.email.campaigns._form', ['campaign' => $campaign])
    </form>

</div>
@endsection
