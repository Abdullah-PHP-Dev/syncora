@extends('layouts.app')

@section('title', 'AI Copilot Analytics')

@php
    $handled = $autoReplied + $suggested;
    $totalDelta = $previousTotal > 0 ? round((($total - $previousTotal) / $previousTotal) * 100) : null;
    $rateDelta = $previousRate !== null ? $resolutionRate - $previousRate : null;
    $pct = fn ($n) => $total > 0 ? round($n / $total * 100) : 0;
    $maxGap = max(1, (int) $topGaps->max('occurrence_count'));
    $maxFaq = max(1, (int) $topFaqs->max('uses'));
    $confTone = $averageConfidence >= 75 ? 'ok' : ($averageConfidence >= 50 ? 'warn' : 'bad');
@endphp

@push('styles')
<style>
    .cpa { --ln: #e7e9f0; --ln-soft: #f1f3f7; --ink: #161b2b; --ink2: #545d70; --muted: #8a92a3; --brand: #6d4aff; --brand-2: #8f6bff; --brand-soft: #f2eeff; --ok: #079455; --ok-soft: #ecfdf3; --warn: #b54708; --warn-soft: #fffaeb; --bad: #d92d20; --bad-soft: #fef3f2; color: var(--ink2); }

    .cpa-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; }
    .cpa-head-title { display: flex; gap: 14px; align-items: flex-start; }
    .cpa-head-icon { width: 48px; height: 48px; border-radius: 14px; display: grid; place-items: center; font-size: 24px; color: #fff; background: linear-gradient(135deg, var(--brand), var(--brand-2)); box-shadow: 0 8px 18px rgba(109, 74, 255, .28); flex-shrink: 0; }
    .cpa-eyebrow { font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--brand); margin-bottom: 2px; }
    .cpa-head h4 { color: var(--ink); font-weight: 700; font-size: 1.35rem; letter-spacing: -.01em; margin: 0 0 4px; }
    .cpa-head p { margin: 0; font-size: .87rem; }
    .cpa-period { display: inline-flex; gap: 2px; padding: 3px; background: var(--ln-soft); border-radius: 11px; }
    .cpa-period a { height: 34px; padding: 0 14px; border-radius: 8px; display: inline-flex; align-items: center; font-size: .8rem; font-weight: 600; color: var(--ink2); text-decoration: none; transition: background .15s, color .15s; }
    .cpa-period a:hover { color: var(--ink); }
    .cpa-period a.is-active { background: #fff; color: var(--ink); box-shadow: 0 1px 3px rgba(16, 24, 40, .1); }

    .cpa-card { background: #fff; border: 1px solid var(--ln); border-radius: 16px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }
    .cpa-card-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 18px 20px 0; }
    .cpa-card-head h6 { color: var(--ink); font-weight: 700; font-size: .95rem; margin: 0; }
    .cpa-card-head p { margin: 2px 0 0; font-size: .78rem; color: var(--muted); }
    .cpa-card-head a { font-size: .8rem; font-weight: 600; color: var(--brand); text-decoration: none; white-space: nowrap; }
    .cpa-card-body { padding: 16px 20px 20px; }

    /* KPIs */
    .cpa-kpis { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
    .cpa-kpi { padding: 16px; transition: box-shadow .2s, transform .2s; }
    .cpa-kpi:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(16, 24, 40, .08); }
    .cpa-kpi-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; margin-bottom: 10px; }
    .cpa-kpi-label { font-size: .76rem; font-weight: 600; color: var(--muted); }
    .cpa-kpi-icon { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; font-size: 17px; flex-shrink: 0; }
    .cpa-kpi-value { font-size: 1.6rem; font-weight: 700; color: var(--ink); letter-spacing: -.02em; line-height: 1.1; }
    .cpa-kpi-foot { margin-top: 6px; font-size: .74rem; color: var(--muted); display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
    .cpa-delta { display: inline-flex; align-items: center; font-weight: 700; }
    .cpa-delta.is-up { color: var(--ok); }
    .cpa-delta.is-down { color: var(--bad); }
    .t-brand { background: var(--brand-soft); color: var(--brand); }
    .t-ok { background: var(--ok-soft); color: var(--ok); }
    .t-warn { background: var(--warn-soft); color: var(--warn); }
    .t-bad { background: var(--bad-soft); color: var(--bad); }
    .t-info { background: #eff8ff; color: #1570ef; }
    .cpa-meter { height: 6px; border-radius: 999px; background: var(--ln-soft); overflow: hidden; margin-top: 8px; }
    .cpa-meter span { display: block; height: 100%; border-radius: 999px; }

    .cpa-grid { display: grid; grid-template-columns: minmax(0, 1.7fr) minmax(0, 1fr); gap: 16px; margin-bottom: 16px; }
    .cpa-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

    /* Charts */
    .cpa-chart { position: relative; height: 260px; }
    .cpa-donut { position: relative; height: 200px; }
    .cpa-donut-center { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none; }
    .cpa-donut-center strong { font-size: 1.6rem; color: var(--ink); line-height: 1; }
    .cpa-donut-center span { font-size: .72rem; color: var(--muted); margin-top: 4px; }
    .cpa-mix { list-style: none; margin: 16px 0 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
    .cpa-mix li { display: flex; align-items: center; gap: 10px; font-size: .82rem; }
    .cpa-mix i { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
    .cpa-mix span { flex: 1; }
    .cpa-mix strong { color: var(--ink); }
    .cpa-mix em { font-style: normal; color: var(--muted); width: 42px; text-align: end; font-variant-numeric: tabular-nums; }

    /* Ranked lists */
    .cpa-rank { list-style: none; margin: 0; padding: 0; }
    .cpa-rank li { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--ln-soft); }
    .cpa-rank li:last-child { border-bottom: none; }
    .cpa-rank-num { width: 26px; height: 26px; border-radius: 8px; display: grid; place-items: center; font-size: .72rem; font-weight: 700; flex-shrink: 0; background: var(--ln-soft); color: var(--ink2); }
    .cpa-rank-num.t-ok { background: var(--ok-soft); color: var(--ok); }
    .cpa-rank-num.t-warn { background: var(--warn-soft); color: var(--warn); }
    .cpa-rank-main { flex: 1; min-width: 0; }
    .cpa-rank-q { color: var(--ink); font-weight: 600; font-size: .84rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .cpa-rank-bar { height: 5px; border-radius: 999px; background: var(--ln-soft); margin-top: 6px; overflow: hidden; }
    .cpa-rank-bar span { display: block; height: 100%; border-radius: 999px; }
    .cpa-rank-meta { text-align: end; flex-shrink: 0; }
    .cpa-rank-meta strong { display: block; color: var(--ink); font-size: .86rem; }
    .cpa-rank-meta span { font-size: .7rem; color: var(--muted); }

    .cpa-empty { text-align: center; padding: 28px 12px; }
    .cpa-empty-icon { width: 52px; height: 52px; margin: 0 auto 10px; border-radius: 15px; display: grid; place-items: center; font-size: 24px; }
    .cpa-empty strong { display: block; color: var(--ink); font-size: .88rem; margin-bottom: 3px; }
    .cpa-empty p { font-size: .8rem; color: var(--muted); margin: 0 0 12px; }
    .cpa-btn { display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 14px; border-radius: 9px; font-size: .8rem; font-weight: 600; text-decoration: none; border: 1px solid var(--ln); color: var(--ink); background: #fff; }
    .cpa-btn:hover { border-color: #cfd4de; background: #fafbfd; color: var(--ink); }

    @media (max-width: 1399.98px) { .cpa-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 991.98px) { .cpa-grid, .cpa-grid-2 { grid-template-columns: 1fr; } }
    @media (max-width: 575.98px) { .cpa-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
@endpush

@section('content')
<div class="cpa">

    <div class="cpa-head">
        <div class="cpa-head-title">
            <div class="cpa-head-icon"><i class="bx bx-line-chart"></i></div>
            <div>
                <div class="cpa-eyebrow">AI Copilot</div>
                <h4>Copilot analytics</h4>
                <p>How AI Copilot handled incoming customer messages over the last {{ $days }} days.</p>
            </div>
        </div>
        <nav class="cpa-period" aria-label="Period">
            @foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days'] as $value => $label)
                <a href="{{ request()->fullUrlWithQuery(['days' => $value]) }}" class="{{ $days === $value ? 'is-active' : '' }}">{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    {{-- KPIs --}}
    <div class="cpa-kpis">
        <div class="cpa-card cpa-kpi">
            <div class="cpa-kpi-top"><span class="cpa-kpi-label">Messages scored</span><span class="cpa-kpi-icon t-brand"><i class="bx bx-message-square-dots"></i></span></div>
            <div class="cpa-kpi-value">{{ number_format($total) }}</div>
            <div class="cpa-kpi-foot">
                @if ($totalDelta !== null)
                    <span class="cpa-delta {{ $totalDelta >= 0 ? 'is-up' : 'is-down' }}"><i class="bx {{ $totalDelta >= 0 ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt' }}"></i>{{ abs($totalDelta) }}%</span> vs previous {{ $days }}d
                @else
                    No earlier data to compare
                @endif
            </div>
        </div>
        <div class="cpa-card cpa-kpi">
            <div class="cpa-kpi-top"><span class="cpa-kpi-label">Auto-resolved</span><span class="cpa-kpi-icon t-ok"><i class="bx bx-check-double"></i></span></div>
            <div class="cpa-kpi-value">{{ number_format($autoReplied) }}</div>
            <div class="cpa-kpi-foot">{{ $pct($autoReplied) }}% sent automatically</div>
        </div>
        <div class="cpa-card cpa-kpi">
            <div class="cpa-kpi-top"><span class="cpa-kpi-label">Suggested</span><span class="cpa-kpi-icon t-info"><i class="bx bx-bulb"></i></span></div>
            <div class="cpa-kpi-value">{{ number_format($suggested) }}</div>
            <div class="cpa-kpi-foot">{{ $pct($suggested) }}% offered to an agent</div>
        </div>
        <div class="cpa-card cpa-kpi">
            <div class="cpa-kpi-top"><span class="cpa-kpi-label">No match</span><span class="cpa-kpi-icon t-bad"><i class="bx bx-help-circle"></i></span></div>
            <div class="cpa-kpi-value">{{ number_format($noMatch) }}</div>
            <div class="cpa-kpi-foot">{{ $pct($noMatch) }}% left for your team</div>
        </div>
        <div class="cpa-card cpa-kpi">
            <div class="cpa-kpi-top"><span class="cpa-kpi-label">Resolution rate</span><span class="cpa-kpi-icon t-ok"><i class="bx bx-target-lock"></i></span></div>
            <div class="cpa-kpi-value">{{ $resolutionRate }}%</div>
            <div class="cpa-kpi-foot">
                @if ($rateDelta !== null)
                    <span class="cpa-delta {{ $rateDelta >= 0 ? 'is-up' : 'is-down' }}"><i class="bx {{ $rateDelta >= 0 ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt' }}"></i>{{ abs($rateDelta) }} pts</span> vs previous {{ $days }}d
                @else
                    Share resolved with no agent
                @endif
            </div>
        </div>
        <div class="cpa-card cpa-kpi">
            <div class="cpa-kpi-top"><span class="cpa-kpi-label">Avg. confidence</span><span class="cpa-kpi-icon t-{{ $confTone === 'bad' ? 'bad' : ($confTone === 'warn' ? 'warn' : 'ok') }}"><i class="bx bx-tachometer"></i></span></div>
            <div class="cpa-kpi-value">{{ $averageConfidence }}%</div>
            <div class="cpa-meter"><span style="width: {{ $averageConfidence }}%; background: var(--{{ $confTone === 'bad' ? 'bad' : ($confTone === 'warn' ? 'warn' : 'ok') }});"></span></div>
        </div>
    </div>

    {{-- Trend + mix --}}
    <div class="cpa-grid">
        <div class="cpa-card">
            <div class="cpa-card-head">
                <div>
                    <h6>Daily activity</h6>
                    <p>Messages scored per day, by outcome</p>
                </div>
            </div>
            <div class="cpa-card-body">
                @if ($total > 0)
                    <div class="cpa-chart"><canvas id="cpaTrendChart"></canvas></div>
                @else
                    <div class="cpa-empty">
                        <div class="cpa-empty-icon t-brand"><i class="bx bx-line-chart"></i></div>
                        <strong>No Copilot activity in this period</strong>
                        <p>Once customers message you with AI Copilot enabled, daily results show up here.</p>
                        <a href="{{ route('admin.ai-copilot.settings.index') }}" class="cpa-btn"><i class="bx bx-cog"></i> Copilot settings</a>
                    </div>
                @endif
            </div>
        </div>

        <div class="cpa-card">
            <div class="cpa-card-head">
                <div>
                    <h6>Resolution mix</h6>
                    <p>What happened to each scored message</p>
                </div>
            </div>
            <div class="cpa-card-body">
                @if ($total > 0)
                    <div class="cpa-donut">
                        <canvas id="cpaMixChart"></canvas>
                        <div class="cpa-donut-center"><strong>{{ $pct($handled) }}%</strong><span>handled by AI</span></div>
                    </div>
                    <ul class="cpa-mix">
                        <li><i style="background:#17b26a"></i><span>Auto-resolved</span><strong>{{ number_format($autoReplied) }}</strong><em>{{ $pct($autoReplied) }}%</em></li>
                        <li><i style="background:#8f6bff"></i><span>Suggested</span><strong>{{ number_format($suggested) }}</strong><em>{{ $pct($suggested) }}%</em></li>
                        <li><i style="background:#d0d5dd"></i><span>No match</span><strong>{{ number_format($noMatch) }}</strong><em>{{ $pct($noMatch) }}%</em></li>
                    </ul>
                @else
                    <div class="cpa-empty">
                        <div class="cpa-empty-icon t-brand"><i class="bx bx-pie-chart-alt-2"></i></div>
                        <strong>Nothing to show yet</strong>
                        <p>The split between auto-resolved, suggested and unanswered appears here.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Rankings --}}
    <div class="cpa-grid-2">
        <div class="cpa-card">
            <div class="cpa-card-head">
                <div>
                    <h6>Most-used answers</h6>
                    <p>FAQs the Copilot matched most often</p>
                </div>
                <a href="{{ route('admin.knowledge-base.index') }}">Knowledge Base</a>
            </div>
            <div class="cpa-card-body">
                @if ($topFaqs->isEmpty())
                    <div class="cpa-empty">
                        <div class="cpa-empty-icon t-ok"><i class="bx bx-book-content"></i></div>
                        <strong>No answers used yet</strong>
                        <p>FAQs the Copilot sends or suggests will be ranked here.</p>
                    </div>
                @else
                    <ol class="cpa-rank">
                        @foreach ($topFaqs as $row)
                            <li>
                                <span class="cpa-rank-num t-ok">{{ $loop->iteration }}</span>
                                <div class="cpa-rank-main">
                                    <div class="cpa-rank-q" title="{{ $row->faq?->question }}">{{ $row->faq?->question ?? 'Deleted FAQ' }}</div>
                                    <div class="cpa-rank-bar"><span style="width: {{ round($row->uses / $maxFaq * 100) }}%; background: #17b26a;"></span></div>
                                </div>
                                <div class="cpa-rank-meta"><strong>{{ number_format($row->uses) }}</strong><span>{{ (int) $row->avg_confidence }}% conf.</span></div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </div>

        <div class="cpa-card">
            <div class="cpa-card-head">
                <div>
                    <h6>Top unanswered questions</h6>
                    <p>Open knowledge gaps - add an FAQ to cover them</p>
                </div>
                <a href="{{ route('admin.ai-copilot.knowledge-gaps.index') }}">Review all</a>
            </div>
            <div class="cpa-card-body">
                @if ($topGaps->isEmpty())
                    <div class="cpa-empty">
                        <div class="cpa-empty-icon t-ok"><i class="bx bx-check-shield"></i></div>
                        <strong>No open knowledge gaps</strong>
                        <p>Your FAQs cover every question customers have asked.</p>
                    </div>
                @else
                    <ol class="cpa-rank">
                        @foreach ($topGaps as $gap)
                            <li>
                                <span class="cpa-rank-num t-warn">{{ $loop->iteration }}</span>
                                <div class="cpa-rank-main">
                                    <div class="cpa-rank-q" title="{{ $gap->question }}">{{ $gap->question }}</div>
                                    <div class="cpa-rank-bar"><span style="width: {{ round($gap->occurrence_count / $maxGap * 100) }}%; background: #f79009;"></span></div>
                                </div>
                                <div class="cpa-rank-meta"><strong>{{ number_format($gap->occurrence_count) }}×</strong><span>asked</span></div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
@if ($total > 0)
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
    (function () {
        const trend = @json($trend);
        const draw = () => {
            const trendEl = document.getElementById('cpaTrendChart');
            const mixEl = document.getElementById('cpaMixChart');
            if (!trendEl || !window.Chart || trendEl.dataset.drawn) return;
            trendEl.dataset.drawn = '1';

            Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
            Chart.defaults.color = '#8a92a3';

            new Chart(trendEl, {
                type: 'bar',
                data: {
                    labels: trend.labels,
                    datasets: [
                        { label: 'Auto-resolved', data: trend.auto_replied, backgroundColor: '#17b26a', borderRadius: 4, stack: 's' },
                        { label: 'Suggested', data: trend.suggested, backgroundColor: '#8f6bff', borderRadius: 4, stack: 's' },
                        { label: 'No match', data: trend.no_match, backgroundColor: '#d0d5dd', borderRadius: 4, stack: 's' },
                    ],
                },
                options: {
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'rectRounded', boxWidth: 8, padding: 16 } } },
                    scales: {
                        x: { stacked: true, grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } },
                        y: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f3f7' }, border: { display: false } },
                    },
                },
            });

            new Chart(mixEl, {
                type: 'doughnut',
                data: {
                    labels: ['Auto-resolved', 'Suggested', 'No match'],
                    datasets: [{ data: [{{ $autoReplied }}, {{ $suggested }}, {{ $noMatch }}], backgroundColor: ['#17b26a', '#8f6bff', '#d0d5dd'], borderWidth: 0, hoverOffset: 4 }],
                },
                options: { maintainAspectRatio: false, cutout: '74%', plugins: { legend: { display: false } } },
            });
        };
        // The Vue #app root re-mounts after load - draw once the final DOM exists.
        window.addEventListener('load', () => setTimeout(draw, 0));
    })();
</script>
@endif
@endpush
