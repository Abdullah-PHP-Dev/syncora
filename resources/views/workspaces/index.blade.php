@extends('layouts.app')

@section('title', __('workspaces.title'))

@section('content')
    {{-- Workspace picker (WorkspacePicker.vue): choose, create or join a workspace. --}}
    <workspace-picker
        :picker='@json($picker)'
        flash-text="{{ session('success') ?: ($errors->first() ?: '') }}"
    ></workspace-picker>
@endsection
