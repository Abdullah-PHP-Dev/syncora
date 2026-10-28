@extends('layouts.app')

@section('title', __('ads_campaigns.page_title'))

@push('styles')
    <style>[v-cloak] { display: none !important; }</style>
@endpush

@section('content')
    {{-- Shown only when an ad connection needs reconnecting (links to the Hub). --}}
    <x-connection-alerts capability="ads" />

    @foreach (['error' => 'danger', 'success' => 'success'] as $flash => $tone)
        @if (session($flash))
            <div class="alert alert-{{ $tone }} alert-dismissible" role="alert">
                {{ session($flash) }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('ads_campaigns.dismiss') }}"></button>
            </div>
        @endif
    @endforeach

    <div v-cloak>
        <facebook-campaigns :data='@json($data)' :messages='@json($messages)' locale="{{ app()->getLocale() }}" csrf="{{ csrf_token() }}"></facebook-campaigns>
    </div>
@endsection
