@extends('layouts.app')
@section('title', __('Social Eaz Team Dashboard'))
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-6">
    <div><h3 class="mb-1">{{ __('Social Eaz Team Dashboard') }}</h3><p class="text-muted mb-0">{{ __('Platform activity and customer operations') }}</p></div>
    <a class="btn btn-primary" href="{{ route('employees.create') }}"><i class="bx bx-user-plus me-2"></i>{{ __('Create employee') }}</a>
</div>
<div class="row g-4 mb-6">
    @foreach(['subscribers' => ['Total subscribers', 'bx-group'], 'active' => ['Active subscriptions', 'bx-check-circle'], 'ads' => ['Total ads', 'bx-megaphone'], 'posts' => ['Total posts', 'bx-file']] as $key => [$label, $icon])
        <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body">
            <div class="d-flex justify-content-between"><span class="text-muted">{{ __($label) }}</span><i class="bx {{ $icon }} text-primary"></i></div>
            <h2 class="my-3">{{ number_format($counts[$key]) }}</h2>
        </div></div></div>
    @endforeach
</div>
<div class="row g-4 mb-6">
    @foreach(['subscribers' => 'New subscribers by month', 'ads' => 'Ads created by month', 'posts' => 'Posts created by month'] as $key => $label)
        <div class="col-xl-4"><div class="card"><div class="card-header"><h5 class="mb-1">{{ __($label) }}</h5><small class="text-muted">{{ __('Last 12 months') }}</small></div>
            <div class="card-body"><div id="chart-{{ $key }}" aria-label="{{ __($label) }}"></div>
                <details><summary>{{ __('View monthly data') }}</summary><table class="table table-sm"><thead><tr><th>{{ __('Month') }}</th><th>{{ __('Total') }}</th></tr></thead><tbody>
                @foreach($chart['labels'] as $index => $month)<tr><td>{{ $month }}</td><td>{{ $chart[$key][$index] }}</td></tr>@endforeach
                </tbody></table></details>
            </div>
        </div></div>
    @endforeach
</div>
<p class="text-muted small">{{ __('Subscriber totals count sellers with a subscription. Monthly subscribers count their first subscription; ads and posts use their creation date.') }}</p>
@include('team.ticket-summary')
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const data = @json($chart);
    const labels = @json(['subscribers' => __('Subscribers'), 'ads' => __('Ads'), 'posts' => __('Posts')]);
    const colors = { subscribers: '#696cff', ads: '#03c3ec', posts: '#71dd37' };
    if (!window.ApexCharts) return;
    Object.keys(labels).forEach(key => new ApexCharts(document.querySelector('#chart-' + key), {
        chart: { type: 'bar', height: 260, toolbar: { show: false } },
        series: [{ name: labels[key], data: data[key] }], colors: [colors[key]],
        xaxis: { categories: data.labels }, yaxis: { min: 0, forceNiceScale: true, decimalsInFloat: 0 },
        dataLabels: { enabled: false }, plotOptions: { bar: { borderRadius: 4 } }
    }).render());
});
</script>
@endpush
