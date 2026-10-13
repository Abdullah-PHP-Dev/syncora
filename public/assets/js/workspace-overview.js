(() => {
    const element = document.getElementById('overview-chart-data');
    if (!element) return;
    const data = JSON.parse(element.textContent);
    const colors = ['#064bff', '#720aff', '#ed2e84', '#00a67d', '#ffab00', '#03a7df'];
    const render = (id, options, empty = false) => {
        const container = document.getElementById(id);
        if (!container) return;
        if (empty || typeof ApexCharts === 'undefined') {
            container.classList.add('ov-empty');
            container.textContent = data.empty;
            return;
        }
        new ApexCharts(container, {
            colors,
            noData: { text: data.empty },
            dataLabels: { enabled: false },
            legend: { position: 'bottom', fontSize: '11px' },
            grid: { borderColor: '#edf0fa' },
            ...options,
            chart: { height: 250, toolbar: { show: false }, fontFamily: 'Inter, sans-serif', animations: { enabled: !matchMedia('(prefers-reduced-motion: reduce)').matches }, ...options.chart },
        }).render();
    };
    render('overview-activity', {
        chart: { type: 'area', height: 300, toolbar: { show: false }, zoom: { enabled: false } },
        series: data.series,
        xaxis: { type: 'datetime', categories: data.labels, labels: { datetimeUTC: false } },
        yaxis: { min: 0, forceNiceScale: true, labels: { formatter: value => Math.round(value).toLocaleString() } },
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { opacityFrom: .2, opacityTo: .02 } },
        tooltip: { x: { format: 'dd MMM yyyy' } },
    }, !data.series.some(series => series.data.some(value => value > 0)));
    for (const [id, key] of [['overview-status', 'statuses'], ['overview-platforms', 'platforms']]) {
        render(id, { chart: { type: 'donut', height: 240 }, series: data[key].values, labels: data[key].labels, stroke: { width: 4, colors: ['#fff'] }, plotOptions: { pie: { donut: { size: '75%' } } }, legend: { show: false } }, !data[key].values.some(value => value > 0));
    }
    render('overview-email', { chart: { type: 'bar', height: 200, toolbar: { show: false } }, series: [{ name: '', data: data.email.values }], xaxis: { categories: data.email.labels }, plotOptions: { bar: { distributed: true, borderRadius: 5, columnWidth: '38%' } }, legend: { show: false }, yaxis: { labels: { formatter: value => Math.round(value) } } }, !data.email.values.some(value => value > 0));
})();
