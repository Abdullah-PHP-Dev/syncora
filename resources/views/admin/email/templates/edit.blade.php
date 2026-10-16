@extends('layouts.app')

@section('title', __('email_marketing.templates.edit_title'))

@push('styles')
@include('layouts.partials.dash-styles')
@include('admin.email.templates._editor-styles')
@endpush

@section('content')
<div class="socialeaz-dash">

    <nav class="dash-subtitle small mb-2">
        <a href="{{ route('admin.email.dashboard') }}" class="dash-link">{{ __('email_marketing.module') }}</a> /
        <a href="{{ route('admin.email.templates.index') }}" class="dash-link">{{ __('email_marketing.common.templates') }}</a> / {{ __('email_marketing.common.edit') }}
    </nav>

    <form action="{{ route('admin.email.templates.update', $template) }}" method="POST">
        @csrf
        @method('PATCH')

        <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-3">
            <div>
                <h4 class="dash-title mb-1">{{ __('email_marketing.templates.edit_title') }}</h4>
                <p class="dash-subtitle mb-0">{{ $template->name }}</p>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" name="status" value="draft" class="dash-btn dash-btn-ghost">{{ __('email_marketing.templates.save_draft') }}</button>
                <button type="submit" name="status" value="published" class="dash-btn dash-btn-primary">{{ __('email_marketing.templates.save_template') }}</button>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success d-flex align-items-center gap-2"><i class="bx bx-check-circle fs-5"></i> {{ session('success') }}</div>
        @endif

        @include('admin.email.templates._form', ['template' => $template])
    </form>

    <div class="dash-card mt-3">
        <div class="dash-card-header">
            <h6>{{ __('email_marketing.templates.version_history') }}</h6>
        </div>
        <div class="table-responsive">
            <table class="dash-table mb-0">
                <tbody>
                    @forelse ($versions as $version)
                        <tr>
                            <td>
                                <strong style="color:var(--dash-heading);">{{ __('email_marketing.templates.version', ['number' => $version->version]) }}</strong>
                                <div class="dash-subtitle" style="font-size:.72rem;">{{ $version->created_at->diffForHumans() }}</div>
                            </td>
                            <td class="text-end">
                                <form action="{{ route('admin.email.templates.versions.restore', [$template, $version]) }}" method="POST" onsubmit="return confirm(@js(__('email_marketing.templates.confirm_restore', ['number' => $version->version])));">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary">{{ __('email_marketing.common.restore') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="dash-empty-row">{{ __('email_marketing.templates.no_versions') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
