/**
 * RolTech GMAO — Viewer page entry-point (Step 7.4)
 *
 * Rule 7:  Listener setup (turbo:load, turbo:before-cache) is SYNCHRONOUS —
 *          no await before the addEventListener calls.
 * Rule 22: No CDN — Three.js loaded from local AssetMapper assets.
 *
 * Usage:  {{ importmap('viewer') }} on any page containing [data-rtn-viewer].
 * Data attribute: data-url="/api/machine/{id}/modules"
 */

import { mountViewer } from './viewer/viewer.js';

/** @type {WeakMap<HTMLElement, true>} tracks containers we've mounted */
const mounted = new WeakMap();

function getContainer() {
    return document.querySelector('[data-rtn-viewer]');
}

async function boot() {
    const container = getContainer();
    if (!container) return;
    if (mounted.has(container)) return; // already up

    const url = container.dataset.url;
    if (!url) {
        console.warn('[RolTech Viewer] [data-rtn-viewer] is missing data-url.');
        return;
    }

    let data;
    try {
        const resp = await fetch(url, { credentials: 'same-origin' });
        if (resp.status === 403) {
            container.dispatchEvent(new CustomEvent('rtn:viewer-unsupported', {
                bubbles: true,
                detail: { reason: 'forbidden', status: 403 },
            }));
            return;
        }
        if (!resp.ok) {
            container.dispatchEvent(new CustomEvent('rtn:viewer-unsupported', {
                bubbles: true,
                detail: { reason: 'http-error', status: resp.status },
            }));
            return;
        }
        data = await resp.json();
    } catch (err) {
        container.dispatchEvent(new CustomEvent('rtn:viewer-unsupported', {
            bubbles: true,
            detail: { reason: 'network-error', error: err?.message },
        }));
        return;
    }

    mounted.set(container, true);
    await mountViewer(container, data);
}

function teardown() {
    const container = getContainer();
    if (!container) return;
    if (container.rtnViewer) {
        container.rtnViewer.destroy();
    }
    mounted.delete(container);
}

// ── Synchronous listener registration (rule 7) ────────────────────────────
document.addEventListener('turbo:load', boot);
document.addEventListener('turbo:before-cache', teardown);

// Also boot immediately if Turbo already fired (page loaded without Turbo navigation)
if (document.readyState !== 'loading') {
    boot();
} else {
    document.addEventListener('DOMContentLoaded', boot);
}
