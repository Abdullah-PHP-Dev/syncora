@extends('layouts.app')
@section('title', __('overview.title'))
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/workspace-overview.css') }}?v={{ filemtime(public_path('assets/css/workspace-overview.css')) }}">
@endpush
@section('content')
<div class="workspace-overview">
    <div class="ov-heading"><div><span class="ov-eyebrow">SOCIALEAZ / {{ __('overview.title') }}</span><h1>{{ __('overview.greeting', ['name' => auth()->user()->name]) }}</h1><p>{{ __('overview.team_subtitle') }}</p></div></div>
    <div class="ov-modules">
        @foreach(($counts ?? []) as $key => $total)<div class="ov-module"><span class="ov-icon"><i class="bx bx-bar-chart-alt-2"></i></span><h3 class="mt-3">{{ __('overview.'.(['subscribers'=>'subscribers_total','ads'=>'team_ads','posts'=>'team_posts'][$key] ?? $key)) }}</h3><strong>{{ number_format($total) }}</strong><p>{{ __('overview.lifetime') }}</p></div>@endforeach
        @foreach($ticketCounts as $key => $total)<a href="{{ route('admin.tickets.index') }}" class="ov-module"><span class="ov-icon"><i class="bx bx-support"></i></span><h3 class="mt-3">{{ __('overview.'.($key === 'open' ? 'open_tickets' : $key)) }}</h3><strong>{{ number_format($total) }}</strong></a>@endforeach
    </div>
    @isset($chart)<section class="ov-panel mb-4"><h2>{{ __('overview.team_activity') }}</h2><div id="overview-team-chart" class="ov-chart"></div></section>@endisset
    <section class="ov-panel"><div class="ov-section-heading"><h2>{{ __('overview.tickets') }}</h2><a href="{{ route('admin.tickets.index') }}">{{ __('overview.view_all') }}</a></div>@forelse($recentTickets as $ticket)<a class="ov-post" href="{{ route('admin.tickets.show', $ticket) }}"><span class="ov-icon"><i class="bx bx-support"></i></span><div><strong>{{ $ticket->subject }}</strong><small>{{ $ticket->ticket_number }} · {{ $ticket->updated_at->diffForHumans() }}</small></div><span class="ov-pill">{{ __('admin.tickets.status.'.$ticket->status) === 'admin.tickets.status.'.$ticket->status ? $ticket->status : __('admin.tickets.status.'.$ticket->status) }}</span></a>@empty<p>{{ __('overview.empty_tickets') }}</p>@endforelse</section>
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
        chart: { type: 'bar', height: 320, toolbar: { show: false } },
        colors: ['#064bff', '#720aff', '#00a67d'],
        series: [{ name: @json(__('overview.subscribers_total')), data: chart.subscribers }, { name: @json(__('overview.team_ads')), data: chart.ads }, { name: @json(__('overview.team_posts')), data: chart.posts }],
        xaxis: { categories: chart.labels }, plotOptions: { bar: { borderRadius: 4 } }, dataLabels: { enabled: false }, grid: { borderColor: '#edf0fa' },
    }).render();
});
</script>
@endisset
@endpush
