@extends('layouts.app')

@section('title', 'Email Segments')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bx bx-filter-alt"></i> Segments</h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createSegmentModal"><i class="bx bx-plus"></i> New Segment</button>
</div>

@if (session('success'))
    <div class="alert alert-success d-flex align-items-center gap-2"><i class="bx bx-check-circle fs-5"></i> {{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger d-flex align-items-center gap-2"><i class="bx bx-error-circle fs-5"></i> {{ session('error') }}</div>
@endif

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Name</th><th>Query</th><th></th></tr></thead>
            <tbody>
                @forelse ($segments as $segment)
                    <tr>
                        <td>{{ $segment->name }}</td>
                        <td><code class="small">{{ $segment->query_json }}</code></td>
                        <td class="text-end">
                            <form action="{{ route('admin.email.segments.destroy', $segment) }}" method="POST" onsubmit="return confirm('Delete this segment?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted py-5">No segments yet. Segments let a campaign target contacts matching a rule (eg. "opened an email in the last 30 days") instead of a static list.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="createSegmentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('admin.email.segments.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">New Segment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" class="form-control" required>
                        @error('name')<p class="text-danger small">{{ $message }}</p>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">SendGrid Query (SGQL) *</label>
                        <textarea name="query_json" class="form-control" rows="3" placeholder="e.g. contact_lists.name = 'Customers' AND CONTAINS(email, '@gmail.com')" required></textarea>
                        <p class="form-text">Uses SendGrid's own query language directly - see SendGrid's Segmentation docs for the full syntax.</p>
                        @error('query_json')<p class="text-danger small">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary w-100">Create Segment</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
