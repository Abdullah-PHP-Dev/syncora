@extends('layouts.app')
@section('title', __('seller_team.invite'))
@section('content')
<div class="card"><div class="card-body p-4"><h2>{{ __('seller_team.invite') }}</h2><p>{{ __('seller_team.invited_by', ['name' => $member->owner->name, 'email' => $member->email]) }}</p><p class="text-muted">{{ __('seller_team.invitation_help') }}</p><form action="{{ route('seller-workspaces.accept', ['token' => $token]) }}" method="POST">@csrf<button class="btn btn-primary">{{ __('seller_team.accept') }}</button></form><a class="btn btn-link mt-2" href="{{ route('seller-workspaces.index') }}">{{ __('seller_team.workspaces') }}</a></div></div>
@endsection
