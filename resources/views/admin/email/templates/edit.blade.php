@extends('layouts.app')

@section('title', 'Edit Email Template')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <a href="{{ route('admin.email.templates.index') }}" class="small text-muted"><i class="bx bx-arrow-back"></i> Templates</a>
        <h4 class="mb-0">Edit Template</h4>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="row g-3">
    <div class="col-lg-8">
        <form action="{{ route('admin.email.templates.update', $template) }}" method="POST">
            @csrf
            @method('PATCH')
            @include('admin.email.templates._form', ['template' => $template])
        </form>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Version History</h6></div>
            <div class="list-group list-group-flush">
                @forelse ($versions as $version)
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold">Version {{ $version->version }}</div>
                            <div class="text-muted small">{{ $version->created_at->diffForHumans() }}</div>
                        </div>
                        <form action="{{ route('admin.email.templates.versions.restore', [$template, $version]) }}" method="POST" onsubmit="return confirm('Restore version {{ $version->version }}? The current body will be saved as a new version first.');">
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary">Restore</button>
                        </form>
                    </div>
                @empty
                    <div class="list-group-item text-muted small">No saved versions yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
