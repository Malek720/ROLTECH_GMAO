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
