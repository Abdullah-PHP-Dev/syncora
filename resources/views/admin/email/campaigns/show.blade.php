@extends('layouts.app')

@section('title', $campaign->name)

@push('styles')
@include('layouts.partials.dash-styles')
<style>
    .socialeaz-dash .stat-icon-circle { width: 38px; height: 38px; border-radius: .65rem; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
    .socialeaz-dash .meta-item { display: flex; align-items: center; gap: .4rem; color: var(--dash-muted); font-size: .8125rem; }
    .socialeaz-dash .meta-item .bx { font-size: 1rem; }
    .socialeaz-dash .preview-empty { display: flex; align-items: center; justify-content: center; height: 260px; color: var(--dash-muted); font-size: .8125rem; border: 1px dashed var(--dash-border); border-radius: .6rem; }
</style>
@endpush

@php
    $rates = [
        ['label' => 'Sent', 'value' => $campaign->sent_count, 'rate' => $campaign->total_recipients > 0 ? round(($campaign->sent_count / $campaign->total_recipients) * 100, 1) : ($campaign->sent_count > 0 ? 100.0 : 0.0), 'icon' => 'bx-paper-plane', 'color' => '#0ea5e9'],
        ['label' => 'Delivered', 'value' => $campaign->delivered_count, 'rate' => $campaign->deliveryRate(), 'icon' => 'bx-check-double', 'color' => '#16a34a'],
        ['label' => 'Opens', 'value' => $campaign->opened_count, 'rate' => $campaign->openRate(), 'icon' => 'bx-mouse', 'color' => '#7c5cff'],
        ['label' => 'Clicks', 'value' => $campaign->clicked_count, 'rate' => $campaign->clickRate(), 'icon' => 'bx-link', 'color' => '#0891b2'],
        ['label' => 'Bounces', 'value' => $campaign->bounced_count, 'rate' => $campaign->bounceRate(), 'icon' => 'bx-error', 'color' => '#e11d48'],
        ['label' => 'Unsubscribes', 'value' => $campaign->unsubscribed_count, 'rate' => $campaign->unsubscribeRate(), 'icon' => 'bx-user-x', 'color' => '#d97706'],
    ];
    $campaignTypeLabel = match ($campaign->campaign_type) { 'newsletter' => 'Newsletter', default => 'One-time Campaign' };
@endphp

@section('content')
<div class="socialeaz-dash">

    <nav class="mb-2" style="font-size:.78rem;">
        <a href="{{ route('admin.email.dashboard') }}" class="dash-link" style="color:var(--dash-muted);">Email Marketing</a>
        <span class="mx-1" style="color:var(--dash-muted);">/</span>
        <a href="{{ route('admin.email.campaigns.index') }}" class="dash-link" style="color:var(--dash-muted);">Campaigns</a>
        <span class="mx-1" style="color:var(--dash-muted);">/</span>
        <span style="color:var(--dash-heading);font-weight:600;">Campaign Details</span>
    </nav>

    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h4 class="dash-title mb-0">{{ $campaign->name }}</h4>
                <span class="dash-badge dash-badge-{{ match($campaign->status) { 'sent' => 'success', 'sending' => 'info', 'scheduled' => 'warning', 'failed' => 'danger', default => 'muted' } }} text-capitalize">{{ $campaign->status }}</span>
            </div>
            @if ($campaign->preheader)
                <p class="dash-subtitle mb-2" style="max-width:560px;">{{ $campaign->preheader }}</p>
            @endif
            <div class="d-flex flex-wrap gap-3 mt-1">
                <span class="meta-item"><i class="bx bx-calendar"></i> {{ $campaign->sent_at ? 'Sent ' . $campaign->sent_at->format('M j, Y') : ($campaign->scheduled_at ? 'Scheduled for ' . $campaign->scheduled_at->format('M j, Y') : 'Created ' . $campaign->created_at->format('M j, Y')) }}</span>
                <span class="meta-item"><i class="bx bx-envelope"></i> {{ $campaignTypeLabel }}</span>
                <span class="meta-item"><i class="bx bx-group"></i> {{ number_format($campaign->total_recipients) }} Recipients</span>
                @if ($campaign->senderIdentity)
                    <span class="meta-item"><i class="bx bx-user"></i> {{ $campaign->senderIdentity->from_name }} &lt;{{ $campaign->senderIdentity->from_email }}&gt;</span>
                @endif
            </div>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <a href="{{ route('admin.email.campaigns.export', $campaign) }}" class="dash-btn dash-btn-ghost"><i class="bx bx-download"></i> Export Report</a>
            <form action="{{ route('admin.email.campaigns.duplicate', $campaign) }}" method="POST">
                @csrf
                <button type="submit" class="dash-btn dash-btn-ghost"><i class="bx bx-copy"></i> Duplicate</button>
            </form>
            @if ($campaign->template)
                <a href="{{ route('admin.email.templates.edit', $campaign->template) }}" class="dash-btn dash-btn-ghost"><i class="bx bx-file"></i> View Template</a>
            @endif
            @if ($campaign->isEditable())
                <a href="{{ route('admin.email.campaigns.edit', $campaign) }}" class="dash-btn dash-btn-ghost">Edit</a>
                <form action="{{ route('admin.email.campaigns.send', $campaign) }}" method="POST" onsubmit="return confirm('Send this campaign now?');">
                    @csrf
                    <button type="submit" class="dash-btn dash-btn-primary">Send Now</button>
                </form>
            @else
                <a href="{{ route('admin.email.campaigns.create') }}" class="dash-btn dash-btn-primary"><i class="bx bx-paper-plane"></i> Send Another Campaign</a>
            @endif
        </div>
    </div>

    @if ($campaign->error_message)
        <div class="alert alert-danger">{{ $campaign->error_message }}</div>
    @endif
    @if (session('success'))
        <div class="alert alert-success d-flex align-items-center gap-2"><i class="bx bx-check-circle fs-5"></i> {{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger d-flex align-items-center gap-2"><i class="bx bx-error-circle fs-5"></i> {{ session('error') }}</div>
    @endif

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabOverview" type="button" role="tab">Overview</button></li>
        <li class="nav-item"><button id="eventsTabBtn" class="nav-link" data-bs-toggle="tab" data-bs-target="#tabEvents" type="button" role="tab">Events</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabStatistics" type="button" role="tab">Statistics</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabRecipients" type="button" role="tab">Recipients</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabSettings" type="button" role="tab">Settings</button></li>
    </ul>

    <div class="tab-content">

        {{-- ============================= OVERVIEW ============================= --}}
        <div class="tab-pane fade show active" id="tabOverview" role="tabpanel">

            <div class="row g-3 mb-3">
                @foreach ($rates as $r)
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="dash-card h-100">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="stat-icon-circle" style="background:{{ $r['color'] }}1a;color:{{ $r['color'] }};"><i class="bx {{ $r['icon'] }}"></i></div>
                                <span class="dash-badge" style="background:{{ $r['color'] }}1a;color:{{ $r['color'] }};">{{ $r['rate'] }}%</span>
                            </div>
                            <div class="dash-stat-label">{{ $r['label'] }}</div>
                            <div class="dash-stat-value">{{ number_format($r['value']) }}</div>
                            <div class="progress mt-2" style="height:5px;background:{{ $r['color'] }}1a;">
                                <div class="progress-bar" style="width:{{ min($r['rate'], 100) }}%;background:{{ $r['color'] }};"></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row g-3 mb-3">
                <div class="col-lg-7">
                    <div class="dash-card h-100">
                        <div class="dash-card-header">
                            <h6 class="mb-0">Delivery & Engagement Overview</h6>
                        </div>
                        @if (count($chartLabels) > 0)
                            <div id="engagementChart"></div>
                        @else
                            <div class="preview-empty">This chart fills in once the campaign has been sent and events start arriving.</div>
                        @endif
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="dash-card h-100">
                        <div class="dash-card-header">
                            <h6 class="mb-0">Campaign Performance</h6>
                        </div>
                        @if ($campaign->sent_count > 0)
                            <div class="text-center">
                                <div id="performanceDonut"></div>
                            </div>
                            <hr style="border-color:var(--dash-border);">
                            @foreach ($rates as $r)
                                @continue($r['label'] === 'Sent')
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="dash-subtitle" style="font-size:.8125rem;"><span class="mini-stat-dot" style="background:{{ $r['color'] }};"></span> {{ $r['label'] }}</span>
                                    <span style="font-weight:600;color:var(--dash-heading);font-size:.8125rem;">{{ number_format($r['value']) }} ({{ $r['rate'] }}%)</span>
                                </div>
                            @endforeach
                        @else
                            <div class="preview-empty">No performance data yet - this campaign hasn't been sent.</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-lg-4">
                    <div class="dash-card h-100">
                        <div class="dash-card-header"><h6 class="mb-0">Campaign Details</h6></div>
                        <div class="review-row"><span>Campaign Name</span><span>{{ $campaign->name }}</span></div>
                        <div class="review-row"><span>Subject</span><span>{{ $campaign->subject }}</span></div>
                        @if ($campaign->preheader)
                            <div class="review-row"><span>Preheader</span><span>{{ $campaign->preheader }}</span></div>
                        @endif
                        <div class="review-row">
                            <span>Template</span>
                            <span>
                                @if ($campaign->template)
                                    <a href="{{ route('admin.email.templates.edit', $campaign->template) }}" class="dash-link">{{ $campaign->template->name }}</a>
                                @else
                                    &mdash;
                                @endif
                            </span>
                        </div>
                        <div class="review-row"><span>Sender</span><span>{{ $campaign->senderIdentity ? $campaign->senderIdentity->from_name . ' <' . $campaign->senderIdentity->from_email . '>' : '—' }}</span></div>
                        <div class="review-row"><span>Type</span><span>{{ $campaignTypeLabel }}</span></div>
                        <div class="review-row">
                            <span>Schedule</span>
                            <span>
                                @if ($campaign->sent_at) Sent on {{ $campaign->sent_at->format('M j, Y \a\t H:i') }}
                                @elseif ($campaign->scheduled_at) {{ $campaign->scheduled_at->format('M j, Y \a\t H:i') }}
                                @else Not scheduled
                                @endif
                            </span>
                        </div>
                        <div class="review-row"><span>Status</span><span class="text-capitalize">{{ $campaign->status }}</span></div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="dash-card h-100">
                        <div class="dash-card-header">
                            <h6 class="mb-0">Recent Events</h6>
                            @if ($recentEvents->isNotEmpty())
                                <a href="javascript:;" class="dash-link" onclick="document.getElementById('eventsTabBtn').click();">View All</a>
                            @endif
                        </div>
                        @forelse ($recentEvents as $event)
                            <div class="activity-item">
                                <div class="activity-icon" style="background:{{ in_array($event->event_type, ['bounce','blocked','spamreport']) ? '#e11d48' : (in_array($event->event_type, ['unsubscribe','group_unsubscribe']) ? '#8b8d9c' : '#16a34a') }}1a;color:{{ in_array($event->event_type, ['bounce','blocked','spamreport']) ? '#e11d48' : (in_array($event->event_type, ['unsubscribe','group_unsubscribe']) ? '#8b8d9c' : '#16a34a') }};">
                                    <i class="bx {{ match($event->event_type) { 'delivered' => 'bx-check', 'open' => 'bx-envelope-open', 'click' => 'bx-mouse', 'bounce' => 'bx-error', 'blocked' => 'bx-block', 'spamreport' => 'bx-flag', default => 'bx-user-x' } }}"></i>
                                </div>
                                <div class="activity-body">
                                    <p class="text-capitalize">{{ $event->event_type }}</p>
                                    <small>{{ $event->recipient_email }}</small>
                                </div>
                                <div class="activity-when">{{ $event->event_at?->format('M j, H:i') ?? '—' }}</div>
                            </div>
                        @empty
                            <div class="dash-empty-row">No events recorded yet.</div>
                        @endforelse
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="dash-card h-100">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">Email Preview</h6>
                            <div class="device-toggle">
                                <button type="button" class="device-btn active" data-width="100%" title="Desktop"><i class="bx bx-desktop"></i></button>
                                <button type="button" class="device-btn" data-width="375px" title="Mobile"><i class="bx bx-mobile"></i></button>
                            </div>
                        </div>
                        <div class="browser-chrome">
                            <div class="browser-dots"><span></span><span></span><span></span></div>
                        </div>
                        <iframe id="campaignPreviewFrame" class="template-preview-frame" style="height:320px;" srcdoc="{{ $campaign->body }}"></iframe>
                    </div>
                </div>
            </div>

        </div>

        {{-- ============================= EVENTS ============================= --}}
        <div class="tab-pane fade" id="tabEvents" role="tabpanel">
            <div class="dash-card">
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
        </div>

        {{-- ============================= STATISTICS ============================= --}}
        <div class="tab-pane fade" id="tabStatistics" role="tabpanel">
            <div class="dash-card">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="dash-card" style="box-shadow:none;background:var(--dash-card-hover);">
                            <div class="dash-stat-label">Delivery Rate</div>
                            <div class="dash-stat-value">{{ $campaign->deliveryRate() }}%</div>
                            <div class="dash-stat-foot">{{ number_format($campaign->delivered_count) }} of {{ number_format($campaign->sent_count) }} sent</div>
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
                            <div class="dash-stat-value">{{ $campaign->bounceRate() }}%</div>
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
                            <div class="dash-stat-value">{{ $campaign->unsubscribeRate() }}%</div>
                            <div class="dash-stat-foot">{{ number_format($campaign->unsubscribed_count) }} unsubscribes</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="dash-card" style="box-shadow:none;background:var(--dash-card-hover);">
                            <div class="dash-stat-label">Block Rate</div>
                            <div class="dash-stat-value">{{ $campaign->sent_count > 0 ? round(($campaign->blockedCount() / $campaign->sent_count) * 100, 2) : 0 }}%</div>
                            <div class="dash-stat-foot">{{ number_format($campaign->blockedCount()) }} blocked</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================= RECIPIENTS ============================= --}}
        <div class="tab-pane fade" id="tabRecipients" role="tabpanel">
            <div class="dash-card">
                @if ($recipients)
                    <div class="table-responsive">
                        <table class="dash-table">
                            <thead>
                                <tr>
                                    <th>Email</th>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th>Subscribed At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recipients as $subscriber)
                                    <tr>
                                        <td>{{ $subscriber->email }}</td>
                                        <td>{{ $subscriber->name ?? '—' }}</td>
                                        <td><span class="dash-badge dash-badge-success text-capitalize">{{ $subscriber->status }}</span></td>
                                        <td>{{ $subscriber->subscribed_at?->format('M j, Y') ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="dash-empty-row">No recipients on this list.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($recipients->hasPages())
                        <div class="mt-3">{{ $recipients->links() }}</div>
                    @endif
                @elseif ($audience)
                    <div class="dash-empty-row">
                        This campaign targets the SendGrid segment "{{ $audience->name }}". Segment membership is managed directly in SendGrid, so individual recipients aren't listed here — {{ number_format($campaign->total_recipients) }} recipients were included when this campaign was sent.
                    </div>
                @else
                    <div class="dash-empty-row">No audience selected for this campaign.</div>
                @endif
            </div>
        </div>

        {{-- ============================= SETTINGS ============================= --}}
        <div class="tab-pane fade" id="tabSettings" role="tabpanel">
            <div class="dash-card" style="max-width:640px;">
                <div class="review-row"><span>Campaign Name</span><span>{{ $campaign->name }}</span></div>
                <div class="review-row"><span>Subject</span><span>{{ $campaign->subject }}</span></div>
                <div class="review-row"><span>From Name</span><span>{{ $campaign->from_name }}</span></div>
                <div class="review-row"><span>From Email</span><span>{{ $campaign->from_email }}</span></div>
                <div class="review-row"><span>Audience</span><span>{{ $audience?->name ?? '—' }} <span class="text-capitalize">({{ $campaign->audience_type }})</span></span></div>
                <div class="review-row"><span>Campaign Type</span><span>{{ $campaignTypeLabel }}</span></div>
                <div class="review-row"><span>Unsubscribe Group ID</span><span>{{ $campaign->suppression_group_id ?? '—' }}</span></div>
                <div class="review-row"><span>Created At</span><span>{{ $campaign->created_at->format('M j, Y H:i') }}</span></div>
                <div class="review-row"><span>Editable</span><span>{{ $campaign->isEditable() ? 'Yes' : 'No - already sent' }}</span></div>
                @if ($campaign->isEditable())
                    <div class="mt-3"><a href="{{ route('admin.email.campaigns.edit', $campaign) }}" class="dash-btn dash-btn-primary">Edit Campaign</a></div>
                @endif
            </div>
        </div>

    </div>

</div>
@endsection

@push('scripts')
<script>
    window.addEventListener('load', function () {
        if (typeof ApexCharts === 'undefined') return;

        const engagementEl = document.querySelector('#engagementChart');
        if (engagementEl) {
            new ApexCharts(engagementEl, {
                chart: { type: 'line', height: 280, toolbar: { show: false }, background: 'transparent' },
                series: [
                    { name: 'Delivered', data: @json($chartDelivered) },
                    { name: 'Opened', data: @json($chartOpened) },
                    { name: 'Clicked', data: @json($chartClicked) },
                ],
                xaxis: { categories: @json($chartLabels), labels: { style: { colors: '#8b8d9c' } } },
                yaxis: { labels: { style: { colors: '#8b8d9c' } } },
                stroke: { curve: 'smooth', width: 2.5 },
                colors: ['#0ea5e9', '#7c5cff', '#0891b2'],
                legend: { labels: { colors: '#8b8d9c' } },
                grid: { borderColor: 'rgba(20,20,50,.08)' },
                tooltip: { theme: 'light' },
            }).render();
        }

        const donutEl = document.querySelector('#performanceDonut');
        if (donutEl) {
            new ApexCharts(donutEl, {
                chart: { type: 'donut', height: 210 },
                series: [{{ $campaign->delivered_count }}, {{ $campaign->opened_count }}, {{ $campaign->clicked_count }}, {{ $campaign->bounced_count }}, {{ $campaign->unsubscribed_count }}],
                labels: ['Delivered', 'Opened', 'Clicked', 'Bounced', 'Unsubscribed'],
                colors: ['#16a34a', '#7c5cff', '#0891b2', '#e11d48', '#d97706'],
                legend: { show: false },
                dataLabels: { enabled: false },
                plotOptions: { pie: { donut: { size: '75%', labels: { show: true, value: { color: '#1e1e2d', fontWeight: 700 }, total: { show: true, label: 'Delivery Rate', color: '#8b8d9c', formatter: () => '{{ $campaign->deliveryRate() }}%' } } } } },
                tooltip: { theme: 'light' },
            }).render();
        }

        const previewFrame = document.querySelector('#campaignPreviewFrame');
        document.querySelectorAll('.device-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.device-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                previewFrame.style.width = btn.dataset.width;
                previewFrame.style.margin = btn.dataset.width === '100%' ? '0' : '0 auto';
            });
        });
    });
</script>
@endpush
