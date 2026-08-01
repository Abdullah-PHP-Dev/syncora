@extends('layouts.app')

@section('title', 'Email Templates')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bx bx-file"></i> Email Templates</h4>
    <a href="{{ route('admin.email.templates.create') }}" class="btn btn-primary btn-sm"><i class="bx bx-plus"></i> New Template</a>
</div>

@if (session('success'))
    <div class="alert alert-success d-flex align-items-center gap-2"><i class="bx bx-check-circle fs-5"></i> {{ session('success') }}</div>
@endif

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Subject</th>
                    <th>Updated</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($templates as $template)
                    <tr>
                        <td>{{ $template->name }}</td>
                        <td>{{ $template->subject }}</td>
                        <td>{{ $template->updated_at->diffForHumans() }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.email.templates.edit', $template) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form action="{{ route('admin.email.templates.destroy', $template) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this template?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">No templates yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($templates->hasPages())
        <div class="card-footer">{{ $templates->links() }}</div>
    @endif
</div>
@endsection
