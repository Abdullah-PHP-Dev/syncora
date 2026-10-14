@extends('layouts.app')
@section('title', __('overview.performance'))
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/workspace-overview.css') }}?v={{ filemtime(public_path('assets/css/workspace-overview.css')) }}">
@endpush
@section('content')
@php
    $moduleIndex = collect($modules)->keyBy('key');
    $activityTotal = array_sum(array_map('array_sum', $series));
    $activityPrevious = collect($modules)->whereIn('key', array_keys($series))->sum('previous');
    $statusLabel = fn($status) => __('overview.status_'.$status) === 'overview.status_'.$status ? ucfirst($status) : __('overview.status_'.$status);
@endphp
<div class="workspace-overview ov-performance">
    <header class="ov-heading">
        <div>
            <span class="ov-eyebrow">SOCIALEAZ / {{ __('overview.workspace_label') }}</span>
            <h1>{{ __('overview.performance') }}</h1>
            <p>{{ __('overview.performance_copy') }}</p>
        </div>
        <a class="ov-old-dashboard" href="{{ route('dashboard') }}">{{ __('overview.old_dashboard') }}</a>
    </header>
    <div class="ov-update-bar">
        <span><i class="ov-live-dot" aria-hidden="true"></i>{{ __('overview.latest_data') }}</span>
        <div><time datetime="{{ $end->toIso8601String() }}">{{ $end->format('d/m/Y, H:i') }}</time><a href="{{ request()->fullUrl() }}" aria-label="{{ __('overview.refresh') }}"><i class="bx bx-refresh" aria-hidden="true"></i></a></div>
    </div>

    <div class="ov-performance-grid">
        <div class="ov-main-column">
            <section class="ov-panel ov-main-performance">
                <div class="ov-section-heading ov-chart-heading">
                    <div><span class="ov-eyebrow">{{ __('overview.big_picture') }}</span><h2>{{ __('overview.period_performance') }}</h2><p>{{ __('overview.activity_copy') }}</p></div>
                    <form class="ov-period-tabs" method="GET" aria-label="{{ __('overview.period') }}">
                        @foreach([90,30,7] as $period)
                            <button type="submit" name="days" value="{{ $period }}" class="{{ $days === $period ? 'is-active' : '' }}" @if($days === $period) aria-current="true" @endif>{{ __('overview.days', ['days' => $period]) }}</button>
                        @endforeach
                    </form>
                </div>
                <div class="ov-period-caption"><span>{{ $start->translatedFormat('d M Y') }} — {{ $end->translatedFormat('d M Y') }}</span><span>{{ __('overview.selected_modules') }}</span></div>
                <div class="ov-big-total">
                    <span>{{ __('overview.period_activity') }}</span>
                    <div><strong id="overview-selected-total">{{ number_format($activityTotal) }}</strong><span class="ov-total-unit">{{ __('overview.records') }}</span></div>
                    <small>{{ __('overview.previous', ['count' => number_format($activityPrevious)]) }}</small>
                </div>
                <div class="ov-series-filters" aria-label="{{ __('overview.selected_modules') }}">
                    @foreach($series as $key => $values)
                        <label class="ov-series-filter" style="--series-color:{{ ['posts'=>'#6855ed','ads'=>'#42b9a4','email'=>'#efa0bf','inbox'=>'#efa94c','comments'=>'#6997ef','ai'=>'#9a76dc'][$key] }}">
                            <input type="checkbox" data-overview-series="{{ $loop->index }}" checked>
                            <span>{{ __('overview.'.$key) }}</span>
                        </label>
                    @endforeach
                </div>
                <div id="overview-activity" class="ov-chart ov-chart-main" aria-label="{{ __('overview.activity') }}"></div>
                <div class="ov-stream-totals">
                    @foreach($series as $key => $values)
                        <div><span><i style="background:{{ ['posts'=>'#6855ed','ads'=>'#42b9a4','email'=>'#efa0bf','inbox'=>'#efa94c','comments'=>'#6997ef','ai'=>'#9a76dc'][$key] }}"></i>{{ __('overview.'.$key) }}</span><strong>{{ number_format(array_sum($values)) }}</strong><small>{{ __('overview.share', ['percent' => $activityTotal ? round(array_sum($values) / $activityTotal * 100) : 0]) }}</small></div>
                    @endforeach
                </div>
                <details class="ov-chart-data">
                    <summary>{{ __('overview.data_table') }}</summary>
                    <div class="ov-table-scroll"><table><thead><tr><th>{{ __('overview.date') }}</th>@foreach(array_keys($series) as $key)<th>{{ __('overview.'.$key) }}</th>@endforeach</tr></thead><tbody>@foreach($labels as $i => $label)<tr><td>{{ $label }}</td>@foreach($series as $values)<td>{{ $values[$i] }}</td>@endforeach</tr>@endforeach</tbody></table></div>
                </details>
            </section>
            <section class="ov-panel ov-recent-panel">
                <div class="ov-section-heading"><div><span class="ov-eyebrow">{{ __('overview.content_workspace') }}</span><h2>{{ __('overview.recent') }}</h2></div><a href="{{ route('admin.posts.dashboard') }}">{{ __('overview.view_all') }} <i class="bx bx-link-external"></i></a></div>
                @forelse($recent as $post)
                    <div class="ov-post"><span class="ov-icon"><i class="bx bx-file"></i></span><div><strong>{{ \Illuminate\Support\Str::limit($post->title ?: strip_tags($post->content ?: __('overview.untitled')), 65) }}</strong><small>{{ ucfirst($post->platform) }} · {{ $post->created_at->locale(app()->getLocale())->diffForHumans() }}</small></div><span class="ov-pill">{{ $statusLabel($post->status) }}</span></div>
                @empty
                    <div class="ov-empty"><i class="bx bx-calendar-edit"></i><p>{{ __('overview.empty_posts') }}</p></div>
                @endforelse
            </section>
        </div>

        <aside class="ov-summary-column" aria-label="{{ __('overview.modules') }}">
            <section class="ov-attention-section">
                <div class="ov-section-heading"><div><span class="ov-eyebrow">{{ __('overview.daily_focus') }}</span><h2>{{ __('overview.attention') }}</h2></div><span class="ov-date-badge"><i class="bx bx-calendar"></i>{{ $end->format('d/m/Y') }}</span></div>
                <div class="ov-focus-grid">
                    <div class="ov-focus-card"><h3>{{ __('overview.conversations_support') }}</h3><div class="ov-focus-counts"><div><span>{{ __('overview.unread') }}</span><strong>{{ $available['inbox'] ? number_format($unread) : '—' }}</strong></div><div><span>{{ __('overview.open_tickets') }}</span><strong>{{ $available['support'] ? number_format($openTickets) : '—' }}</strong></div></div><a href="{{ route('admin.chats.dashboard') }}">{{ __('overview.view_details') }} <i class="bx bx-link-external"></i></a></div>
                    <a class="ov-focus-card ov-focus-single" href="{{ route('admin.posts.dashboard', ['status'=>'failed']) }}"><h3>{{ __('overview.failed') }}</h3><strong>{{ $available['posts'] ? number_format($failed) : '—' }}</strong><small>{{ __('overview.needs_review') }}</small></a>
                </div>
                <p class="ov-focus-note">{{ __('overview.schedule_count', ['count' => $statuses['scheduled'] ?? 0]) }}</p>
            </section>
            <section class="ov-module-section">
                <div class="ov-section-heading"><div><span class="ov-eyebrow">{{ __('overview.workspace_health') }}</span><h2>{{ __('overview.modules') }}</h2></div><span class="ov-date-badge">{{ __('overview.days', ['days'=>$days]) }}</span></div>
                <p class="ov-comparison-note"><i class="ov-live-dot"></i>{{ __('overview.comparison_copy') }}</p>
                <div class="ov-modules">
                    @foreach($modules as $module)
                        <a class="ov-module {{ $loop->first ? 'ov-module-featured' : '' }}" @if($module['available']) href="{{ route($module['route']) }}" @else aria-disabled="true" @endif>
                            <div class="ov-module-top"><span class="ov-icon ov-icon-{{ $module['key'] }}"><i class="bx {{ $module['icon'] }}" aria-hidden="true"></i></span><i class="bx bx-link-external" aria-hidden="true"></i></div>
                            <h3>{{ __('overview.'.$module['key']) }}</h3>
                            <strong>{{ $module['available'] ? number_format($module['current']) : '—' }}</strong>
                            @if($module['available'])
                                @php $change = $module['current'] - $module['previous']; @endphp
                                <span class="ov-change {{ $change > 0 ? 'ov-change-up' : ($change < 0 ? 'ov-change-down' : '') }}"><i class="bx {{ $change > 0 ? 'bx-trending-up' : ($change < 0 ? 'bx-trending-down' : 'bx-minus') }}"></i>{{ $change === 0 ? '—' : ($change > 0 ? '+' : '').number_format($change) }}</span>
                                <div class="ov-module-footer"><span>{{ __('overview.previous_short') }}</span><span>{{ number_format($module['previous']) }}</span></div>
                                <div class="ov-module-alltime"><span>{{ __('overview.lifetime_short') }}</span><span>{{ number_format($module['total']) }}</span></div>
                            @else
                                <small class="ov-unavailable">{{ __('overview.unavailable') }}</small>
                            @endif
                        </a>
                    @endforeach
                </div>
            </section>
        </aside>
    </div>

    <div class="ov-bottom-grid">
        <section class="ov-panel"><span class="ov-eyebrow">{{ __('overview.content_workspace') }}</span><h2>{{ __('overview.publishing_health') }}</h2><p>{{ __('overview.lifetime') }}</p><div id="overview-status" class="ov-chart" aria-label="{{ __('overview.publishing_health') }}"></div><div class="ov-status-list">@forelse($statuses as $status => $total)<div><span>{{ $statusLabel($status) }}</span><strong>{{ number_format($total) }}</strong></div>@empty<p>{{ __('overview.empty_posts') }}</p>@endforelse</div></section>
        <section class="ov-panel"><span class="ov-eyebrow">{{ __('overview.audience_reach') }}</span><h2>{{ __('overview.email_results') }}</h2><p>{{ __('overview.email_copy') }}</p><div id="overview-email" class="ov-chart ov-chart-small"></div><div class="ov-email-totals">@foreach(['sent','delivered','opened','clicked'] as $metric)<div><span>{{ __('overview.'.$metric) }}</span><strong>{{ $available['email'] ? number_format($emailTotals->$metric) : '—' }}</strong></div>@endforeach</div></section>
        <section class="ov-panel"><span class="ov-eyebrow">{{ __('overview.your_network') }}</span><h2>{{ __('overview.channel_mix') }}</h2><p>{{ __('overview.valid_accounts') }}</p><div id="overview-platforms" class="ov-chart ov-chart-small"></div><div class="ov-status-list">@forelse($platforms as $platform => $count)<div><span>{{ ucfirst($platform) }}</span><strong>{{ $count }}</strong></div>@empty<p>{{ __('overview.empty_connections') }}</p>@endforelse</div></section>
        <section class="ov-panel"><span class="ov-eyebrow">{{ __('overview.content_calendar') }}</span><h2>{{ __('overview.up_next') }}</h2><p>{{ __('overview.schedule_copy') }}</p>@forelse($scheduled as $post)<div class="ov-post"><div><strong>{{ \Illuminate\Support\Str::limit($post->title ?: strip_tags($post->content ?: __('overview.untitled')), 50) }}</strong><small>{{ ucfirst($post->platform) }} · {{ \Illuminate\Support\Carbon::parse($post->schedule_at)->translatedFormat('d M, H:i') }}</small></div></div>@empty<div class="ov-empty"><i class="bx bx-time-five"></i><p>{{ __('overview.empty_schedule') }}</p></div>@endforelse</section>
    </div>
    <p class="ov-data-note">{{ __('overview.data_note') }}</p>
</div>
@endsection
@push('scripts')
<script type="application/json" id="overview-chart-data">{!! json_encode(['labels' => $labels, 'series' => collect($series)->map(fn($values, $key) => ['name' => __('overview.'.$key), 'data' => $values])->values(), 'statuses' => ['labels' => array_map($statusLabel, array_keys($statuses)), 'values' => array_values($statuses)], 'platforms' => ['labels' => array_map('ucfirst', array_keys($platforms)), 'values' => array_values($platforms)], 'email' => ['labels' => array_map(fn($s) => __('overview.'.$s), ['sent','delivered','opened','clicked']), 'values' => array_map(fn($s) => (int) $emailTotals->$s, ['sent','delivered','opened','clicked'])], 'empty' => __('overview.no_data'), 'locale' => app()->getLocale()], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script src="{{ asset('assets/js/workspace-overview.js') }}?v={{ filemtime(public_path('assets/js/workspace-overview.js')) }}"></script>
@endpush
