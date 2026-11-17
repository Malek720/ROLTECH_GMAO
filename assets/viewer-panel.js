/**
 * RolTech GMAO — 3D Viewer Panel & 2D Fallback Controller (Step 7.5)
 *
 * Rule 7:  Listeners registered immediately and synchronously (no await before addEventListener).
 * Rule 24: Status never shown by color alone (always icon + color + text label).
 * Rule 28: Demo data explicitly marked with "Données de démonstration" badge.
 * DOM built with textContent and createElement (never innerHTML with external data).
 */

const BADGE_DEFAULTS = {
    'ok': { icon: 'fa-circle-check', color: '#a3be8c', label: 'OK' },
    'proche': { icon: 'fa-clock', color: '#ebcb8b', label: 'Proche' },
    'echue': { icon: 'fa-circle-exclamation', color: '#bf616a', label: 'Échue' },
    'confirmed': { icon: 'fa-shield-check', color: '#5e81ac', label: 'Données confirmées' },
    'demo': { icon: 'fa-flask', color: '#b48ead', label: 'Données de démonstration' },
};

/**
 * Creates a standard RolTech status badge element without using innerHTML.
 * @param {string} status
 * @returns {HTMLSpanElement}
 */
function createStatusBadge(status) {
    const entry = BADGE_DEFAULTS[status] || { icon: 'fa-circle-question', color: '#4c566a', label: status || 'Inconnu' };
    const badge = document.createElement('span');
    badge.className = 'rtn-badge';
    badge.style.setProperty('--rtn-badge-color', entry.color);

    const iconWrap = document.createElement('span');
    iconWrap.className = 'rtn-badge-icon';
    const icon = document.createElement('i');
    icon.className = 'fa ' + entry.icon;
    iconWrap.appendChild(icon);
    badge.appendChild(iconWrap);

    const label = document.createElement('span');
    label.textContent = entry.label;
    badge.appendChild(label);

    return badge;
}

/** Cache for fetched endpoint data keyed by URL */
const dataCache = new Map();

/**
 * Fetch and cache viewer data.
 * @param {string} url
 * @returns {Promise<object|null>}
 */
async function fetchViewerData(url) {
    if (!url) return null;
    if (dataCache.has(url)) {
        return dataCache.get(url);
    }
    try {
        const resp = await fetch(url, { credentials: 'same-origin' });
        if (!resp.ok) return null;
        const data = await resp.json();
        dataCache.set(url, data);
        return data;
    } catch {
        return null;
    }
}

/**
 * Update panel header with model information.
 * @param {HTMLElement} wrapper
 * @param {object} model
 */
function updatePanelHeader(wrapper, model) {
    if (!model) return;
    const nameEl = wrapper.querySelector('[data-rtn-model-name]');
    if (nameEl && model.name) {
        nameEl.textContent = model.name;
    }
    const badgeContainer = wrapper.querySelector('[data-rtn-model-badge]');
    if (badgeContainer && model.dataStatus) {
        badgeContainer.replaceChildren(createStatusBadge(model.dataStatus));
    }
    const loadText = wrapper.querySelector('[data-rtn-load-text]');
    if (loadText) {
        if (model.maxLoadKg !== null && model.maxLoadKg !== undefined && model.maxLoadKg !== '') {
            loadText.textContent = model.maxLoadKg + ' kg';
        } else {
            loadText.textContent = 'charge max. à confirmer';
        }
    }
}

/**
 * Build 2D accessible nested list tree for modules.
 * @param {HTMLElement} wrapper
 * @param {HTMLElement} container
 * @param {object} data
 */
function build2DFallbackTree(wrapper, container, data) {
    const treeNav = wrapper.querySelector('[data-rtn-viewer-tree]');
    if (!treeNav || !data?.modules) return;

    treeNav.replaceChildren();

    const modules = data.modules;
    const moduleMap = new Map();
    const roots = [];

    // Index modules
    modules.forEach((mod) => {
        moduleMap.set(mod.id, { ...mod, children: [] });
    });

    // Build hierarchy
    modules.forEach((mod) => {
        const node = moduleMap.get(mod.id);
        if (mod.parentId && moduleMap.has(mod.parentId)) {
            moduleMap.get(mod.parentId).children.push(node);
        } else {
            roots.push(node);
        }
    });

    /**
     * Recursive builder for <ul> / <li>.
     * @param {Array} items
     * @param {boolean} isRoot
     * @returns {HTMLUListElement}
     */
    function renderList(items, isRoot) {
        const ul = document.createElement('ul');
        ul.className = isRoot ? 'rtn-viewer-tree-list rtn-viewer-tree-root' : 'rtn-viewer-tree-list';
        ul.setAttribute('role', isRoot ? 'tree' : 'group');

        items.forEach((item) => {
            const li = document.createElement('li');
            li.setAttribute('role', 'treeitem');

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'rtn-viewer-tree-button';
            btn.dataset.moduleId = String(item.id);
            btn.dataset.moduleCode = item.code;

            const textSpan = document.createElement('span');
            textSpan.textContent = item.code + ' — ' + item.name;
            btn.appendChild(textSpan);

            const badge = createStatusBadge(item.health || 'ok');
            btn.appendChild(badge);

            btn.addEventListener('click', () => {
                if (container.rtnViewer && typeof container.rtnViewer.selectModule === 'function') {
                    container.rtnViewer.selectModule(item.id);
                } else {
                    container.dispatchEvent(new CustomEvent('rtn:module-selected', {
                        bubbles: true,
                        detail: { moduleId: item.id, code: item.code },
                    }));
                }
            });

            li.appendChild(btn);

            if (item.children.length > 0) {
                li.appendChild(renderList(item.children, false));
            }

            ul.appendChild(li);
        });

        return ul;
    }

    treeNav.appendChild(renderList(roots, true));
}

/**
 * Render the side panel when a module is selected.
 * @param {HTMLElement} wrapper
 * @param {HTMLElement} container
 * @param {object} data
 * @param {number|null} moduleId
 */
function renderModulePanel(wrapper, container, data, moduleId) {
    const emptyHint = wrapper.querySelector('[data-rtn-panel-empty]');
    const detailsContainer = wrapper.querySelector('[data-rtn-panel-details]');
    const announcer = wrapper.querySelector('[data-rtn-panel-announcer]');

    // Highlight matching 2D tree button if present
    wrapper.querySelectorAll('.rtn-viewer-tree-button').forEach((b) => {
        const isMatch = moduleId !== null && b.dataset.moduleId === String(moduleId);
        b.classList.toggle('rtn-selected', isMatch);
        b.setAttribute('aria-selected', isMatch ? 'true' : 'false');
    });

    if (!moduleId || !data?.modules) {
        if (emptyHint) emptyHint.hidden = false;
        if (detailsContainer) detailsContainer.hidden = true;
        if (announcer) announcer.textContent = 'Sélection réinitialisée.';
        return;
    }

    const mod = data.modules.find((m) => m.id === moduleId);
    if (!mod) {
        if (emptyHint) emptyHint.hidden = false;
        if (detailsContainer) detailsContainer.hidden = true;
        return;
    }

    if (emptyHint) emptyHint.hidden = true;
    if (detailsContainer) detailsContainer.hidden = false;

    // Code & Name
    const codeEl = wrapper.querySelector('[data-rtn-module-code]');
    if (codeEl) codeEl.textContent = mod.code;

    const nameEl = wrapper.querySelector('[data-rtn-module-name]');
    if (nameEl) nameEl.textContent = mod.name;

    // Data Status badge (demo / confirmed)
    const badgeContainer = wrapper.querySelector('[data-rtn-module-badge]');
    if (badgeContainer) {
        if (mod.dataStatus === 'demo') {
            badgeContainer.replaceChildren(createStatusBadge('demo'));
        } else {
            badgeContainer.replaceChildren();
        }
    }

    // Health badge
    const healthContainer = wrapper.querySelector('[data-rtn-module-health]');
    if (healthContainer) {
        healthContainer.replaceChildren(createStatusBadge(mod.health || 'ok'));
    }

    // Next task
    const taskContainer = wrapper.querySelector('[data-rtn-module-task]');
    if (taskContainer) {
        if (mod.nextTask) {
            let taskText = mod.nextTask.action;
            if (mod.nextTask.dueAt) {
                const d = new Date(mod.nextTask.dueAt);
                const dateStr = isNaN(d.getTime()) ? mod.nextTask.dueAt : d.toLocaleDateString('fr-FR');
                taskText += ' · Échéance : ' + dateStr;
            }
            taskContainer.textContent = taskText;
        } else {
            taskContainer.textContent = 'Aucune tâche planifiée à court terme.';
        }
    }

    // Open faults
    const faultsContainer = wrapper.querySelector('[data-rtn-module-faults]');
    if (faultsContainer) {
        if (mod.openFaults > 0) {
            faultsContainer.textContent = mod.openFaults + ' panne(s) en cours';
        } else {
            faultsContainer.textContent = 'Aucune panne déclarée.';
        }
    }

    // Parts list
    const partsContainer = wrapper.querySelector('[data-rtn-module-parts]');
    if (partsContainer) {
        partsContainer.replaceChildren();
        if (mod.parts && mod.parts.length > 0) {
            mod.parts.forEach((part) => {
                const li = document.createElement('li');
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'rtn-viewer-part-btn';

                const labelSpan = document.createElement('span');
                const partNum = part.partNumber ? part.partNumber : 'référence à confirmer';
                const qty = part.quantity ? ' (' + part.quantity + ' pcs)' : '';
                labelSpan.textContent = partNum + ' — ' + part.description + qty;
                btn.appendChild(labelSpan);

                if (part.dataStatus === 'demo') {
                    btn.appendChild(createStatusBadge('demo'));
                }

                btn.addEventListener('click', () => {
                    if (container.rtnViewer && typeof container.rtnViewer.selectModule === 'function') {
                        container.rtnViewer.selectModule(mod.id);
                    }
                });

                li.appendChild(btn);
                partsContainer.appendChild(li);
            });
        } else {
            const li = document.createElement('li');
            li.style.color = '#88c0d0';
            li.style.fontSize = '.85rem';
            li.textContent = 'Aucune pièce enregistrée pour ce module.';
            partsContainer.appendChild(li);
        }
    }

    // Machine ID resolution
    const machineId = wrapper.dataset.machineId || data.machine?.id || '';

    // Action button: Déclarer une panne
    const faultBtn = wrapper.querySelector('[data-rtn-action-fault]');
    if (faultBtn) {
        faultBtn.href = '/faults/new?machine=' + encodeURIComponent(machineId) + '&module=' + encodeURIComponent(mod.id);
    }

    // Action button: Voir l'historique (only when canSeeTimeline is true)
    const timelineBtn = wrapper.querySelector('[data-rtn-action-timeline]');
    const canSeeTimeline = wrapper.dataset.canSeeTimeline === '1';
    if (timelineBtn) {
        if (canSeeTimeline && machineId) {
            timelineBtn.hidden = false;
            timelineBtn.href = '/machines/' + encodeURIComponent(machineId) + '/fiche?module=' + encodeURIComponent(mod.id);
        } else {
            timelineBtn.hidden = true;
        }
    }

    // Accessible screen reader announcement
    if (announcer) {
        const healthText = BADGE_DEFAULTS[mod.health]?.label || 'OK';
        announcer.textContent = 'Module ' + mod.code + ' ' + mod.name + ' sélectionné. État de maintenance : ' + healthText + '.';
    }
}

/**
 * Initialize a single viewer wrapper instance.
 * @param {HTMLElement} wrapper
 */
async function initWrapper(wrapper) {
    if (wrapper._rtnPanelInitialized) return;
    wrapper._rtnPanelInitialized = true;

    const container = wrapper.querySelector('[data-rtn-viewer]');
    if (!container) return;

    const fallback2D = wrapper.querySelector('[data-rtn-viewer-fallback-2d]');
    let data = null;

    async function loadData() {
        const url = container.dataset.url;
        if (!url) return null;
        data = await fetchViewerData(url);
        if (data) {
            if (data.model) updatePanelHeader(wrapper, data.model);
            if (data.machine?.id && !wrapper.dataset.machineId) {
                wrapper.dataset.machineId = String(data.machine.id);
            }
            build2DFallbackTree(wrapper, container, data);
        }
        return data;
    }

    await loadData();

    // ── Handle WebGL unsupported event ────────────────────────────────────
    if (!container._rtnListenersAttached) {
        container._rtnListenersAttached = true;
        container.addEventListener('rtn:viewer-unsupported', async () => {
            container.style.display = 'none';
            if (fallback2D) {
                fallback2D.hidden = false;
            }
            if (!data) {
                await loadData();
            }
        });

        // ── Handle module selection event ──────────────────────────────────────
        container.addEventListener('rtn:module-selected', async (e) => {
            const moduleId = e.detail?.moduleId ?? null;
            if (!data) {
                await loadData();
            }
            renderModulePanel(wrapper, container, data, moduleId);
        });
    }
}

function bootAll() {
    document.querySelectorAll('[data-rtn-viewer-wrapper]').forEach((wrapper) => {
        initWrapper(wrapper);
    });
}

function teardownAll() {
    document.querySelectorAll('[data-rtn-viewer-wrapper]').forEach((wrapper) => {
        wrapper._rtnPanelInitialized = false;
    });
    // Clear fetch cache on Turbo navigation so stale data is never served
    dataCache.clear();
}

/**
 * Reinitialise a single wrapper (called from inline scripts when the machine
 * dropdown changes on the fault form).  Clears the cache for the previous URL
 * so the new data is fetched fresh.
 * @param {HTMLElement} wrapper
 * @param {string} [oldUrl] - Previous data-url to evict from cache.
 */
function reinitWrapper(wrapper, oldUrl) {
    if (oldUrl) dataCache.delete(oldUrl);
    // Destroy the existing 3D viewer before reinitialising to avoid RAF/listener leaks
    const container = wrapper.querySelector('[data-rtn-viewer]');
    if (container?.rtnViewer) {
        container.rtnViewer.destroy();
    }
    wrapper._rtnPanelInitialized = false;
    initWrapper(wrapper);
    if (container?.dataset.url) {
        document.dispatchEvent(new CustomEvent('rtn:viewer-reinit', { bubbles: true }));
    }
}

// ── Synchronous listener setup (rule 7) ────────────────────────────────────
document.addEventListener('turbo:load', bootAll);
document.addEventListener('turbo:before-cache', teardownAll);

if (document.readyState !== 'loading') {
    bootAll();
} else {
    document.addEventListener('DOMContentLoaded', bootAll);
}

// ── Expose helpers for inline scripts (fault form machine-change handler) ──
window._rtnViewerPanel = { reinitWrapper };
