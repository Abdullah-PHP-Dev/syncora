(() => {
    const element = document.getElementById('overview-chart-data');
    if (!element) return;
    const data = JSON.parse(element.textContent);
    const colors = ['#6855ed', '#42b9a4', '#efa0bf', '#efa94c', '#6997ef', '#9a76dc'];
    const number = value => new Intl.NumberFormat(data.locale || 'en').format(value);
    const shortNumber = value => value >= 1000 ? `${(value / 1000).toFixed(1).replace(/\.0$/, '')}K` : number(Math.round(value));
    const render = (id, options, empty = false) => {
        const container = document.getElementById(id);
        if (!container) return null;
        if (empty || typeof ApexCharts === 'undefined') {
            container.classList.add('ov-empty');
            container.textContent = data.empty;
            return null;
        }
        const chart = new ApexCharts(container, {
            colors,
            noData: { text: data.empty },
            dataLabels: { enabled: false },
            legend: { position: 'bottom', fontSize: '10px' },
            grid: { borderColor: '#e9edf6', strokeDashArray: 4 },
            ...options,
            chart: { height: 230, toolbar: { show: false }, fontFamily: 'Inter, sans-serif', foreColor: '#929db4', animations: { enabled: !matchMedia('(prefers-reduced-motion: reduce)').matches }, ...options.chart },
        });
        chart.render();
        return chart;
    };
    const dateLabel = value => {
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat(data.locale || 'en', { day: 'numeric', month: 'short' }).format(date);
    };
    const activityChart = render('overview-activity', {
        chart: { type: 'bar', stacked: true, height: matchMedia('(max-width: 800px)').matches ? 330 : 440, zoom: { enabled: false } },
        series: data.series,
        legend: { show: false },
        xaxis: { categories: data.labels, tickAmount: Math.min(data.labels.length, 15), labels: { rotate: 0, hideOverlappingLabels: true, formatter: dateLabel, style: { fontSize: '10px' } }, axisBorder: { color: '#e9edf6' }, axisTicks: { show: false } },
        yaxis: { min: 0, forceNiceScale: true, tickAmount: 5, labels: { formatter: shortNumber } },
        plotOptions: { bar: { borderRadius: 3, borderRadiusApplication: 'end', columnWidth: '65%' } },
        stroke: { show: true, width: 1, colors: ['#fff'] },
        fill: { opacity: 1 },
        tooltip: { x: { formatter: dateLabel }, y: { formatter: number } },
        responsive: [{ breakpoint: 500, options: { xaxis: { tickAmount: 5 }, chart: { height: 300 } } }],
    }, !data.series.length);
    if (activityChart && !data.series.some(series => series.data.some(value => value > 0))) {
        const notice = document.createElement('p');
        notice.className = 'ov-chart-zero';
        notice.textContent = data.empty;
        document.getElementById('overview-activity').appendChild(notice);
    }
    const filters = [...document.querySelectorAll('[data-overview-series]')];
    filters.forEach(input => input.addEventListener('change', () => {
        const active = filters.filter(filter => filter.checked).map(filter => Number(filter.dataset.overviewSeries));
        const selectedTotal = document.getElementById('overview-selected-total');
        if (selectedTotal) selectedTotal.textContent = number(active.reduce((total, index) => total + data.series[index].data.reduce((sum, value) => sum + value, 0), 0));
        if (!activityChart) return;
        // Keep the series order so each module retains its assigned color.
        activityChart.updateSeries(data.series.map((series, index) => ({ name: series.name, data: active.includes(index) ? series.data : series.data.map(() => 0) })));
    }));
    for (const [id, key] of [['overview-status', 'statuses'], ['overview-platforms', 'platforms']]) {
        render(id, { chart: { type: 'donut', height: 230 }, series: data[key].values, labels: data[key].labels, stroke: { width: 4, colors: ['#fff'] }, plotOptions: { pie: { donut: { size: '78%' } } }, legend: { show: false }, tooltip: { y: { formatter: number } } }, !data[key].values.some(value => value > 0));
    }
    render('overview-email', { chart: { type: 'bar', height: 200 }, series: [{ name: '', data: data.email.values }], xaxis: { categories: data.email.labels, labels: { style: { fontSize: '10px' } } }, plotOptions: { bar: { distributed: true, borderRadius: 4, columnWidth: '38%' } }, legend: { show: false }, yaxis: { labels: { formatter: shortNumber } }, tooltip: { y: { formatter: number } } }, !data.email.values.some(value => value > 0));
})();
