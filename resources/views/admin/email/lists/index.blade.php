@extends('layouts.app')

@section('title', __('email_marketing.lists.title'))

@push('styles')
@include('layouts.partials.dash-styles')
@endpush

@section('content')
<div class="socialeaz-dash">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="dash-title mb-0"><i class="bx bx-list-ul"></i> {{ __('email_marketing.lists.title') }}</h4>
        <button class="dash-btn dash-btn-primary" data-bs-toggle="modal" data-bs-target="#createListModal"><i class="bx bx-plus"></i> {{ __('email_marketing.lists.new_list') }}</button>
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
                <div class="dash-card h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <h6 class="mb-1" style="color:var(--dash-heading);font-weight:600;">{{ $list->name }}</h6>
                        <div class="dropdown">
                            <a href="javascript:;" class="btn p-0" data-bs-toggle="dropdown"><i class="bx bx-dots-vertical-rounded"></i></a>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a href="javascript:;" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editListModal{{ $list->id }}">{{ __('email_marketing.lists.rename') }}</a>
                                <form action="{{ route('admin.email.lists.destroy', $list) }}" method="POST" onsubmit="return confirm(@js(__('email_marketing.lists.confirm_delete')));">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="dropdown-item text-danger">{{ __('email_marketing.common.delete') }}</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <p class="dash-subtitle small mb-3">{{ $list->description ?: __('email_marketing.lists.no_description') }}</p>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="dash-badge dash-badge-info">{{ trans_choice('email_marketing.lists.subscribers', $list->subscribers_count) }}</span>
                        <span class="dash-subtitle" style="font-size:.7rem;">{{ __('email_marketing.lists.created', ['date' => $list->created_at->translatedFormat('M j, Y')]) }}</span>
                    </div>
                    <div>
                        <a href="{{ route('admin.email.lists.subscribers.index', $list) }}" class="dash-btn dash-btn-ghost w-100 justify-content-center">{{ __('email_marketing.lists.manage_subscribers') }}</a>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="editListModal{{ $list->id }}" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form action="{{ route('admin.email.lists.update', $list) }}" method="POST">
                            @csrf @method('PATCH')
                            <div class="modal-header">
                                <h5 class="modal-title">{{ __('email_marketing.lists.rename_title') }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('email_marketing.common.name_required') }}</label>
                                    <input type="text" name="name" class="form-control" value="{{ $list->name }}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('email_marketing.common.description') }}</label>
                                    <textarea name="description" class="form-control" rows="2">{{ $list->description }}</textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-primary w-100">{{ __('email_marketing.common.save') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="dash-card text-center py-5">
                    <i class="bx bx-list-ul fs-1 d-block mb-2" style="color:var(--dash-muted);"></i>
                    <span class="dash-subtitle">{{ __('email_marketing.lists.empty') }}</span>
                </div>
            </div>
        @endforelse
    </div>

</div>

<div class="modal fade" id="createListModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('admin.email.lists.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('email_marketing.lists.new_list') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('email_marketing.common.name_required') }}</label>
                        <input type="text" name="name" class="form-control" required>
                        @error('name')<p class="text-danger small">{{ $message }}</p>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('email_marketing.common.description') }}</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary w-100">{{ __('email_marketing.lists.create_list') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
