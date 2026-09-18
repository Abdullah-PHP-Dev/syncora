@extends('layouts.app')

@section('title', $campaign->name)

@section('content')
<div class="socialeaz-dash">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <a href="{{ route('admin.email.campaigns.index') }}" class="dash-link"><i class="bx bx-arrow-back"></i> Campaigns</a>
            <h4 class="dash-title mb-0">{{ $campaign->name }}</h4>
            <span class="dash-subtitle" style="font-size:.8rem;">
                @if ($campaign->sent_at)
                    Sent {{ $campaign->sent_at->format('M j, Y') }}
                @else
                    Created {{ $campaign->created_at->format('M j, Y') }}
                @endif
            </span>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <span class="badge text-capitalize bg-label-{{ match($campaign->status) { 'sent' => 'success', 'sending' => 'info', 'scheduled' => 'warning', 'failed' => 'danger', default => 'secondary' } }}">{{ $campaign->status }}</span>
            <a href="{{ route('admin.email.campaigns.export', $campaign) }}" class="dash-btn dash-btn-ghost"><i class="bx bx-download"></i> Export Report</a>
            @if ($campaign->isEditable())
                <a href="{{ route('admin.email.campaigns.edit', $campaign) }}" class="dash-btn dash-btn-ghost">Edit</a>
                <form action="{{ route('admin.email.campaigns.send', $campaign) }}" method="POST" onsubmit="return confirm('Send this campaign now?');">
                    @csrf
                    <button type="submit" class="dash-btn dash-btn-primary">Send Now</button>
                </form>
            @endif
        </div>
    </div>

    @if ($campaign->error_message)
        <div class="alert alert-danger">{{ $campaign->error_message }}</div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><x-metric-card label="Sent" :value="number_format($campaign->sent_count)" /></div>
        <div class="col-6 col-md-3"><x-metric-card label="Delivered" :value="number_format($campaign->delivered_count)" /></div>
        <div class="col-6 col-md-3">
            <x-metric-card label="Opens" :value="number_format($campaign->opened_count)">
                <x-slot:foot>{{ $campaign->openRate() }}% open rate</x-slot:foot>
            </x-metric-card>
        </div>
        <div class="col-6 col-md-3">
            <x-metric-card label="Clicks" :value="number_format($campaign->clicked_count)">
                <x-slot:foot>{{ $campaign->clickRate() }}% click rate</x-slot:foot>
            </x-metric-card>
        </div>
        <div class="col-6 col-md-3"><x-metric-card label="Bounces" :value="number_format($campaign->bounced_count)" /></div>
        <div class="col-6 col-md-3"><x-metric-card label="Blocks" :value="number_format($campaign->blockedCount())" /></div>
        <div class="col-6 col-md-3"><x-metric-card label="Spam Reports" :value="number_format($campaign->complained_count)" /></div>
        <div class="col-6 col-md-3"><x-metric-card label="Unsubscribes" :value="number_format($campaign->unsubscribed_count)" /></div>
    </div>

    <div class="dash-card">
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#reportEvents" type="button" role="tab">Events</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#reportStatistics" type="button" role="tab">Statistics</button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="reportEvents" role="tabpanel">
                <div class="table-responsive">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Email</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($events as $event)
                                <tr>
                                    <td>
                                        <span class="dash-badge dash-badge-{{ in_array($event->event_type, ['bounce','blocked','spamreport']) ? 'danger' : (in_array($event->event_type, ['unsubscribe','group_unsubscribe']) ? 'muted' : 'success') }} text-capitalize">{{ $event->event_type }}</span>
                                    </td>
                                    <td>{{ $event->recipient_email }}</td>
                                    <td>{{ $event->event_at?->format('M j, Y H:i') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center py-4" style="color:var(--dash-muted);">No events recorded yet - they arrive here in real time via SendGrid's Event Webhook once this campaign has been delivered.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($events->hasPages())
                    <div class="mt-3">{{ $events->links() }}</div>
                @endif
            </div>

            <div class="tab-pane fade" id="reportStatistics" role="tabpanel">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="dash-card" style="box-shadow:none;background:var(--dash-card-hover);">
                            <div class="dash-stat-label">Delivery Rate</div>
                            <div class="dash-stat-value">{{ $campaign->total_recipients > 0 ? round(($campaign->delivered_count / $campaign->total_recipients) * 100, 1) : 0 }}%</div>
                            <div class="dash-stat-foot">{{ number_format($campaign->delivered_count) }} of {{ number_format($campaign->total_recipients) }} recipients</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="dash-card" style="box-shadow:none;background:var(--dash-card-hover);">
                            <div class="dash-stat-label">Open Rate</div>
                            <div class="dash-stat-value">{{ $campaign->openRate() }}%</div>
                            <div class="dash-stat-foot">{{ number_format($campaign->opened_count) }} opens</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="dash-card" style="box-shadow:none;background:var(--dash-card-hover);">
                            <div class="dash-stat-label">Click Rate</div>
                            <div class="dash-stat-value">{{ $campaign->clickRate() }}%</div>
                            <div class="dash-stat-foot">{{ number_format($campaign->clicked_count) }} clicks</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="dash-card" style="box-shadow:none;background:var(--dash-card-hover);">
                            <div class="dash-stat-label">Bounce Rate</div>
                            <div class="dash-stat-value">{{ $campaign->total_recipients > 0 ? round(($campaign->bounced_count / $campaign->total_recipients) * 100, 1) : 0 }}%</div>
                            <div class="dash-stat-foot">{{ number_format($campaign->bounced_count) }} bounces</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="dash-card" style="box-shadow:none;background:var(--dash-card-hover);">
                            <div class="dash-stat-label">Complaint Rate</div>
                            <div class="dash-stat-value">{{ $campaign->delivered_count > 0 ? round(($campaign->complained_count / $campaign->delivered_count) * 100, 2) : 0 }}%</div>
                            <div class="dash-stat-foot">{{ number_format($campaign->complained_count) }} spam reports</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="dash-card" style="box-shadow:none;background:var(--dash-card-hover);">
                            <div class="dash-stat-label">Unsubscribe Rate</div>
                            <div class="dash-stat-value">{{ $campaign->delivered_count > 0 ? round(($campaign->unsubscribed_count / $campaign->delivered_count) * 100, 2) : 0 }}%</div>
                            <div class="dash-stat-foot">{{ number_format($campaign->unsubscribed_count) }} unsubscribes</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
