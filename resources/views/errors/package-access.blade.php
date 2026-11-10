@extends('layouts.app')
@section('title', __('package_access.upgrade_title'))
@section('content')
<div class="card"><div class="card-body py-5 text-center"><h2>{{ __('package_access.upgrade_title') }}</h2><p>{{ $message }}</p>
@if(request()->attributes->has('workspace_member'))
    {{-- Teammates can't open billing; only the organization owner can upgrade. --}}
    <p class="text-muted mb-0">{{ __('package_access.member_upgrade') }}</p>
@else
    <a class="btn btn-primary" href="{{ route('admin.subscription.select') }}">{{ __('package_access.view_packages') }}</a>
@endif
</div></div>
@endsection
