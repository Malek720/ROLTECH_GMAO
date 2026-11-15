/**
 * RolTech GMAO — Leaflet map initialiser (phase 6).
 *
 * Usage:
 *   <div id="rtn-map"
 *        data-sites="{{ sitesJson|e('html_attr') }}"
 *        data-geojson-url="{{ asset('tunisia.geojson') }}"
 *        data-turbo="false">
 *   </div>
 *
 * The GeoJSON URL is loaded with fetch() (instruction 2 from the user).
 * Leaflet CSS is imported as a side-effect via the importmap entry.
 * Listeners are registered immediately — never behind an awaited import (rule 5 / note 5).
 *
 * Marker colour by worst machine state (rule 9):
 *   red    — "en panne" machine or open "critique" fault
 *   orange — active schedule "echue" or "proche"
 *   green  — otherwise
 */
import L from 'leaflet';
import 'leaflet/dist/leaflet.min.css';

const COLORS = { red: '#bf616a', orange: '#d08770', green: '#a3be8c' };
const STATE_LABELS = {
    red: { label: 'Critique / En panne', symbol: '⚠️' },
    orange: { label: 'Maintenance requise', symbol: '⏱️' },
    green: { label: 'Opérationnel', symbol: '✔' }
};

function markerIcon(color) {
    const fill = COLORS[color] ?? COLORS.green;
    const stateInfo = STATE_LABELS[color] ?? STATE_LABELS.green;
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="40" viewBox="0 0 28 40" role="img" aria-label="${stateInfo.label}">
        <title>${stateInfo.label}</title>
        <path d="M14 0C6.27 0 0 6.27 0 14c0 10.5 14 26 14 26S28 24.5 28 14C28 6.27 21.73 0 14 0z" fill="${fill}"/>
        <circle cx="14" cy="14" r="6" fill="#fff"/>
        <text x="14" y="17" font-size="9" text-anchor="middle" font-family="sans-serif" font-weight="bold" fill="${fill}">${stateInfo.symbol}</text>
    </svg>`;
    return L.divIcon({
        html: svg,
        className: 'rtn-map-pin',
        iconSize: [28, 40],
        iconAnchor: [14, 40],
        popupAnchor: [0, -40],
    });
}

function buildPopupHtml(site) {
    const state = STATE_LABELS[site.color] ?? STATE_LABELS.green;
    const statusRows = Object.entries(site.machine_counts || {})
        .map(([s, n]) => `<li><strong>${n}</strong> ${s}</li>`).join('') || '<li>Aucune machine</li>';
    const modelRows = Object.entries(site.model_counts || {})
        .map(([m, n]) => `<li><strong>${n}</strong> ${m}</li>`).join('');
    return `
        <div class="rtn-map-popup">
            <h3 style="margin:0 0 4px;font-size:1.1rem;color:#2e3440;">${site.name}</h3>
            <p style="margin:0 0 8px;font-size:.85rem;color:#4c566a;">Ville : <strong>${site.city}</strong></p>
            <div style="margin:0 0 8px;display:inline-flex;align-items:center;gap:6px;padding:3px 8px;border-radius:999px;background:${COLORS[site.color]}22;color:${COLORS[site.color]};font-weight:700;font-size:.8rem;">
                <span>${state.symbol}</span> <span>${state.label}</span>
            </div>
            <p style="margin:4px 0;font-size:.85rem;">Pannes ouvertes : <strong>${site.open_fault_count}</strong></p>
            <div style="margin:6px 0;font-size:.8rem;">
                <strong>Statuts :</strong>
                <ul style="margin:4px 0;padding-left:18px;">${statusRows}</ul>
            </div>
            ${modelRows ? `<div style="margin:6px 0;font-size:.8rem;"><strong>Modèles :</strong><ul style="margin:4px 0;padding-left:18px;">${modelRows}</ul></div>` : ''}
            <div style="margin-top:10px;">
                <a class="rtn-button" style="display:inline-block;padding:6px 12px;font-size:.85rem;color:#020817;text-decoration:none;" href="/sites/${site.id}" data-turbo="false">Voir les machines</a>
            </div>
        </div>`;
}

function initMap(container) {
    const rawSites = container.dataset.sites;
    const geojsonUrl = container.dataset.geojsonUrl;

    let sites = [];
    try { sites = JSON.parse(rawSites); } catch (_) { /* invalid JSON — skip */ }

    // Tunisia centre, zoom 7
    const map = L.map(container, { preferCanvas: true }).setView([34.0, 9.5], 7);

    // OSM tiles — optional, falls back gracefully when offline
    const tiles = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 18,
    });
    tiles.addTo(map);
    tiles.on('tileerror', () => { /* tiles fail silently; GeoJSON outline stays */ });

    // Tunisia outline from GeoJSON (fetched, never imported as a module)
    if (geojsonUrl) {
        fetch(geojsonUrl)
            .then(r => r.ok ? r.json() : Promise.reject(r.status))
            .then(gj => {
                L.geoJSON(gj, {
                    style: { color: '#5e81ac', weight: 2, fillOpacity: 0.05 },
                }).addTo(map);
            })
            .catch(() => { /* GeoJSON unavailable — map still works */ });
    }

    // Site markers
    const bounds = [];
    sites.forEach(site => {
        if (site.latitude == null || site.longitude == null) return;
        const latlng = [site.latitude, site.longitude];
        bounds.push(latlng);
        L.marker(latlng, { icon: markerIcon(site.color) })
            .bindPopup(buildPopupHtml(site), { maxWidth: 280 })
            .addTo(map);
    });

    if (bounds.length > 1) {
        map.fitBounds(bounds, { padding: [32, 32] });
    }

    // Dispose Leaflet on Turbo navigation to prevent memory leaks
    document.addEventListener('turbo:before-render', () => { map.remove(); }, { once: true });
}

function initAllMaps() {
    const containers = document.querySelectorAll('#rtn-map, .rtn-map-widget');
    containers.forEach(container => {
        if (!container.dataset.initialized) {
            container.dataset.initialized = '1';
            initMap(container);
        }
    });
}

// Register immediately — no await (rule 5)
document.addEventListener('DOMContentLoaded', initAllMaps);
document.addEventListener('turbo:load', initAllMaps);
