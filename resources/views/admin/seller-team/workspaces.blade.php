@extends('layouts.app')
@section('title', __('seller_team.workspaces'))
@section('content')
<h2 class="mb-4">{{ __('seller_team.workspaces') }}</h2>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="card mb-4"><div class="card-body"><h5>{{ __('seller_team.personal') }}</h5><p>{{ auth()->user()->email }}</p><form action="{{ route('seller-workspaces.leave') }}" method="POST">@csrf<button class="btn btn-outline-primary">{{ __('seller_team.use_personal') }}</button></form></div></div>
<div class="row g-3">@forelse($memberships as $membership)<div class="col-md-6"><div class="card h-100"><div class="card-body"><h5>{{ $membership->owner->name }}</h5><p class="text-muted">{{ $membership->owner->email }}</p><form action="{{ route('seller-workspaces.enter', $membership) }}" method="POST">@csrf<button class="btn btn-primary">{{ __('seller_team.open_workspace') }}</button></form></div></div></div>@empty<p class="text-muted">{{ __('seller_team.no_workspaces') }}</p>@endforelse</div>
@endsection
