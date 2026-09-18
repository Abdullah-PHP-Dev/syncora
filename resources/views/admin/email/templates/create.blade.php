@extends('layouts.app')

@section('title', 'Create Email Template')

@push('styles')
@include('layouts.partials.dash-styles')
@include('admin.email.templates._editor-styles')
@endpush

@section('content')
<div class="socialeaz-dash">

    <nav class="dash-subtitle small mb-2">
        <a href="{{ route('admin.email.dashboard') }}" class="dash-link">Email Marketing</a> / Create Template
    </nav>

    <form action="{{ route('admin.email.templates.store') }}" method="POST">
        @csrf

        <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-3">
            <div>
                <h4 class="dash-title mb-1">Create Email Template</h4>
                <p class="dash-subtitle mb-0">Design beautiful and engaging email templates for your campaigns. Use our editor or AI to create the perfect template.</p>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" name="status" value="draft" class="dash-btn dash-btn-ghost">Save as Draft</button>
                <button type="submit" name="status" value="published" class="dash-btn dash-btn-primary">Save Template</button>
            </div>
        </div>

        @if (session('error'))
            <div class="alert alert-danger d-flex align-items-center gap-2"><i class="bx bx-error-circle fs-5"></i> {{ session('error') }}</div>
        @endif

        @include('admin.email.templates._form', ['template' => null])
    </form>

</div>
@endsection
