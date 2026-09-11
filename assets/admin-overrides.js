(async function () {
    const { default: Chart } = await import('https://cdn.jsdelivr.net/npm/chart.js@4/auto/+esm');

    const STORAGE_KEY = 'rtn-admin-view-mode';

    const PALETTE = ['#88c0d0', '#5e81ac', '#b48ead', '#d08770', '#a3be8c', '#bf616a', '#ebcb8b', '#4c566a'];

    // ---------- view toggle (table / grid) ----------
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
            <button type="button" data-mode="table">
                <i class="fa fa-list"></i> Tableau
            </button>
            <button type="button" data-mode="grid">
                <i class="fa fa-grip"></i> Grille
            </button>
        `;

        table.parentElement.insertBefore(wrap, table);

        wrap.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', () => {
                const mode = btn.dataset.mode;
                localStorage.setItem(STORAGE_KEY, mode);
                applyMode(mode);
            });
        });

        const saved = localStorage.getItem(STORAGE_KEY) || 'grid';
        applyMode(saved);
    }

    // ---------- chart helpers ----------
    function makeCard(title, wide = false) {
        const card = document.createElement('div');
        card.className = 'rtn-chart-card' + (wide ? ' rtn-chart-wide' : '');
        card.innerHTML = `<div class="rtn-chart-title">${title}</div>`;
        const canvas = document.createElement('canvas');
        card.appendChild(canvas);
        return { card, canvas };
    }

    function ringChart(canvas, labels, values, title) {
        if (!labels.length) {
            canvas.parentElement.innerHTML += '<div class="rtn-chart-empty">Aucune donnée</div>';
            return;
        }
        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: labels.map((_, i) => PALETTE[i % PALETTE.length]),
                    borderColor: '#242b38',
                    borderWidth: 3,
                }],
            },
            options: {
                cutout: '72%',
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { color: '#d8dee9', boxWidth: 12, padding: 12, font: { size: 12 } },
                    },
                },
            },
        });
    }

    function barChart(canvas, labels, values) {
        if (!labels.length) {
            canvas.parentElement.innerHTML += '<div class="rtn-chart-empty">Aucune donnée</div>';
            return;
        }
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: '#88c0d0',
                    borderRadius: 8,
                    maxBarThickness: 42,
                }],
            },
            options: {
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { color: '#9aa7ba' }, grid: { display: false } },
                    y: { ticks: { color: '#9aa7ba' }, grid: { color: 'rgba(255,255,255,0.06)' }, beginAtZero: true },
                },
            },
        });
    }

    function areaChart(canvas, labels, values) {
        if (!labels.length) {
            canvas.parentElement.innerHTML += '<div class="rtn-chart-empty">Aucune donnée</div>';
            return;
        }
        const ctx = canvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 260);
        gradient.addColorStop(0, 'rgba(136, 192, 208, 0.45)');
        gradient.addColorStop(1, 'rgba(163, 190, 140, 0.02)');

        new Chart(canvas, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    data: values,
                    borderColor: '#8fbcbb',
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointBackgroundColor: '#8fbcbb',
                }],
            },
            options: {
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { color: '#9aa7ba' }, grid: { display: false } },
                    y: { ticks: { color: '#9aa7ba' }, grid: { color: 'rgba(255,255,255,0.06)' }, beginAtZero: true },
                },
            },
        });
    }

    function entries(obj) {
        return Object.entries(obj || {});
    }

    // ---------- section chart builders ----------
    function renderMachineCharts(root, stats) {
        const s = stats.machines;
        const c1 = makeCard('Machines par statut');
        const c2 = makeCard('Machines par catégorie');
        root.append(c1.card, c2.card);
        ringChart(c1.canvas, ...unzip(entries(s.byStatus)));
        barChart(c2.canvas, ...unzip(entries(s.byCategory)));
    }

    function renderFaultCharts(root, stats) {
        const s = stats.faults;
        const c1 = makeCard('Pannes déclarées dans le temps', true);
        const c2 = makeCard('Pannes par gravité');
        const c3 = makeCard('Pannes par statut');
        root.append(c1.card, c2.card, c3.card);
        areaChart(c1.canvas, ...unzip(entries(s.overTime)));
        ringChart(c2.canvas, ...unzip(entries(s.bySeverity)));
        ringChart(c3.canvas, ...unzip(entries(s.byStatus)));
    }

    function renderInterventionCharts(root, stats) {
        const s = stats.interventions;
        const c1 = makeCard('Interventions dans le temps', true);
        const c2 = makeCard('Interventions par type');
        const c3 = makeCard('Interventions par statut');
        root.append(c1.card, c2.card, c3.card);
        areaChart(c1.canvas, ...unzip(entries(s.overTime)));
        ringChart(c2.canvas, ...unzip(entries(s.byType)));
        ringChart(c3.canvas, ...unzip(entries(s.byStatus)));
    }

    function renderUserCharts(root, stats) {
        const s = stats.users;
        const c1 = makeCard('Utilisateurs par rôle');
        root.append(c1.card);
        ringChart(c1.canvas, ...unzip(entries(s.byRole)));
    }

    function unzip(pairs) {
        return [pairs.map(p => p[0]), pairs.map(p => p[1])];
    }

    // ---------- context detection & bootstrap ----------
    function getEmbeddedStats() {
        const el = document.getElementById('rtn-stats-embedded');
        if (!el) return null;
        try {
            return JSON.parse(el.textContent);
        } catch (e) {
            return null;
        }
    }

    function currentCrudEntity() {
        const params = new URLSearchParams(window.location.search);
        const fqcn = params.get('crudControllerFqcn') || '';
        if (fqcn.includes('MachineCrudController')) return 'machines';
        if (fqcn.includes('FaultCrudController')) return 'faults';
        if (fqcn.includes('InterventionCrudController')) return 'interventions';
        if (fqcn.includes('UserCrudController')) return 'users';
        return null;
    }

    async function fetchStats() {
        const res = await fetch('/admin/stats-data');
        return res.json();
    }

    async function bootCharts() {
        const dashboardRoot = document.getElementById('rtn-charts-root');
        if (dashboardRoot) {
            const stats = getEmbeddedStats() || await fetchStats();
            renderMachineCharts(dashboardRoot, stats);
            renderFaultCharts(dashboardRoot, stats);
            renderInterventionCharts(dashboardRoot, stats);
            renderUserCharts(dashboardRoot, stats);
            return;
        }

        const entity = currentCrudEntity();
        const table = document.querySelector('table.datagrid');
        if (!entity || !table || document.querySelector('.rtn-charts-grid')) {
            return;
        }

        const root = document.createElement('div');
        root.className = 'rtn-charts-grid';
        table.parentElement.insertBefore(root, table);

        const stats = await fetchStats();
        if (entity === 'machines') renderMachineCharts(root, stats);
        if (entity === 'faults') renderFaultCharts(root, stats);
        if (entity === 'interventions') renderInterventionCharts(root, stats);
        if (entity === 'users') renderUserCharts(root, stats);
    }

    function boot() {
        buildToggle();
        bootCharts();
    }

    document.addEventListener('DOMContentLoaded', boot);
    document.addEventListener('turbo:load', boot);
})();