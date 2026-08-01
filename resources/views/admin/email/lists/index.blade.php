@extends('layouts.app')

@section('title', 'Email Lists')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bx bx-list-ul"></i> Email Lists</h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createListModal"><i class="bx bx-plus"></i> New List</button>
</div>

@if (session('success'))
    <div class="alert alert-success d-flex align-items-center gap-2"><i class="bx bx-check-circle fs-5"></i> {{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger d-flex align-items-center gap-2"><i class="bx bx-error-circle fs-5"></i> {{ session('error') }}</div>
@endif

<div class="row g-3">
    @forelse ($lists as $list)
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <h6 class="mb-1">{{ $list->name }}</h6>
                        <div class="dropdown">
                            <a href="javascript:;" class="btn p-0" data-bs-toggle="dropdown"><i class="bx bx-dots-vertical-rounded"></i></a>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a href="javascript:;" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editListModal{{ $list->id }}">Rename</a>
                                <form action="{{ route('admin.email.lists.destroy', $list) }}" method="POST" onsubmit="return confirm('Delete this list? Subscribers stay in your account but will be removed from it.');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="dropdown-item text-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted small mb-3">{{ $list->description ?: 'No description.' }}</p>
                    <span class="badge bg-label-primary mb-3">{{ $list->subscribers_count }} subscriber{{ $list->subscribers_count === 1 ? '' : 's' }}</span>
                    <div>
                        <a href="{{ route('admin.email.lists.subscribers.index', $list) }}" class="btn btn-sm btn-outline-primary w-100">Manage Subscribers</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="editListModal{{ $list->id }}" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form action="{{ route('admin.email.lists.update', $list) }}" method="POST">
                        @csrf @method('PATCH')
                        <div class="modal-header">
                            <h5 class="modal-title">Rename List</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Name *</label>
                                <input type="text" name="name" class="form-control" value="{{ $list->name }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="2">{{ $list->description }}</textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary w-100">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5 text-muted">
                    <i class="bx bx-list-ul fs-1 d-block mb-2"></i>
                    No lists yet. Create one to start adding subscribers.
                </div>
            </div>
        </div>
    @endforelse
</div>

<div class="modal fade" id="createListModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('admin.email.lists.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">New List</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" class="form-control" required>
                        @error('name')<p class="text-danger small">{{ $message }}</p>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary w-100">Create List</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
