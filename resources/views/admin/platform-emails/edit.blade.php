@extends('layouts.app')
@section('title', $name.' · '.__('platform_emails.title'))

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/profile.css') }}?v={{ filemtime(public_path('assets/css/profile.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/platform-emails.css') }}?v={{ filemtime(public_path('assets/css/platform-emails.css')) }}">
@endpush

@section('content')
@php
    $labels = [
        'actions' => __('platform_emails.actions'), 'editor' => __('platform_emails.editor'), 'blocks' => __('platform_emails.blocks'),
        'badges' => __('platform_emails.badges'), 'languages' => __('platform_emails.languages'), 'log' => __('platform_emails.actions_log'),
    ];
@endphp
<div class="account-page">
    @include('admin.platform-emails._nav', ['title' => $name])
    <platform-email-editor :config='@json($editor)' :labels='@json($labels)'></platform-email-editor>
</div>
@endsection
