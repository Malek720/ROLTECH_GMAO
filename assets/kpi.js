(function () {
    'use strict';

    // Chart.js is the only allowed CDN (rule 7) and it is loaded lazily: the tables are the
    // primary content and must keep working when the CDN is unreachable.
    const CHART_CDN = 'https://cdn.jsdelivr.net/npm/chart.js@4/auto/+esm';
    const PALETTE = ['#88c0d0', '#5e81ac', '#b48ead', '#d08770', '#a3be8c', '#bf616a', '#ebcb8b', '#4c566a'];
    const TEXT_MUTED = '#9aa7ba';

    // Lazy loader with a cache so the CDN is fetched at most once per page.
    let chartPromise = null;
    function loadChart() {
        if (!chartPromise) {
            chartPromise = import(CHART_CDN)
                .then(mod => mod.default)
                .catch(err => {
                    chartPromise = null; // allow a retry on the next boot
                    throw err;
                });
        }
        return chartPromise;
    }

    function readData() {
        const el = document.getElementById('rtn-kpi-data');
        if (!el) {
            return null;
        }
        try {
            return JSON.parse(el.textContent);
        } catch (e) {
            return null;
        }
    }

    // Every month between the window bounds, inclusive ("YYYY-MM").
    function monthsRange(window) {
        const start = String(window.from || '').slice(0, 7);
        const end = String(window.to || '').slice(0, 7);
        if (!/^\d{4}-\d{2}$/.test(start) || !/^\d{4}-\d{2}$/.test(end)) {
            return [];
        }

        const months = [];
        let [year, month] = start.split('-').map(Number);
        const [endYear, endMonth] = end.split('-').map(Number);
        while (year < endYear || (year === endYear && month <= endMonth)) {
            months.push(`${year}-${String(month).padStart(2, '0')}`);
            month++;
            if (month > 12) {
                month = 1;
                year++;
            }
        }
        return months;
    }

    // One dataset per group (site or model), one bar per month.
    function buildSeries(rows, months) {
        const groups = [...new Set(rows.map(row => row.group))].sort();
        const datasets = groups.map(group => ({
            label: group,
            data: months.map(month => rows
                .filter(row => row.group === group && row.month === month)
                .reduce((sum, row) => sum + row.count, 0)),
        }));

        return { labels: months, datasets };
    }

    function mount(root, Chart, title, series) {
        const card = document.createElement('div');
        card.className = 'rtn-kpi-chart';

        const heading = document.createElement('div');
        heading.className = 'rtn-chart-title';
        heading.textContent = title;

        const body = document.createElement('div');
        body.className = 'rtn-chart-body';
        card.append(heading, body);
        root.append(card);

        if (!series.datasets.length) {
            body.innerHTML = '<p class="rtn-empty">Aucune donnée à représenter.</p>';
            return;
        }

        const canvas = document.createElement('canvas');
        body.append(canvas);

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: series.labels,
                datasets: series.datasets.map((dataset, index) => ({
                    ...dataset,
                    backgroundColor: PALETTE[index % PALETTE.length],
                    maxBarThickness: 36,
                })),
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#d8dee9', boxWidth: 10 } } },
                scales: {
                    x: { stacked: true, ticks: { color: TEXT_MUTED }, grid: { display: false } },
                    y: { stacked: true, beginAtZero: true, ticks: { color: TEXT_MUTED, precision: 0 }, grid: { color: 'rgba(255,255,255,0.06)' } },
                },
            },
        });
    }

    function boot() {
        const root = document.getElementById('rtn-kpi-charts-root');
        if (!root || root.dataset.rtnState) {
            return;
        }
        const data = readData();
        if (!data) {
            return;
        }

        // Marked synchronously so a second boot() cannot draw twice.
        root.dataset.rtnState = 'loading';

        const months = monthsRange(data.window);
        loadChart().then(Chart => {
            mount(root, Chart, 'Pannes par site', buildSeries(data.faultsPerSite || [], months));
            mount(root, Chart, 'Pannes par modèle', buildSeries(data.faultsPerModel || [], months));
            root.dataset.rtnState = 'ready';
        }).catch(err => {
            console.error('[rtn] KPI charts unavailable:', err);
            root.dataset.rtnState = 'error';
            root.innerHTML = '<p class="rtn-kpi-error">Impossible de charger les graphiques (bibliothèque indisponible). Les tableaux ci-dessus restent disponibles.</p>';
        });
    }

    // Listeners are registered immediately (rule 7): the CDN import is never awaited here.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    document.addEventListener('turbo:load', boot);
})();
