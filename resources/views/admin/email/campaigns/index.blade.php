@extends('layouts.app')

@section('title', __('email_marketing.campaigns.title'))

@push('styles')
@include('layouts.partials.dash-styles')
@endpush

@section('content')
<div class="socialeaz-dash">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="dash-title mb-0"><i class="bx bx-paper-plane"></i> {{ __('email_marketing.campaigns.heading') }}</h4>
        <a href="{{ route('admin.email.campaigns.create') }}" class="dash-btn dash-btn-primary"><i class="bx bx-plus"></i> {{ __('email_marketing.campaigns.new_campaign') }}</a>
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
                <thead>
                    <tr>
                        <th>{{ __('email_marketing.common.name') }}</th>
                        <th>{{ __('email_marketing.common.audience') }}</th>
                        <th>{{ __('email_marketing.common.status') }}</th>
                        <th>{{ __('email_marketing.common.recipients') }}</th>
                        <th>{{ __('email_marketing.campaigns.open_rate') }}</th>
                        <th>{{ __('email_marketing.campaigns.click_rate') }}</th>
                        <th class="text-end">{{ __('email_marketing.common.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($campaigns as $campaign)
                        <tr>
                            <td><a href="{{ route('admin.email.campaigns.show', $campaign) }}" class="dash-link" style="color:var(--dash-heading);">{{ $campaign->name }}</a></td>
                            <td>{{ $campaign->audience()?->name ?? '—' }}</td>
                            <td>
                                <span class="dash-badge dash-badge-{{ match($campaign->status) { 'sent' => 'success', 'sending' => 'info', 'scheduled' => 'warning', 'failed' => 'danger', default => 'muted' } }}">{{ emailLabel('statuses', $campaign->status) }}</span>
                                @if ($campaign->status === 'scheduled')
                                    <div class="dash-subtitle" style="font-size:.7rem;">{{ $campaign->scheduled_at->translatedFormat('M j, Y H:i') }}</div>
                                @endif
                            </td>
                            <td>{{ $campaign->total_recipients }}</td>
                            <td>{{ $campaign->openRate() }}%</td>
                            <td>{{ $campaign->clickRate() }}%</td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <a href="javascript:;" class="btn dropdown-toggle hide-arrow p-0" data-bs-toggle="dropdown"><i class="bx bx-dots-vertical-rounded"></i></a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        @if ($campaign->isEditable())
                                            <a href="{{ route('admin.email.campaigns.edit', $campaign) }}" class="dropdown-item">{{ __('email_marketing.common.edit') }}</a>
                                            <a href="javascript:;" class="dropdown-item" onclick="if (confirm(@js(__('email_marketing.campaigns.confirm_send')))) { document.getElementById('send-{{ $campaign->id }}').submit(); }">{{ __('email_marketing.campaigns.send_now') }}</a>
                                        @else
                                            <a href="{{ route('admin.email.campaigns.show', $campaign) }}" class="dropdown-item">{{ __('email_marketing.campaigns.view_stats') }}</a>
                                        @endif
                                        <a href="{{ route('admin.email.campaigns.export', $campaign) }}" class="dropdown-item">{{ __('email_marketing.campaigns.export_report') }}</a>
                                        <div class="dropdown-divider"></div>
                                        <a href="javascript:;" class="dropdown-item text-danger" onclick="if (confirm(@js(__('email_marketing.campaigns.confirm_delete')))) { document.getElementById('delete-{{ $campaign->id }}').submit(); }">{{ __('email_marketing.common.delete') }}</a>
                                    </div>
                                </div>
                                <form id="send-{{ $campaign->id }}" action="{{ route('admin.email.campaigns.send', $campaign) }}" method="POST" class="d-none">@csrf</form>
                                <form id="delete-{{ $campaign->id }}" action="{{ route('admin.email.campaigns.destroy', $campaign) }}" method="POST" class="d-none">@csrf @method('DELETE')</form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="dash-empty-row">{{ __('email_marketing.campaigns.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($campaigns->hasPages())
            <div class="mt-3">{{ $campaigns->links() }}</div>
        @endif
    </div>

</div>
@endsection
