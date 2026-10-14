@extends('layouts.app')

@section('title', __('admin.marketing_tools.ads.header'))

@push('styles')
    <style>
        [v-cloak] { display: none !important; }
    </style>
@endpush

@section('content')

    {{-- Shown only when an ad connection needs reconnecting (links to the Hub). --}}
    <x-connection-alerts capability="ads" />

    <div v-cloak>
        <ads-dashboard :data='@json($data)' :messages='@json(__('ads_dashboard'))' locale="{{ app()->getLocale() }}"></ads-dashboard>
    </div>

@endsection
