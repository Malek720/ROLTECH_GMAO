# Phase 6 Notes — Work by Antigravity (branch `antigravity/phase6-7`)

## Summary of Phase 6 Implementation

Phase 6 implements the Tunisia map and navigation:
1. **Schema & Migration**:
   - `Site`: added nullable float fields `$latitude` and `$longitude` with getters/setters.
   - `Version20261115130000.php`: migration tested on `roltech_gmao_copy` and test database `roltech_gmao_copy_test`.
   - Backfill coordinates: Tunis (36.8065, 10.1815), Sousse (35.8256, 10.6084), Béja (36.7256, 9.1817), Jendouba (36.5011, 8.7803).
   - `AppFixtures.php` updated to seed coordinates for all four sites.

2. **Map Data Service (`SiteMapDataProvider`)**:
   - Scoping (rule 26): `ROLE_ADMIN` and `ROLE_RESPONSABLE` see all sites; `ROLE_TECHNICIEN` sees only their own site (or empty list if no site).
   - Marker worst-state color calculation:
     - `red`: any machine `en panne` OR any open `critique` fault.
     - `orange`: any active (non-archived) maintenance schedule in state `echue` or `proche`.
     - `green`: all operational / ok.
   - Object-level authorization check: `canViewSite(User $user, Site $site)` restricts a technician from accessing other sites.

3. **Leaflet & GeoJSON Setup**:
   - Installed Leaflet 1.9.4 and `leaflet.min.css` locally via AssetMapper `importmap.php` (no CDN).
   - Real Tunisia GeoJSON boundary stored in `assets/tunisia.geojson` (15 KB, loaded dynamically via `fetch(data-geojson-url)`).
   - Map script in `assets/map.js` registered as an entrypoint `map` in `importmap.php`.
   - Accessible markers with both SVG icons, state symbols, and text labels (rule 24).
   - Popups showing site name, city, state badge, open fault count, machine counts by status and model, and a "Voir les machines" button.

4. **Routes, Controllers & Access Control (`config/packages/security.yaml`)**:
   - `GET /carte` (`map_index`): full interactive map page (`ROLE_TECHNICIEN`).
   - `GET /carte/card` (`map_card`): compact map widget embedded in dashboards (`ROLE_TECHNICIEN`).
   - `GET /sites/{id}` (`site_show`): site detail page grouped by zone (`Machine.location`) (`ROLE_TECHNICIEN`, scoped with 403 on other sites).
   - `GET /parc` (`machine_list`): machine catalog list with text search, status, model, and family filters (`ROLE_TECHNICIEN`, scoped by user site).
   - `GET|POST /machines/{id}/fiche`: preserved strictly for `ROLE_RESPONSABLE` (technicien gets 403).
   - Access control rules order in `security.yaml`: specific routes `/carte`, `/sites`, `/parc` before general rules.

5. **Breadcrumbs & Layout**:
   - Shared breadcrumb component `templates/_partials/breadcrumb.html.twig` implementing `Tunisie > Site > Zone > Machine > Module > Pièce`.
   - Included in site page, machine list, machine fiche (additive only, preserving QR code and print styles), fault declaration form, and map page.
   - Compact map cards added to `templates/dashboard/technicien.html.twig` and `templates/dashboard/responsable.html.twig`.

6. **Tests**:
   - Pure unit tests in `tests/Unit/SiteMapDataProviderTest.php` covering color priority, faults, schedules, and permissions.
   - Self-contained database functional tests in `tests/Functional/MapAndNavigationTest.php` on `roltech_gmao_copy_test` asserting role scoping, 403 checks, machine filtering, and breadcrumbs. Repeatable twice in a row.

## Phase 7: 3D Viewer & Module Health

### Step 7.2: Backend Implementation
- `ModuleHealthProvider`:
  - Pure service computing module health (`echue` > `proche` > `ok`) from active maintenance schedules (`archivedAt IS NULL`).
  - Bottom-up recursive health propagation across arbitrary nesting levels (tested across 3 levels: M1 -> M2 -> M3).
  - Constant query count: 3 DQL queries to load modules/parts, active schedules/tasks, and open faults.
  - Formats flat module tree with `parentId`, `openFaults` count, `nextTask` with action and ISO 8601 `dueAt`.
- `MachineViewerController`:
  - `GET /parc/{id}/viewer-data` under `^/parc` (`ROLE_TECHNICIEN`).
  - Object-level authorization: 403 for techniciens on other sites, 200 for their own site and for responsable/admin.
- `FaultController`:
  - Supports `?machine=<id>&module=<id>&part=<id>` prefilling on `GET /faults/new`.
  - Server-side validation against machine accessibility (rule 26) and model matching (rule 33).
  - Invalid parameters trigger warning flashes and are cleanly ignored without crashing.
- `MachineFicheController`:
  - Scoped timeline filtering via read-only `?module=<id>` query parameter (invalid IDs safely ignored).
- Tests:
  - `tests/Unit/ModuleHealthProviderTest.php` (health ranking logic).
  - `tests/Functional/MachineViewerApiTest.php` (role scoping, JSON structure, 2-level health propagation, archived schedule exclusion, fault form prefill, timeline filtering).

### Step 7.3: Shapes and Per-Model Configs
- `assets/viewer/builder.js`: procedural schematic shapes generator (`rail`, `traction`, `trolley`, `return180`, `return90`, `motor`, `tensioner`, `pulley`, `carrier`, `dryer`, `block`) using low-poly `MeshStandardMaterial` primitives.
- `assets/viewer/models/*.config.js`: 5 configurations covering OC-CA1100, HLS-CA1100, HLS-PXL-CA1100, HLS-PXL-CA1100-01, and PPXXL-CA1100 with accurate French labels and module codes from fixtures, x-axis layout, explosion vectors, and camera focus.
- `assets/viewer/generic.js`: deterministic 3-column grid fallback for press (HP-2000X), compressor (AirMax-300), and unknown models.
- `assets/viewer/configs.js`: dynamic loader with literal imports for AssetMapper and automatic fallback.


### Step 7.4: Engine Bootstrap (previous session)
- `assets/viewer-page.js`: Turbo-aware entrypoint — fetches viewer-data endpoint, mounts the 3D engine, dispatches `rtn:viewer-unsupported` on WebGL failure or network error.
- `assets/viewer/viewer.js`: Three.js engine — orbit/zoom/pan, raycasting for module pick, explode slider, health-coloured meshes, legend, `rtn:module-selected` / `rtn:viewer-ready` custom events, `container.rtnViewer.{selectModule, clearSelection, setExplode, destroy}` public API.
- `importmap.php`: added `three`, `three/addons/controls/OrbitControls.js`, `viewer` entrypoint.
- `templates/machine/fiche.html.twig`: viewer `<div data-rtn-viewer data-url="…">` placeholder added (later replaced in 7.5).

### Step 7.5: Panel UI, 2D Fallback and Integration
**Files created/modified:**
- `assets/viewer-panel.js` (new entrypoint `viewer-panel`): panel controller loaded after the engine entry.
  - Synchronous `turbo:load` / `turbo:before-cache` / `DOMContentLoaded` listeners (rule 7).
  - `fetchViewerData(url)`: fetch + cache; cache cleared on `turbo:before-cache` teardown.
  - `updatePanelHeader()`: model name, data-status badge (rule 24/28), load limit or "charge max. à confirmer".
  - `renderModulePanel()`: module code/name, demo badge, health badge (icon+color+text), next task date, open fault count, parts list (part buttons call `selectModule` for the module), action buttons min-height 44 px.
  - "Déclarer une panne" button: `/faults/new?machine=<id>&module=<id>`. "Voir l'historique" shown only when `data-can-see-timeline="1"`.
  - `build2DFallbackTree()`: accessible `role="tree"` nested list with `role="treeitem"` buttons, `aria-selected`, keyboard-operable, health badge on each item.
  - On `rtn:viewer-unsupported`: hides the canvas, shows the 2D fallback container + tree.
  - `aria-live="polite"` region announces the selected module name and health.
  - `window._rtnViewerPanel.reinitWrapper(wrapper, oldUrl)`: exposed helper for the fault-form inline script to reload a wrapper after a machine change without re-dispatching `turbo:load` globally.
  - DOM built exclusively with `textContent` / `createElement` — never `innerHTML` with server data (rule).
- `templates/_partials/machine_viewer.html.twig` (new partial):
  - Parameters: `machine` (nullable), `canSeeTimeline` (bool).
  - Layout: 16:10 aspect-ratio canvas (min 320 px, works at 375 px wide) + side panel in a CSS grid; single-column on ≤850 px phones.
  - Panel header: model name, data-status badge, load limit line (all rendered server-side, updated client-side when data loads).
  - `data-rtn-no-machine` hint when `machine` is null (fault form with no machine selected yet).
  - `@media print { .rtn-viewer-layout { display: none !important; } }` — viewer hidden from print (rule).
- `templates/machine/fiche.html.twig`:
  - Loads `importmap(['viewer', 'viewer-panel'])` (panel after engine).
  - Viewer section uses `{% include '_partials/machine_viewer.html.twig' with { machine: machine, canSeeTimeline: true } %}`.
  - Print stylesheet extended: `.rtn-viewer-section` and `.rtn-viewer-layout` both hidden in print — QR block and machine identity still visible (phase 8 intact).
  - Inline `height: 480px` CSS removed from the partial (now uses `aspect-ratio`).
- `templates/fault/new.html.twig`:
  - Loads `importmap(['viewer', 'viewer-panel'])`.
  - Collapsible `<details id="rtn-fault-viewer-collapsible">` with `<summary>` styled as a secondary button (min-height 44 px): "Choisir sur le schéma 3D".
  - Includes the partial with `canSeeTimeline: false` and `machine: selectedMachine` (null when no machine pre-selected).
  - Inline script: on `rtn:module-selected` from inside the collapsible, sets the module `<select>` value and clears the part select (dropdowns remain the source of truth; form works with JS disabled).
  - On machine dropdown change: updates `container.dataset.url`, calls `window._rtnViewerPanel.reinitWrapper(wrapper, oldUrl)` to reload the viewer for the new machine; no global `turbo:load` dispatch.
- `importmap.php`: added `viewer-panel` entrypoint pointing to `./assets/viewer-panel.js`.

**What cannot be tested without a browser:**
- Actual 3D rendering and module click-to-highlight.
- WebGL context-lost fallback path.
- Orbit/zoom/pan interactions.
- `<details>` expand/collapse animation in the fault form.
- Font Awesome icon rendering (CDN).

**Tests run:**
- `tests/Functional/MachineViewerApiTest.php`: 4 tests, 101 assertions — OK (viewer API, role scoping, JSON structure).
- `tests/Unit/ModuleHealthProviderTest.php`: 1 test, 6 assertions — OK.
- `tests/Functional/MapAndNavigationTest.php`: 6 tests, 34 assertions — OK.
- Full suite: **128 tests, 539 assertions, 0 failures, 0 errors**, 1 pre-existing PHPUnit deprecation.

**Requirements with no automated test (panel/viewer):**
- Panel DOM rendering (health badge, parts list, action button URLs) — browser only.
- 2D fallback tree keyboard navigation.
- Fault-form viewer → module dropdown binding.
- Print-hidden viewer block.
