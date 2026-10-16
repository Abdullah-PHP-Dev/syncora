@extends('layouts.app')

@section('title', __('email_marketing.segments.title'))

@push('styles')
@include('layouts.partials.dash-styles')
@endpush

@section('content')
<div class="socialeaz-dash">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="dash-title mb-0"><i class="bx bx-filter-alt"></i> {{ __('email_marketing.segments.heading') }}</h4>
        <button class="dash-btn dash-btn-primary" data-bs-toggle="modal" data-bs-target="#createSegmentModal"><i class="bx bx-plus"></i> {{ __('email_marketing.segments.new_segment') }}</button>
    </div>

    @if (session('success'))
        <div class="alert alert-success d-flex align-items-center gap-2"><i class="bx bx-check-circle fs-5"></i> {{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger d-flex align-items-center gap-2"><i class="bx bx-error-circle fs-5"></i> {{ session('error') }}</div>
    @endif

    <div class="dash-card">
        <div class="table-responsive">
            <table class="dash-table mb-0">
                <thead><tr><th>{{ __('email_marketing.common.name') }}</th><th>{{ __('email_marketing.segments.query') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($segments as $segment)
                        <tr>
                            <td>{{ $segment->name }}</td>
                            <td dir="ltr" class="text-start"><code class="small">{{ $segment->query_json }}</code></td>
                            <td class="text-end">
                                <form action="{{ route('admin.email.segments.destroy', $segment) }}" method="POST" onsubmit="return confirm(@js(__('email_marketing.segments.confirm_delete')));">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('email_marketing.common.delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="dash-empty-row">{{ __('email_marketing.segments.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

<div class="modal fade" id="createSegmentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('admin.email.segments.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('email_marketing.segments.new_segment') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('email_marketing.common.name_required') }}</label>
                        <input type="text" name="name" class="form-control" required>
                        @error('name')<p class="text-danger small">{{ $message }}</p>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('email_marketing.segments.query_label') }}</label>
                        <textarea name="query_json" class="form-control" rows="3" dir="ltr" placeholder="e.g. contact_lists.name = 'Customers' AND CONTAINS(email, '@gmail.com')" required></textarea>
                        <p class="form-text">{{ __('email_marketing.segments.query_help') }}</p>
                        @error('query_json')<p class="text-danger small">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary w-100">{{ __('email_marketing.segments.create_segment') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
