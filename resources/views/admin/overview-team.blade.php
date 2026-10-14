@extends('layouts.app')
@section('title', __('overview.performance'))
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/workspace-overview.css') }}?v={{ filemtime(public_path('assets/css/workspace-overview.css')) }}">
@endpush
@section('content')
<div class="workspace-overview ov-performance">
    <header class="ov-heading"><div><span class="ov-eyebrow">SOCIALEAZ / {{ __('overview.workspace_label') }}</span><h1>{{ __('overview.performance') }}</h1><p>{{ __('overview.team_subtitle') }}</p></div><a class="ov-old-dashboard" href="{{ route('dashboard') }}">{{ __('overview.old_dashboard') }}</a></header>
    <div class="ov-update-bar"><span><i class="ov-live-dot"></i>{{ __('overview.latest_data') }}</span><div><time>{{ now()->format('d/m/Y, H:i') }}</time><a href="{{ request()->fullUrl() }}" aria-label="{{ __('overview.refresh') }}"><i class="bx bx-refresh"></i></a></div></div>
    <div class="ov-performance-grid">
        <div class="ov-main-column">
            @isset($chart)
                <section class="ov-panel ov-main-performance"><div class="ov-section-heading"><div><span class="ov-eyebrow">{{ __('overview.big_picture') }}</span><h2>{{ __('overview.team_activity') }}</h2><p>{{ __('overview.lifetime') }}</p></div></div><div class="ov-big-total"><span>{{ __('overview.subscribers_total') }}</span><div><strong>{{ number_format($counts['subscribers'] ?? 0) }}</strong><span class="ov-total-unit">{{ __('overview.records') }}</span></div></div><div id="overview-team-chart" class="ov-chart ov-chart-main"></div><div class="ov-stream-totals">@foreach(['subscribers','ads','posts'] as $key)<div><span>{{ __('overview.'.['subscribers'=>'subscribers_total','ads'=>'team_ads','posts'=>'team_posts'][$key]) }}</span><strong>{{ number_format($counts[$key] ?? 0) }}</strong><small>{{ __('overview.lifetime') }}</small></div>@endforeach</div></section>
            @endisset
            <section class="ov-panel ov-recent-panel"><div class="ov-section-heading"><div><span class="ov-eyebrow">{{ __('overview.daily_focus') }}</span><h2>{{ __('overview.tickets') }}</h2></div><a href="{{ route('admin.tickets.index') }}">{{ __('overview.view_all') }}</a></div>@forelse($recentTickets as $ticket)<a class="ov-post" href="{{ route('admin.tickets.show', $ticket) }}"><span class="ov-icon"><i class="bx bx-support"></i></span><div><strong>{{ $ticket->subject }}</strong><small>{{ $ticket->ticket_number }} · {{ $ticket->updated_at->locale(app()->getLocale())->diffForHumans() }}</small></div><span class="ov-pill">{{ __('admin.tickets.status.'.$ticket->status) === 'admin.tickets.status.'.$ticket->status ? $ticket->status : __('admin.tickets.status.'.$ticket->status) }}</span></a>@empty<div class="ov-empty"><i class="bx bx-check-circle"></i><p>{{ __('overview.empty_tickets') }}</p></div>@endforelse</section>
        </div>
        <aside class="ov-summary-column">
            <section class="ov-attention-section"><div class="ov-section-heading"><div><span class="ov-eyebrow">{{ __('overview.daily_focus') }}</span><h2>{{ __('overview.attention') }}</h2></div></div><div class="ov-focus-grid"><a class="ov-focus-card" href="{{ route('admin.tickets.index') }}"><h3>{{ __('overview.open_tickets') }}</h3><div class="ov-focus-counts"><div><span>{{ __('overview.open_tickets') }}</span><strong>{{ number_format($ticketCounts['open']) }}</strong></div><div><span>{{ __('overview.mine') }}</span><strong>{{ number_format($ticketCounts['mine']) }}</strong></div></div></a><a class="ov-focus-card ov-focus-single" href="{{ route('admin.tickets.index') }}"><h3>{{ __('overview.urgent') }}</h3><strong>{{ number_format($ticketCounts['urgent']) }}</strong><small>{{ __('overview.needs_review') }}</small></a></div></section>
            <section class="ov-module-section"><div class="ov-section-heading"><div><span class="ov-eyebrow">{{ __('overview.workspace_health') }}</span><h2>{{ __('overview.modules') }}</h2></div></div><div class="ov-modules">
                @foreach(($counts ?? []) as $key => $total)<div class="ov-module {{ $loop->first ? 'ov-module-featured' : '' }}"><div class="ov-module-top"><span class="ov-icon"><i class="bx bx-bar-chart-alt-2"></i></span></div><h3>{{ __('overview.'.(['subscribers'=>'subscribers_total','ads'=>'team_ads','posts'=>'team_posts'][$key] ?? $key)) }}</h3><strong>{{ number_format($total) }}</strong><span class="ov-change">{{ __('overview.lifetime_short') }}</span></div>@endforeach
                @foreach($ticketCounts as $key => $total)<a href="{{ route('admin.tickets.index') }}" class="ov-module"><div class="ov-module-top"><span class="ov-icon ov-icon-support"><i class="bx bx-support"></i></span><i class="bx bx-link-external"></i></div><h3>{{ __('overview.'.($key === 'open' ? 'open_tickets' : $key)) }}</h3><strong>{{ number_format($total) }}</strong></a>@endforeach
            </div></section>
        </aside>
    </div>
    <p class="ov-data-note">{{ __('overview.data_note') }}</p>
</div>
@endsection
@push('scripts')
@isset($chart)
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof ApexCharts === 'undefined') return;
    const chart = @json($chart);
    new ApexCharts(document.getElementById('overview-team-chart'), {
        chart: { type: 'bar', height: 440, toolbar: { show: false }, foreColor: '#929db4', animations: { enabled: !matchMedia('(prefers-reduced-motion: reduce)').matches } },
        colors: ['#6855ed', '#42b9a4', '#efa0bf'],
        series: [{ name: @json(__('overview.subscribers_total')), data: chart.subscribers }, { name: @json(__('overview.team_ads')), data: chart.ads }, { name: @json(__('overview.team_posts')), data: chart.posts }],
        xaxis: { categories: chart.labels }, yaxis: { min: 0, labels: { formatter: value => Math.round(value).toLocaleString() } }, plotOptions: { bar: { borderRadius: 3, columnWidth: '65%' } }, dataLabels: { enabled: false }, grid: { borderColor: '#e9edf6', strokeDashArray: 4 }, legend: { position: 'top', fontSize: '11px' }, responsive: [{ breakpoint: 800, options: { chart: { height: 330 } } }],
    }).render();
});
</script>
@endisset
@endpush
