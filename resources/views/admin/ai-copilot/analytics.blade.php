@extends('layouts.app')

@section('title', 'AI Copilot Analytics')

@push('styles')
@include('layouts.partials.dash-styles')
<style>
    .socialeaz-dash .kpi-card { padding: 1.25rem; }
    .socialeaz-dash .kpi-value { font-size: 1.8rem; font-weight: 700; }
    .socialeaz-dash .kpi-label { color: var(--dash-muted, #6b7280); font-size: .85rem; }
</style>
@endpush

@section('content')
<div class="socialeaz-dash">

    <div class="dash-card mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="mb-1">AI Copilot Analytics</h4>
            <p class="text-muted mb-0">How your AI Copilot has performed on incoming customer messages over the last {{ $days }} days.</p>
        </div>
        <form method="get" class="d-flex align-items-center gap-2">
            <label class="form-label mb-0 small">Period</label>
            <select name="days" class="form-select form-select-sm" onchange="this.form.submit()" style="width:auto;">
                <option value="7" {{ $days === 7 ? 'selected' : '' }}>Last 7 days</option>
                <option value="30" {{ $days === 30 ? 'selected' : '' }}>Last 30 days</option>
                <option value="90" {{ $days === 90 ? 'selected' : '' }}>Last 90 days</option>
            </select>
        </form>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4 col-lg-2">
            <div class="dash-card kpi-card"><div class="kpi-value">{{ $total }}</div><div class="kpi-label">AI Questions</div></div>
        </div>
        <div class="col-md-4 col-lg-2">
            <div class="dash-card kpi-card"><div class="kpi-value">{{ $autoReplied }}</div><div class="kpi-label">Auto-Resolved</div></div>
        </div>
        <div class="col-md-4 col-lg-2">
            <div class="dash-card kpi-card"><div class="kpi-value">{{ $suggested }}</div><div class="kpi-label">Suggested</div></div>
        </div>
        <div class="col-md-4 col-lg-2">
            <div class="dash-card kpi-card"><div class="kpi-value">{{ $noMatch }}</div><div class="kpi-label">No Match</div></div>
        </div>
        <div class="col-md-4 col-lg-2">
            <div class="dash-card kpi-card"><div class="kpi-value">{{ $resolutionRate }}%</div><div class="kpi-label">Resolution Rate</div></div>
        </div>
        <div class="col-md-4 col-lg-2">
            <div class="dash-card kpi-card"><div class="kpi-value">{{ $averageConfidence }}%</div><div class="kpi-label">Avg. Confidence</div></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="dash-card">
                <h6 class="mb-3">Resolution mix</h6>
                @if ($total > 0)
                    <canvas id="resolutionChart" height="220"></canvas>
                @else
                    <p class="text-muted mb-0">No AI Copilot activity in this period yet.</p>
                @endif
            </div>
        </div>
        <div class="col-lg-7">
            <div class="dash-card">
                <h6 class="mb-3">Top unanswered questions</h6>
                @if ($topGaps->isEmpty())
                    <p class="text-muted mb-0">No open knowledge gaps right now.</p>
                @else
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Question</th><th>Seen</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($topGaps as $gap)
                                <tr>
                                    <td>{{ $gap->question }}</td>
                                    <td>{{ $gap->occurrence_count }}</td>
                                    <td class="text-end"><a href="{{ route('admin.ai-copilot.knowledge-gaps.index') }}" class="small">Review</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
@if ($total > 0)
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('resolutionChart'), {
    type: 'doughnut',
    data: {
        labels: ['Auto-Resolved', 'Suggested', 'No Match'],
        datasets: [{
            data: [{{ $autoReplied }}, {{ $suggested }}, {{ $noMatch }}],
            backgroundColor: ['#22c55e', '#f59e0b', '#ef4444'],
        }],
    },
    options: { plugins: { legend: { position: 'bottom' } } },
});
</script>
@endif
@endpush
