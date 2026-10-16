@extends('layouts.app')

@section('title', __('email_marketing.subscribers.page_title', ['list' => $list->name]))

@push('styles')
@include('layouts.partials.dash-styles')
@endpush

@section('content')
<div class="socialeaz-dash">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <a href="{{ route('admin.email.lists.index') }}" class="dash-link"><i class="bx bx-arrow-back"></i> {{ __('email_marketing.subscribers.all_lists') }}</a>
            <h4 class="dash-title mb-0">{{ $list->name }}</h4>
        </div>
        <div class="d-flex gap-2">
            <button class="dash-btn dash-btn-ghost" data-bs-toggle="modal" data-bs-target="#importModal"><i class="bx bx-upload"></i> {{ __('email_marketing.subscribers.import_csv') }}</button>
            <button class="dash-btn dash-btn-primary" data-bs-toggle="modal" data-bs-target="#addSubscriberModal"><i class="bx bx-plus"></i> {{ __('email_marketing.subscribers.add_subscriber') }}</button>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success d-flex align-items-center gap-2"><i class="bx bx-check-circle fs-5"></i> {{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger d-flex align-items-center gap-2"><i class="bx bx-error-circle fs-5"></i> {{ session('error') }}</div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><x-metric-card :label="__('email_marketing.subscribers.total_contacts')" :value="number_format($totalContacts)" /></div>
        <div class="col-6 col-md-3"><x-metric-card :label="__('email_marketing.statuses.subscribed')" :value="number_format($statusCounts->get('subscribed', 0))" /></div>
        <div class="col-6 col-md-3"><x-metric-card :label="__('email_marketing.statuses.unsubscribed')" :value="number_format($statusCounts->get('unsubscribed', 0))" /></div>
        <div class="col-6 col-md-3"><x-metric-card :label="__('email_marketing.statuses.bounced')" :value="number_format($statusCounts->get('bounced', 0) + $statusCounts->get('complained', 0))" /></div>
    </div>

    <div class="dash-card mb-3">
        <form method="GET" class="d-flex gap-2 flex-wrap">
            <input type="text" name="search" class="dash-input" placeholder="{{ __('email_marketing.subscribers.search_placeholder') }}" value="{{ request('search') }}" style="max-width:280px">
            <select name="status" class="dash-input" style="max-width:180px">
                <option value="">{{ __('email_marketing.subscribers.all_statuses') }}</option>
                @foreach (['subscribed', 'unsubscribed', 'bounced', 'complained'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ emailLabel('statuses', $status) }}</option>
                @endforeach
            </select>
            <button class="dash-btn dash-btn-ghost" type="submit">{{ __('email_marketing.common.filter') }}</button>
            @if (request('search') || request('status'))
                <a href="{{ route('admin.email.lists.subscribers.index', $list) }}" class="dash-btn dash-btn-ghost">{{ __('email_marketing.common.clear') }}</a>
            @endif
        </form>
    </div>

    <div class="dash-card">
        <div class="table-responsive">
            <table class="dash-table mb-0">
                <thead>
                    <tr>
                        <th>{{ __('email_marketing.common.email') }}</th>
                        <th>{{ __('email_marketing.common.name') }}</th>
                        <th>{{ __('email_marketing.common.status') }}</th>
                        <th>{{ __('email_marketing.subscribers.added') }}</th>
                        <th class="text-end">{{ __('email_marketing.common.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subscribers as $subscriber)
                        <tr>
                            <td dir="ltr" class="text-start">{{ $subscriber->email }}</td>
                            <td dir="auto">{{ $subscriber->name ?: '—' }}</td>
                            <td>
                                <span class="dash-badge dash-badge-{{ match($subscriber->status) { 'subscribed' => 'success', 'unsubscribed' => 'muted', 'bounced', 'complained' => 'danger', default => 'muted' } }}">{{ emailLabel('statuses', $subscriber->status) }}</span>
                            </td>
                            <td>{{ $subscriber->pivot->created_at?->translatedFormat('M j, Y') }}</td>
                            <td class="text-end">
                                <form action="{{ route('admin.email.lists.subscribers.destroy', [$list, $subscriber]) }}" method="POST" onsubmit="return confirm(@js(__('email_marketing.subscribers.confirm_remove')));">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('email_marketing.common.remove') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="dash-empty-row">{{ __('email_marketing.subscribers.empty') }}</td></tr>
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
                    <h5 class="modal-title">{{ __('email_marketing.subscribers.add_subscriber') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('email_marketing.common.email_required') }}</label>
                        <input type="email" name="email" class="form-control" required>
                        @error('email')<p class="text-danger small">{{ $message }}</p>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('email_marketing.common.name') }}</label>
                        <input type="text" name="name" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary w-100">{{ __('email_marketing.subscribers.add') }}</button>
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
                    <h5 class="modal-title">{{ __('email_marketing.subscribers.import_title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">{!! __('email_marketing.subscribers.import_help', ['email' => '<code>email</code>', 'name' => '<code>name</code>']) !!}</p>
                    <div class="mb-3">
                        <input type="file" name="file" accept=".csv,text/csv" class="form-control" required>
                        @error('file')<p class="text-danger small">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary w-100">{{ __('email_marketing.subscribers.import') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
