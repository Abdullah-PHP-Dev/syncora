@extends('layouts.app')

@section('title', __('email_marketing.dashboard.title'))

@push('styles')
@include('layouts.partials.dash-styles')
<style>
    .socialeaz-dash .email-quick-links a {
        display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-radius: 10px;
        border: 1px solid var(--dash-border); color: var(--dash-text); text-decoration: none;
        margin-bottom: 10px; transition: all .15s ease;
    }
    .socialeaz-dash .email-quick-links a:hover { border-color: var(--dash-primary); background: var(--dash-card-hover); color: var(--dash-primary); }
    .socialeaz-dash .range-pill { display: inline-flex; align-items: center; padding: .4rem .85rem; border-radius: .5rem; font-size: .78rem; font-weight: 600; text-decoration: none; border: 1px solid var(--dash-border); color: var(--dash-muted); }
    .socialeaz-dash .range-pill.is-active { background: var(--dash-primary); border-color: var(--dash-primary); color: #fff; }
    .socialeaz-dash .range-pill:not(.is-active):hover { color: var(--dash-primary); border-color: var(--dash-primary); }
    .socialeaz-dash .activity-item { display: flex; align-items: center; gap: .7rem; padding: .65rem 0; border-bottom: 1px solid var(--dash-border); }
    .socialeaz-dash .activity-item:last-child { border-bottom: none; }
    .socialeaz-dash .activity-icon { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1rem; }
    .socialeaz-dash .activity-body { flex: 1; min-width: 0; }
    .socialeaz-dash .activity-body p { margin: 0; font-size: .8125rem; color: var(--dash-text); font-weight: 600; }
    .socialeaz-dash .activity-body small { color: var(--dash-muted); font-size: .72rem; }
    .socialeaz-dash .activity-when { color: var(--dash-muted); font-size: .7rem; white-space: nowrap; }
    .socialeaz-dash .campaign-row-icon { width: 34px; height: 34px; border-radius: .6rem; background: var(--dash-card-hover); display: flex; align-items: center; justify-content: center; color: var(--dash-primary); flex-shrink: 0; }
</style>
@endpush

@php
    $trendFoot = function ($trend, $label = null) {
        $label ??= __('email_marketing.dashboard.vs_last_30');
        if ($trend === null) {
            return '<span class="dash-badge dash-badge-info">' . e(__('email_marketing.dashboard.new')) . '</span>';
        }
        $up = $trend >= 0;
        return '<span class="dash-trend ' . ($up ? 'dash-trend-up' : 'dash-trend-down') . '"><i class="bx ' . ($up ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt') . '"></i> ' . ($up ? '+' : '') . $trend . '%</span> <span style="color:var(--dash-muted);">' . e($label) . '</span>';
    };
    $activityIcon = fn ($type) => match ($type) {
        'subscribed'   => ['bx-user-plus', '#16a34a'],
        'sent'         => ['bx-paper-plane', '#7c5cff'],
        'unsubscribed' => ['bx-user-x', '#e11d48'],
        'opened'       => ['bx-envelope-open', '#0891b2'],
        default        => ['bx-bell', '#8b8d9c'],
    };
    $sourceColors = ['manual' => '#7c5cff', 'import' => '#0ea5e9', 'unknown' => '#8b8d9c'];
@endphp

@section('content')
<div class="socialeaz-dash">

    <div class="email-hero">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4><i class="bx bx-envelope-open"></i> {{ __('email_marketing.dashboard.title') }}</h4>
                <p>{{ __('email_marketing.dashboard.subtitle') }}</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="dropdown">
                    <button class="dash-btn dash-btn-ghost dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bx bx-calendar"></i> {{ $rangeStart->translatedFormat('M j, Y') }} - {{ $rangeEnd->translatedFormat('M j, Y') }}
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item @if($range === 7) active @endif" href="{{ request()->fullUrlWithQuery(['range' => 7]) }}">{{ __('email_marketing.ranges.last_7') }}</a>
                        <a class="dropdown-item @if($range === 30) active @endif" href="{{ request()->fullUrlWithQuery(['range' => 30]) }}">{{ __('email_marketing.ranges.last_30') }}</a>
                        <a class="dropdown-item @if($range === 90) active @endif" href="{{ request()->fullUrlWithQuery(['range' => 90]) }}">{{ __('email_marketing.ranges.last_90') }}</a>
                    </div>
                </div>
                <a href="{{ route('admin.email.campaigns.index') }}" class="dash-btn dash-btn-ghost"><i class="bx bx-bar-chart-alt-2"></i> {{ __('email_marketing.dashboard.view_reports') }}</a>
                <a href="{{ route('admin.email.campaigns.create') }}" class="dash-btn dash-btn-primary"><i class="bx bx-plus"></i> {{ __('email_marketing.dashboard.new_campaign') }}</a>
            </div>
        </div>
    </div>

    @if (!$isReady)
        <div class="alert alert-warning d-flex align-items-center gap-2">
            <i class="bx bx-error-circle fs-5"></i>
            <span>{{ __('email_marketing.dashboard.setup_incomplete') }} <a href="{{ route('admin.email.setup.index') }}">{{ __('email_marketing.dashboard.finish_setup') }}</a> {{ __('email_marketing.dashboard.setup_incomplete_after') }}</span>
        </div>
    @endif

    @if (session('success'))
        <div class="alert alert-success d-flex align-items-center gap-2"><i class="bx bx-check-circle fs-5"></i> {{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger d-flex align-items-center gap-2"><i class="bx bx-error-circle fs-5"></i> {{ session('error') }}</div>
    @endif
    <div class="dash-card row g-3 mb-3">
        <h6 class="mb-3" style="color:var(--dash-heading);font-weight:600;">{{ __('email_marketing.dashboard.quick_links') }}</h6>
        <div class="row">
            <div class="col-md-3 col-6">
                <div class="email-quick-links"><a href="{{ route('admin.email.setup.index') }}"><i class="bx bx-cog"></i> {{ __('email_marketing.dashboard.link_setup') }}</a></div>
            </div>
            <div class="col-md-3 col-6">
                <div class="email-quick-links"><a href="{{ route('admin.email.segments.index') }}"><i class="bx bx-filter-alt"></i> {{ __('email_marketing.dashboard.link_segments') }}</a></div>
            </div>
            <div class="col-md-3 col-6">
                <div class="email-quick-links"><a href="{{ route('admin.email.lists.index') }}"><i class="bx bx-list-ul"></i> {{ __('email_marketing.dashboard.link_lists') }}</a></div>
            </div>
            <div class="col-md-3 col-6">
                <div class="email-quick-links"><a href="{{ route('admin.email.templates.index') }}"><i class="bx bx-file"></i> {{ __('email_marketing.dashboard.link_templates') }}</a></div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-md-3 col-6">
            <x-metric-card :label="__('email_marketing.dashboard.total_contacts')" :value="number_format($totalSubscribers)">
                <x-slot:foot>{!! $trendFoot($subscribersTrend) !!}</x-slot:foot>
                <div id="contactsSparkline" class="dash-sparkline"></div>
            </x-metric-card>
        </div>
        <div class="col-md-3 col-6">
            <x-metric-card :label="__('email_marketing.dashboard.active_lists')" :value="number_format($totalLists)">
                <x-slot:foot>{!! $trendFoot($listsTrend) !!}</x-slot:foot>
                <div id="listsSparkline" class="dash-sparkline"></div>
            </x-metric-card>
        </div>
        <div class="col-md-3 col-6">
            <x-metric-card :label="__('email_marketing.dashboard.campaigns_sent')" :value="number_format($totalSent)">
                <x-slot:foot>{!! $trendFoot($campaignsTrend) !!}</x-slot:foot>
                <div id="campaignsSparkline" class="dash-sparkline"></div>
            </x-metric-card>
        </div>
        <div class="col-md-3 col-6">
            <x-metric-card :label="__('email_marketing.dashboard.avg_open_rate')" :value="$avgOpenRate . '%'">
                <x-slot:foot>{!! $trendFoot($openRateTrend) !!}</x-slot:foot>
                <div id="openRateSparkline" class="dash-sparkline"></div>
            </x-metric-card>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="dash-card h-100">
                <div class="dash-card-header">
                    <div>
                        <h6 class="mb-0">{{ __('email_marketing.dashboard.performance') }}</h6>
                        <span class="dash-subtitle" style="font-size:.75rem;">{{ __('email_marketing.dashboard.performance_subtitle', ['days' => $range]) }}</span>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ request()->fullUrlWithQuery(['range' => 7]) }}" class="range-pill @if($range === 7) is-active @endif">{{ __('email_marketing.ranges.last_7') }}</a>
                        <a href="{{ request()->fullUrlWithQuery(['range' => 30]) }}" class="range-pill @if($range === 30) is-active @endif">{{ __('email_marketing.ranges.last_30') }}</a>
                        <a href="{{ request()->fullUrlWithQuery(['range' => 90]) }}" class="range-pill @if($range === 90) is-active @endif">{{ __('email_marketing.ranges.last_90') }}</a>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <div class="mini-stat">
                            <div class="mini-stat-label"><span class="mini-stat-dot" style="background:#0ea5e9;"></span> {{ __('email_marketing.dashboard.delivered') }}</div>
                            <div class="mini-stat-value">{{ number_format($periodDelivered) }}</div>
                            <div class="mini-stat-foot">{{ __('email_marketing.dashboard.delivery_rate', ['rate' => $periodSent > 0 ? round(($periodDelivered / $periodSent) * 100, 1) : 0]) }}</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="mini-stat">
                            <div class="mini-stat-label"><span class="mini-stat-dot" style="background:#22c55e;"></span> {{ __('email_marketing.dashboard.opened') }}</div>
                            <div class="mini-stat-value">{{ number_format($periodOpened) }}</div>
                            <div class="mini-stat-foot">{{ __('email_marketing.dashboard.open_rate', ['rate' => $periodDelivered > 0 ? round(($periodOpened / $periodDelivered) * 100, 1) : 0]) }}</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="mini-stat">
                            <div class="mini-stat-label"><span class="mini-stat-dot" style="background:#7c5cff;"></span> {{ __('email_marketing.dashboard.clicked') }}</div>
                            <div class="mini-stat-value">{{ number_format($periodClicked) }}</div>
                            <div class="mini-stat-foot">{{ __('email_marketing.dashboard.click_rate', ['rate' => $periodDelivered > 0 ? round(($periodClicked / $periodDelivered) * 100, 1) : 0]) }}</div>
                        </div>
                    </div>
                </div>

                <div id="emailPerformanceChart"></div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="dash-card h-100">
                <div class="dash-card-header">
                    <h6>{{ __('email_marketing.dashboard.recent_campaigns') }}</h6>
                    <a href="{{ route('admin.email.campaigns.index') }}" class="dash-link">{{ __('email_marketing.common.view_all') }}</a>
                </div>
                <div class="table-responsive">
                    <table class="dash-table">
                        <tbody>
                            @forelse ($recentCampaigns as $campaign)
                                <tr>
                                    <td style="width:60%;">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="campaign-row-icon"><i class="bx bx-envelope"></i></div>
                                            <div class="min-w-0">
                                                <a href="{{ route('admin.email.campaigns.show', $campaign) }}" class="dash-link d-block text-truncate" style="color:var(--dash-heading);">{{ $campaign->name }}</a>
                                                <small class="dash-subtitle" style="font-size:.7rem;">{{ $campaign->sent_at?->translatedFormat('M j, Y H:i') ?? $campaign->created_at->translatedFormat('M j, Y') }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <div style="font-weight:600;color:var(--dash-heading);">{{ $campaign->openRate() }}%</div>
                                        <small class="dash-subtitle" style="font-size:.7rem;">{{ __('email_marketing.dashboard.open_rate_label') }}</small>
                                    </td>
                                    <td class="text-end">
                                        <span class="dash-badge dash-badge-{{ match($campaign->status) { 'sent' => 'success', 'sending' => 'info', 'scheduled' => 'warning', 'failed' => 'danger', default => 'muted' } }}">{{ emailLabel('statuses', $campaign->status) }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td class="dash-empty-row">{{ __('email_marketing.dashboard.no_campaigns') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="dash-card h-100">
                <div class="dash-card-header">
                    <div>
                        <h6 class="mb-0">{{ __('email_marketing.dashboard.audience_overview') }}</h6>
                        <span class="dash-subtitle" style="font-size:.75rem;">{{ __('email_marketing.dashboard.audience_subtitle') }}</span>
                    </div>
                    <a href="{{ route('admin.email.lists.index') }}" class="dash-link">{{ __('email_marketing.common.view_all') }}</a>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="mini-stat">
                            <div class="mini-stat-label"><i class="bx bx-group"></i> {{ __('email_marketing.dashboard.total_subscribers') }}</div>
                            <div class="mini-stat-value">{{ number_format($totalSubscribers) }}</div>
                            <div class="mini-stat-foot">{!! $trendFoot($subscribersTrend) !!}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mini-stat">
                            <div class="mini-stat-label"><i class="bx bx-user-plus"></i> {{ __('email_marketing.dashboard.new_contacts') }}</div>
                            <div class="mini-stat-value">{{ number_format($newContactsCount) }}</div>
                            <div class="mini-stat-foot">{!! $trendFoot($newContactsTrend) !!}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mini-stat">
                            <div class="mini-stat-label"><i class="bx bx-user-x"></i> {{ __('email_marketing.dashboard.unsub_bounces') }}</div>
                            <div class="mini-stat-value">{{ number_format($unsubBounceCount) }}</div>
                            <div class="mini-stat-foot">{!! $trendFoot($unsubBounceTrend) !!}</div>
                        </div>
                    </div>
                </div>

                <hr style="border-color:var(--dash-border);">

                <div class="row align-items-center">
                    <div class="col-md-5 text-center">
                        <h6 class="mb-2" style="font-weight:600;">{{ __('email_marketing.dashboard.source_breakdown') }}</h6>
                        <div id="sourceDonutChart"></div>
                    </div>
                    <div class="col-md-7">
                        @foreach ($sourceBreakdown as $source)
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="dash-subtitle" style="font-size:.8125rem;">
                                    <span class="mini-stat-dot" style="background:{{ $sourceColors[$source['key']] ?? '#8b8d9c' }};"></span>
                                    {{ $source['label'] }}
                                </span>
                                <span style="font-weight:600;color:var(--dash-heading);font-size:.8125rem;">{{ $source['percentage'] }}%</span>
                            </div>
                        @endforeach
                        @if ($sourceBreakdown->isEmpty())
                            <p class="dash-subtitle small mb-0">{{ __('email_marketing.dashboard.no_subscribers') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="dash-card h-100">
                <div class="dash-card-header">
                    <h6>{{ __('email_marketing.dashboard.recent_activity') }}</h6>
                </div>
                @forelse ($recentActivity as $item)
                    @php [$icon, $color] = $activityIcon($item['type']); @endphp
                    <div class="activity-item">
                        <div class="activity-icon" style="background:{{ $color }}1a;color:{{ $color }};"><i class="bx {{ $icon }}"></i></div>
                        <div class="activity-body">
                            <p>{{ $item['title'] }}</p>
                            <small dir="auto">{{ $item['subtitle'] }}</small>
                        </div>
                        <div class="activity-when">{{ $item['at']->diffForHumans() }}</div>
                    </div>
                @empty
                    <div class="dash-empty-row">{{ __('email_marketing.dashboard.no_activity') }}</div>
                @endforelse
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    window.addEventListener('load', function () {
        if (typeof ApexCharts === 'undefined') return;

        new ApexCharts(document.querySelector('#emailPerformanceChart'), {
            chart: { type: 'line', height: 280, toolbar: { show: false }, background: 'transparent' },
            series: [
                { name: @json(__('email_marketing.dashboard.delivered')), data: @json($chartDelivered) },
                { name: @json(__('email_marketing.dashboard.opened')), data: @json($chartOpened) },
                { name: @json(__('email_marketing.dashboard.clicked')), data: @json($chartClicked) },
            ],
            xaxis: { categories: @json($chartLabels), labels: { style: { colors: '#8b8d9c' } } },
            yaxis: { labels: { style: { colors: '#8b8d9c' } } },
            stroke: { curve: 'smooth', width: 2.5 },
            colors: ['#0ea5e9', '#22c55e', '#7c5cff'],
            legend: { labels: { colors: '#8b8d9c' } },
            grid: { borderColor: 'rgba(20,20,50,.08)' },
            tooltip: { theme: 'light' },
        }).render();

        function sparkline(el, data, color) {
            if (!el) return;
            new ApexCharts(el, {
                chart: { type: 'line', height: 32, sparkline: { enabled: true } },
                series: [{ data: data }],
                stroke: { curve: 'smooth', width: 2 },
                colors: [color],
                tooltip: { enabled: false },
            }).render();
        }
        sparkline(document.querySelector('#contactsSparkline'), @json($sparkNewContacts), '#0ea5e9');
        sparkline(document.querySelector('#listsSparkline'), @json($sparkActiveLists), '#22c55e');
        sparkline(document.querySelector('#campaignsSparkline'), @json($sparkCampaignsSent), '#7c5cff');
        sparkline(document.querySelector('#openRateSparkline'), @json($sparkOpenRate), '#d97706');

        const sourceLabels = @json($sourceBreakdown->pluck('label'));
        const sourceCounts = @json($sourceBreakdown->pluck('count'));
        const sourceColors = @json($sourceBreakdown->map(fn ($s) => $sourceColors[$s['key']] ?? '#8b8d9c'));
        const donutEl = document.querySelector('#sourceDonutChart');
        if (donutEl && sourceCounts.length > 0) {
            new ApexCharts(donutEl, {
                chart: { type: 'donut', height: 190 },
                series: sourceCounts,
                labels: sourceLabels,
                colors: sourceColors,
                legend: { show: false },
                dataLabels: { enabled: false },
                plotOptions: { pie: { donut: { size: '72%', labels: { show: true, total: { show: true, label: @json(__('email_marketing.dashboard.total')), color: '#8b8d9c' } } } } },
                tooltip: { theme: 'light' },
            }).render();
        }
    });
</script>
@endpush
