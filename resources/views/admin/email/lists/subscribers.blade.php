@extends('layouts.app')

@section('title', $list->name . ' — Subscribers')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <a href="{{ route('admin.email.lists.index') }}" class="small text-muted"><i class="bx bx-arrow-back"></i> All Lists</a>
        <h4 class="mb-0">{{ $list->name }}</h4>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#importModal"><i class="bx bx-upload"></i> Import CSV</button>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSubscriberModal"><i class="bx bx-plus"></i> Add Subscriber</button>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success d-flex align-items-center gap-2"><i class="bx bx-check-circle fs-5"></i> {{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger d-flex align-items-center gap-2"><i class="bx bx-error-circle fs-5"></i> {{ session('error') }}</div>
@endif

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by email or name" value="{{ request('search') }}" style="max-width:280px">
            <button class="btn btn-sm btn-outline-secondary" type="submit">Search</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Added</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($subscribers as $subscriber)
                    <tr>
                        <td>{{ $subscriber->email }}</td>
                        <td>{{ $subscriber->name ?: '—' }}</td>
                        <td>
                            <span class="badge bg-label-{{ match($subscriber->status) { 'subscribed' => 'success', 'unsubscribed' => 'secondary', 'bounced' => 'danger', 'complained' => 'danger', default => 'secondary' } }} text-capitalize">{{ $subscriber->status }}</span>
                        </td>
                        <td>{{ $subscriber->pivot->created_at?->format('M j, Y') }}</td>
                        <td class="text-end">
                            <form action="{{ route('admin.email.lists.subscribers.destroy', [$list, $subscriber]) }}" method="POST" onsubmit="return confirm('Remove this subscriber from the list?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No subscribers in this list yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($subscribers->hasPages())
        <div class="card-footer">{{ $subscribers->links() }}</div>
    @endif
</div>

<div class="modal fade" id="addSubscriberModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('admin.email.lists.subscribers.store', $list) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Subscriber</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required>
                        @error('email')<p class="text-danger small">{{ $message }}</p>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary w-100">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('admin.email.lists.subscribers.import', $list) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Import Subscribers (CSV)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">CSV must have a header row with an <code>email</code> column, and optionally a <code>name</code> column.</p>
                    <div class="mb-3">
                        <input type="file" name="file" accept=".csv,text/csv" class="form-control" required>
                        @error('file')<p class="text-danger small">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary w-100">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
