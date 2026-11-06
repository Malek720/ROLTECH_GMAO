(function () {
    'use strict';

    const STORAGE_KEY = 'rtn-admin-view-mode';
    const STATS_URL = '/admin/stats-data';
    const CHART_CDN = 'https://cdn.jsdelivr.net/npm/chart.js@4/auto/+esm';

    const PALETTE = ['#88c0d0', '#5e81ac', '#b48ead', '#d08770', '#a3be8c', '#bf616a', '#ebcb8b', '#4c566a'];
    const TEXT_MUTED = '#9aa7ba';
    const GRID_LINE = 'rgba(255, 255, 255, 0.06)';
    const CARD_BG = '#242b38';

    // Shorter legend labels so the rings keep their space in narrow cards.
    const LABEL_OVERRIDES = {
        ROLE_ADMIN: 'Administrateur',
        ROLE_RESPONSABLE: 'Responsable',
        ROLE_TECHNICIEN: 'Technicien',
        ROLE_USER: 'Utilisateur',
    };

    const ENTITY_BY_KEY = {
        machine: 'machines',
        fault: 'faults',
        intervention: 'interventions',
        user: 'users',
    };

    // ---------------------------------------------------------------
    // View toggle (table / grid). Synchronous: never waits on Chart.js.
    // ---------------------------------------------------------------
    function applyMode(mode) {
        document.body.classList.toggle('rtn-grid-mode', mode === 'grid');
        document.querySelectorAll('.rtn-view-toggle button').forEach(btn => {
            btn.classList.toggle('rtn-active', btn.dataset.mode === mode);
        });
    }

    function buildToggle() {
        const table = document.querySelector('table.datagrid');
        if (!table || document.querySelector('.rtn-view-toggle')) {
            return;
        }

        const wrap = document.createElement('div');
        wrap.className = 'rtn-view-toggle';
        wrap.innerHTML = `
            <button type="button" data-mode="table"><i class="fa fa-list"></i> Tableau</button>
            <button type="button" data-mode="grid"><i class="fa fa-grip"></i> Grille</button>
        `;
        table.parentElement.insertBefore(wrap, table);

        wrap.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', () => {
                localStorage.setItem(STORAGE_KEY, btn.dataset.mode);
                applyMode(btn.dataset.mode);
            });
        });

        applyMode(localStorage.getItem(STORAGE_KEY) || 'grid');
    }

    // ---------------------------------------------------------------
    // Chart.js is loaded lazily, only when charts are actually drawn.
    // ---------------------------------------------------------------
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

    const DRAW = {
        ring(Chart, canvas, labels, values) {
            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{
                        data: values,
                        backgroundColor: labels.map((_, i) => PALETTE[i % PALETTE.length]),
                        borderColor: CARD_BG,
                        borderWidth: 3,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: { color: '#d8dee9', boxWidth: 10, padding: 10, font: { size: 11 } },
                        },
                    },
                },
            });
        },

        bar(Chart, canvas, labels, values) {
            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{ data: values, backgroundColor: '#88c0d0', borderRadius: 8, maxBarThickness: 36 }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: TEXT_MUTED }, grid: { display: false } },
                        y: { ticks: { color: TEXT_MUTED, precision: 0 }, grid: { color: GRID_LINE }, beginAtZero: true },
                    },
                },
            });
        },

        area(Chart, canvas, labels, values) {
            new Chart(canvas, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        data: values,
                        borderColor: '#8fbcbb',
                        // Gradient is built from the real chart area once it exists.
                        backgroundColor: context => {
                            const { ctx, chartArea } = context.chart;
                            if (!chartArea) return 'rgba(136, 192, 208, 0.25)';
                            const g = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                            g.addColorStop(0, 'rgba(136, 192, 208, 0.45)');
                            g.addColorStop(1, 'rgba(163, 190, 140, 0.02)');
                            return g;
                        },
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3,
                        pointBackgroundColor: '#8fbcbb',
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: TEXT_MUTED }, grid: { display: false } },
                        y: { ticks: { color: TEXT_MUTED, precision: 0 }, grid: { color: GRID_LINE }, beginAtZero: true },
                    },
                },
            });
        },
    };

    // ---------------------------------------------------------------
    // What each section shows. Pure data: no DOM, no Chart.js here.
    // ---------------------------------------------------------------
    const SECTIONS = {
        machines: s => [
            { title: 'Machines par statut', kind: 'ring', data: s.machines?.byStatus },
            { title: 'Machines par catégorie', kind: 'bar', data: s.machines?.byCategory },
        ],
        faults: s => [
            { title: 'Pannes déclarées dans le temps', kind: 'area', wide: true, data: s.faults?.overTime },
            { title: 'Pannes par gravité', kind: 'ring', data: s.faults?.bySeverity },
            { title: 'Pannes par statut', kind: 'ring', data: s.faults?.byStatus },
        ],
        interventions: s => [
            { title: 'Interventions dans le temps', kind: 'area', wide: true, data: s.interventions?.overTime },
            { title: 'Interventions par type', kind: 'ring', data: s.interventions?.byType },
            { title: 'Interventions par statut', kind: 'ring', data: s.interventions?.byStatus },
        ],
        users: s => [
            { title: 'Utilisateurs par rôle', kind: 'ring', data: s.users?.byRole },
        ],
    };

    function mountCharts(root, descriptors, Chart) {
        // Wide trend charts first, then compact ones: this packs the grid
        // tightly instead of leaving gaps next to full-width cards.
        const ordered = [
            ...descriptors.filter(d => d.wide),
            ...descriptors.filter(d => !d.wide),
        ];

        ordered.forEach(d => {
            const card = document.createElement('div');
            card.className = 'rtn-chart-card' + (d.wide ? ' rtn-chart-wide' : '');

            const title = document.createElement('div');
            title.className = 'rtn-chart-title';
            title.textContent = d.title;

            const body = document.createElement('div');
            body.className = 'rtn-chart-body';

            card.append(title, body);
            root.append(card);

            const pairs = Object.entries(d.data || {});
            if (!pairs.length) {
                body.innerHTML = '<div class="rtn-chart-empty">Aucune donnée</div>';
                return;
            }

            const canvas = document.createElement('canvas');
            body.append(canvas);
            DRAW[d.kind](
                Chart,
                canvas,
                pairs.map(([label]) => LABEL_OVERRIDES[label] || label),
                pairs.map(([, value]) => value),
            );
        });
    }

    // ---------------------------------------------------------------
    // Page context + data
    // ---------------------------------------------------------------
    function embeddedStats() {
        const el = document.getElementById('rtn-stats-embedded');
        if (!el) return null;
        try {
            return JSON.parse(el.textContent);
        } catch (e) {
            return null;
        }
    }

    async function fetchStats() {
        const res = await fetch(STATS_URL, { headers: { Accept: 'application/json' } });
        if (!res.ok) {
            throw new Error(`Stats endpoint answered ${res.status}`);
        }
        return res.json();
    }

    // EasyAdmin 5 uses pretty URLs (/admin/fault), not ?crudControllerFqcn=...
    // so the path is checked first; the old query param and the page <h1>
    // are fallbacks in case the URL scheme differs.
    function currentCrudEntity() {
        const path = decodeURIComponent(window.location.pathname).toLowerCase();
        const byPath = path.match(/\/(machine|fault|intervention|user)/);
        if (byPath) return ENTITY_BY_KEY[byPath[1]];

        const fqcn = (new URLSearchParams(window.location.search).get('crudControllerFqcn') || '').toLowerCase();
        const byFqcn = fqcn.match(/(machine|fault|intervention|user)crudcontroller/);
        if (byFqcn) return ENTITY_BY_KEY[byFqcn[1]];

        const heading = (document.querySelector('h1')?.textContent || '').trim().toLowerCase();
        const byHeading = heading.match(/(machine|fault|intervention|user)/);
        if (byHeading) return ENTITY_BY_KEY[byHeading[1]];

        return null;
    }

    async function bootCharts() {
        const dashboardRoot = document.getElementById('rtn-charts-root');
        let root = dashboardRoot;
        let entity = null;

        if (!root) {
            entity = currentCrudEntity();
            const table = document.querySelector('table.datagrid');
            if (!entity || !table || document.querySelector('.rtn-charts-grid')) {
                return;
            }
            root = document.createElement('div');
            root.className = 'rtn-charts-grid';
            // Charts sit above the Tableau/Grille switch, which sits above the data.
            const anchor = document.querySelector('.rtn-view-toggle') || table;
            anchor.parentElement.insertBefore(root, anchor);
        }

        // Marked synchronously so a second boot() can't render twice.
        if (root.dataset.rtnState) return;
        root.dataset.rtnState = 'loading';

        try {
            const [Chart, stats] = await Promise.all([
                loadChart(),
                dashboardRoot ? Promise.resolve(embeddedStats() || fetchStats()) : fetchStats(),
            ]);

            const descriptors = dashboardRoot
                ? Object.values(SECTIONS).flatMap(build => build(stats))
                : SECTIONS[entity](stats);

            mountCharts(root, descriptors, Chart);
            root.dataset.rtnState = 'ready';
        } catch (err) {
            console.error('[rtn] charts failed to load:', err);
            root.dataset.rtnState = 'error';
            root.innerHTML = '<div class="rtn-chart-card rtn-chart-wide"><div class="rtn-chart-empty">Impossible de charger les graphiques.</div></div>';
        }
    }

    function boot() {
        buildToggle();
        bootCharts();
    }

    // Run now if the DOM is already parsed, otherwise wait for it.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    document.addEventListener('turbo:load', boot);
})();
