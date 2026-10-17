@extends('layouts.app')
@section('title', __('package_access.upgrade_title'))
@section('content')
<div class="card"><div class="card-body py-5 text-center"><h2>{{ __('package_access.upgrade_title') }}</h2><p>{{ $message }}</p><a class="btn btn-primary" href="{{ route('admin.subscription.select') }}">{{ __('package_access.view_packages') }}</a></div></div>
@endsection
