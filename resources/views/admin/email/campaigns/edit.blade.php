@extends('layouts.app')

@section('title', 'Edit Campaign')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <a href="{{ route('admin.email.campaigns.index') }}" class="small text-muted"><i class="bx bx-arrow-back"></i> Campaigns</a>
        <h4 class="mb-0">Edit Campaign</h4>
    </div>
    <span class="badge bg-label-{{ $campaign->status === 'scheduled' ? 'warning' : 'secondary' }} text-capitalize">{{ $campaign->status }}</span>
</div>

@if (session('error'))
    <div class="alert alert-danger d-flex align-items-center gap-2"><i class="bx bx-error-circle fs-5"></i> {{ session('error') }}</div>
@endif

<form action="{{ route('admin.email.campaigns.update', $campaign) }}" method="POST">
    @csrf
    @method('PATCH')
    @include('admin.email.campaigns._form', ['campaign' => $campaign])
</form>
@endsection
