@extends('layouts.app')

@section('title', $list->name . ' — Subscribers')

@push('styles')
@include('layouts.partials.dash-styles')
@endpush

@section('content')
<div class="socialeaz-dash">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <a href="{{ route('admin.email.lists.index') }}" class="dash-link"><i class="bx bx-arrow-back"></i> All Lists</a>
            <h4 class="dash-title mb-0">{{ $list->name }}</h4>
        </div>
        <div class="d-flex gap-2">
            <button class="dash-btn dash-btn-ghost" data-bs-toggle="modal" data-bs-target="#importModal"><i class="bx bx-upload"></i> Import CSV</button>
            <button class="dash-btn dash-btn-primary" data-bs-toggle="modal" data-bs-target="#addSubscriberModal"><i class="bx bx-plus"></i> Add Subscriber</button>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success d-flex align-items-center gap-2"><i class="bx bx-check-circle fs-5"></i> {{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger d-flex align-items-center gap-2"><i class="bx bx-error-circle fs-5"></i> {{ session('error') }}</div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><x-metric-card label="Total Contacts" :value="number_format($totalContacts)" /></div>
        <div class="col-6 col-md-3"><x-metric-card label="Subscribed" :value="number_format($statusCounts->get('subscribed', 0))" /></div>
        <div class="col-6 col-md-3"><x-metric-card label="Unsubscribed" :value="number_format($statusCounts->get('unsubscribed', 0))" /></div>
        <div class="col-6 col-md-3"><x-metric-card label="Bounced" :value="number_format($statusCounts->get('bounced', 0) + $statusCounts->get('complained', 0))" /></div>
    </div>

    <div class="dash-card mb-3">
        <form method="GET" class="d-flex gap-2 flex-wrap">
            <input type="text" name="search" class="dash-input" placeholder="Search by email or name" value="{{ request('search') }}" style="max-width:280px">
            <select name="status" class="dash-input" style="max-width:180px">
                <option value="">All statuses</option>
                @foreach (['subscribed', 'unsubscribed', 'bounced', 'complained'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <button class="dash-btn dash-btn-ghost" type="submit">Filter</button>
            @if (request('search') || request('status'))
                <a href="{{ route('admin.email.lists.subscribers.index', $list) }}" class="dash-btn dash-btn-ghost">Clear</a>
            @endif
        </form>
    </div>

    <div class="dash-card">
        <div class="table-responsive">
            <table class="dash-table mb-0">
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
                                <span class="dash-badge dash-badge-{{ match($subscriber->status) { 'subscribed' => 'success', 'unsubscribed' => 'muted', 'bounced', 'complained' => 'danger', default => 'muted' } }} text-capitalize">{{ $subscriber->status }}</span>
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
                        <tr><td colspan="5" class="dash-empty-row">No subscribers in this list yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($subscribers->hasPages())
            <div class="mt-3">{{ $subscribers->links() }}</div>
        @endif
    </div>

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
