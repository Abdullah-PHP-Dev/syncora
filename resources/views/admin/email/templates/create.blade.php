@extends('layouts.app')

@section('title', __('email_marketing.templates.create_title'))

@push('styles')
@include('layouts.partials.dash-styles')
@include('admin.email.templates._editor-styles')
@endpush

@section('content')
<div class="socialeaz-dash">

    <nav class="dash-subtitle small mb-2">
        <a href="{{ route('admin.email.dashboard') }}" class="dash-link">{{ __('email_marketing.module') }}</a> / {{ __('email_marketing.templates.create_crumb') }}
    </nav>

    <form action="{{ route('admin.email.templates.store') }}" method="POST">
        @csrf

        <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-3">
            <div>
                <h4 class="dash-title mb-1">{{ __('email_marketing.templates.create_title') }}</h4>
                <p class="dash-subtitle mb-0">{{ __('email_marketing.templates.create_subtitle') }}</p>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" name="status" value="draft" class="dash-btn dash-btn-ghost">{{ __('email_marketing.templates.save_draft') }}</button>
                <button type="submit" name="status" value="published" class="dash-btn dash-btn-primary">{{ __('email_marketing.templates.save_template') }}</button>
            </div>
        </div>

        @if (session('error'))
            <div class="alert alert-danger d-flex align-items-center gap-2"><i class="bx bx-error-circle fs-5"></i> {{ session('error') }}</div>
        @endif

        @include('admin.email.templates._form', ['template' => null])
    </form>

</div>
@endsection
