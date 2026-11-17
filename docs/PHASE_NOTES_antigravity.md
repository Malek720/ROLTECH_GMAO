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


---

## Phase 7 review — 2026-11-17

### Findings and fixes

| # | Finding | Severity | Fix |
|---|---------|----------|-----|
| F1 | `viewer.js` reads `data.modelCode` but `/parc/{id}/viewer-data` returns `data.model.code` → config lookup always returns `''` → all models fall back to generic grid layout. | **Bug (functional)** | Fixed: `data.model?.code ?? data.modelCode ?? ''` in `viewer.js` line 84. |
| F2 | `reinitWrapper` (viewer-panel.js) resets `_rtnPanelInitialized` without calling `container.rtnViewer.destroy()` → machine-change on the fault form leaks the previous RAF loop, ResizeObserver and canvas. | **Bug (resource leak)** | Fixed: added `container.rtnViewer?.destroy()` before reset in `reinitWrapper`. |
| F3 | `viewer.js` legend builder uses `innerHTML` with template literal: `item.innerHTML = \`<span…>${color}</span><span>${text}</span>\``. Both `color` (hex string from a local constant) and `text` (hardcoded French word) are not server data. | Low — no XSS risk (data is hardcoded), but pattern is inconsistent with the stated "no innerHTML with external data" rule. Left as-is; no security issue. |
| F4 | POST branch of `FaultController::new()` does not re-check that module/part belong to the selected machine's model. Rule 33 is nonetheless enforced by `FaultWorkflow::declare()` which throws `InvalidArgumentException` caught by the controller and shown as a form error. Acceptable; no bypass possible. | Acceptable — service enforces it. |
| F5 | `window._rtnViewerPanel` is a module-level singleton; it does not accumulate state across Turbo visits because `teardownAll` / `reinitWrapper` reset per-wrapper flags rather than the global. No cross-visit leak. | OK |
| F6 | Fault form viewer is inside `<details>` (zero size when closed). `mountViewer` reads `clientWidth \|\| 400` and `clientHeight \|\| 300` as fallback, so no throw on 0×0. `ResizeObserver` fires when `<details>` opens and resizes correctly. | OK |
| F7 | `viewer-page.js` and `viewer-panel.js` register all listeners synchronously (rule 7 compliant). `turbo:before-cache` teardown calls `destroy()` via `container.rtnViewer`. | OK |
| F8 | Font Awesome `fa-*` icons used by `viewer-panel.js` badges. `git log` shows FA was present in `base.html.twig` before phase 7 (commit `ffc8144`). **Phase 7 did not introduce it.** Report only: FA is served from a CDN not controlled by phase 7; fix belongs in phase 9. | Pre-existing |
| F9 | Inline `<script>` in `fault/new.html.twig` registers listeners using `document.getElementById` (not `document.addEventListener`) guarded by null checks. With `data-turbo="false"` on the `<form>`, the page always does a full load → no Turbo re-run issue. Listener registration is not duplicated (no `addEventListener` on `document`). | OK |
| F10 | `three` importmap entry (version 0.169.0, tagged 2024-10-23). Version is > 14 days old, local via AssetMapper, no CDN. License: MIT. | OK — rule 22 satisfied |
| F11 | Print stylesheet in `machine/fiche.html.twig` hides `.rtn-viewer-layout` and `.rtn-viewer-section`; QR block is printed. Phase 8 QR block intact. | OK |
| F12 | No `console.log` or debug routes found in phase 7 files. | OK |
| F13 | `ModuleHealthProvider`: exactly 3 queries (modules+parts, schedules, faults), all batch-loaded. No N+1. | OK |
| F14 | `access_control` order: `/parc` (ROLE_TECHNICIEN) is listed after `/maintenance-plan`, `/indicateurs`, before `/machines` (ROLE_RESPONSABLE). `/machines/{id}/fiche` is under `/machines` rule — still ROLE_RESPONSABLE. `MachineFicheController` also carries `#[IsGranted('ROLE_RESPONSABLE')]`. | OK — double-enforced |
| F15 | Technicien 403 on cross-site viewer-data: enforced by `SiteMapDataProvider::canViewSite()` in `MachineViewerController`. Tested by `MachineViewerApiTest::testViewerDataEndpointRoleScoping`. | OK |

### Routes with roles

| Route | Method | Min role | Notes |
|-------|--------|----------|-------|
| `/parc/{id}/viewer-data` | GET | ROLE_TECHNICIEN | Technicien scoped to own site; responsable/admin all sites |
| `/faults/new?machine=&module=&part=` | GET/POST | IS_AUTHENTICATED_FULLY | Prefill validated on GET; model ownership enforced by FaultWorkflow on POST |
| `/machines/{id}/fiche?module=` | GET | ROLE_RESPONSABLE | Timeline module filter; attribute + firewall |

### Viewer events and API

- `rtn:viewer-ready` — fired on container when 3D scene is fully mounted.
- `rtn:viewer-unsupported` — fired when WebGL unavailable, import fails, or context lost. `viewer-panel.js` listens and shows 2D fallback.
- `rtn:module-selected` — fired on container with `{ moduleId, code }` when user clicks a module or null when deselected. `viewer-panel.js` renders side panel.
- `container.rtnViewer.selectModule(id)` — programmatic select (called from 2D tree and part buttons).
- `container.rtnViewer.destroy()` — full cleanup: RAF cancelled, ResizeObserver + IntersectionObserver disconnected, event listeners removed, geometries/materials disposed, `renderer.forceContextLoss()`, canvas removed.
- `window._rtnViewerPanel.reinitWrapper(wrapper, oldUrl)` — inline-script bridge for the fault form machine-change handler.

### Three.js

- Version: **0.169.0** (npm tag `three@0.169.0`, released 2024-10-23 — > 14 days before this project).
- License: **MIT**.
- Installed via AssetMapper importmap (local files). OrbitControls addon included.
- No CDN; `import('three')` resolves to the locally-mapped asset.

### How to add a model config

1. Create `assets/viewer/models/<MODEL-CODE>.config.js` with a `default` export matching the shape `{ modules: { [moduleCode]: { shape, label, color, position, explodeAxis, explodeDistance, focus } } }`.
2. Add an entry to `MODEL_LOADERS` in `assets/viewer/configs.js`: `'<MODEL-CODE>': () => import('./models/<MODEL-CODE>.config.js')`.
3. Unknown module codes fall back to a simple coloured block with a grid layout (via `buildGenericConfig`).

### GLB loading

The engine **does not load `.glb` files**. The spec (`REMAINING_PHASES.md` §7) says a `.glb` at `assets/models/<model-code>.glb` *replaces* the procedural model if node names match module codes, but this path is **not implemented** — `viewer.js` only calls `makeShape()` (procedural) or falls back to a generic block. All 3D shapes are procedural. GLB support remains a future task.

### PHPUnit results (both runs)

**Run 1 (after schema create):** 128 tests, 539 assertions, 0 failures, 0 errors, 0 skipped, 1 deprecation.  
**Run 2 (without recreating schema):** 128 tests, 539 assertions, 0 failures, 0 errors, 0 skipped, 1 deprecation.  
Suite is idempotent. The 1 deprecation is pre-existing (not from phase 7).

---

### What can only be verified in a browser (manual test)

1. 3D scene renders — WebGL canvas appears, modules visible with correct health colors and legend.
2. Click on a module in the canvas → side panel updates with parts list and health badge.
3. Drag (orbit) vs click (< 5 px movement) distinction.
4. Explode slider separates modules.
5. `prefers-reduced-motion` skips camera tween.
6. Opening `<details>` on the fault form → viewer resizes and loads the 3D scene.
7. Machine-change dropdown on the fault form → old 3D canvas is destroyed (no duplicate canvas in DOM), new scene loads.
8. 2D fallback tree appears when WebGL is unavailable or refused.
9. Font Awesome icons render in panel badges (CDN dependency, pre-existing).
10. Viewer is hidden in print, QR code prints correctly.
11. Turbo navigation: navigating away and back does not leave orphaned canvases or stale event listeners.

### Requirements with no automated test

- 3D rendering correctness (geometry, colors, camera) — browser only.
- Explode slider behavior — browser only.
- `prefers-reduced-motion` skips tween — browser only.
- 2D fallback tree rendered on WebGL-unsupported device — browser only.
- Panel DOM (health badge icons, parts list, action button URLs) — browser only.
- Fault form → viewer → module dropdown binding — browser only.
- Viewer resize when `<details>` opens — browser only.
- GLB model loading — not implemented; no test.
