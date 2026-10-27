@extends('layouts.app')

@section('title', __('google_suite.title') . ' · ' . __('google_suite.nav.' . match ($screen) {
    'analytics' => 'analytics', 'search_console' => 'search_console', 'reports' => 'reports',
    'integrations', 'setup', 'status', 'properties' => 'integrations', 'preferences', 'health', 'settings' => 'settings',
    default => 'overview',
}))

@push('styles')
    <style>
        [v-cloak] { display: none !important; }
    </style>
@endpush

@section('content')
    {{-- Google Suite (Analytics & Insights) - one Vue app per screen; see GoogleSuiteController. --}}
    <div v-cloak>
        <google-suite :boot='@json($boot)'></google-suite>
    </div>
@endsection
