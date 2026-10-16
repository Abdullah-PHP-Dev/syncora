@extends('layouts.app')

@section('title', __('email_marketing.campaigns.create_title'))

@push('styles')
@include('layouts.partials.dash-styles')
@endpush

@section('content')
<div class="socialeaz-dash">

    <nav class="dash-subtitle small mb-2">
        <a href="{{ route('admin.email.dashboard') }}" class="dash-link">{{ __('email_marketing.module') }}</a> /
        <a href="{{ route('admin.email.campaigns.index') }}" class="dash-link">{{ __('email_marketing.common.campaigns') }}</a>
    </nav>

    <div class="email-hero mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4 class="mb-1">{{ __('email_marketing.campaigns.create_title') }}</h4>
                <p>{{ __('email_marketing.campaigns.create_subtitle') }}</p>
            </div>
            <div class="d-none d-md-block text-end">
                <strong style="font-size:.9rem;">{!! __('email_marketing.campaigns.create_tagline') !!}</strong>
                <p class="mb-0" style="font-size:.78rem;">{{ __('email_marketing.campaigns.create_tagline_text') }}</p>
            </div>
        </div>
    </div>

    @if (session('error'))
        <div class="alert alert-danger d-flex align-items-center gap-2"><i class="bx bx-error-circle fs-5"></i> {{ session('error') }}</div>
    @endif

    <form action="{{ route('admin.email.campaigns.store') }}" method="POST">
        @csrf
        @include('admin.email.campaigns._form', ['campaign' => null])
    </form>

</div>
@endsection
