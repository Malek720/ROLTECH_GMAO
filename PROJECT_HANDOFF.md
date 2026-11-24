# RolTech GMAO — Project Context & Handoff

> Purpose: lets any developer or AI tool continue this project. Written when the planned phases ended; some details of phases 4-5 were taken from task specifications and reports, not from a fresh read of the code. Items marked **(à vérifier)** must be checked against the repository (see "Audit checklist").

## What this project is
Student internship project (ESPRIT, 3rd year computer engineering) for **Roller Technology Systems Industry (RolTech)**, Tunis: a simplified CMMS ("GMAO") for about 40 machines on several sites (Sousse, Béja, Jendouba, Tunis) running 24h/day. Machine fiche (serial number, model, site, status), preventive maintenance plans by module and part, automatic and "predictive" triggering, automatic work orders to technicians, fault declaration with notification, role dashboards, map, 3D viewer, KPIs.
Repos: `ROLTECH_GMAO` (code) and `ROLTECH_STAGE_DOCS` (MkDocs site on GitHub Pages: cahier des charges, UML, notes). The docs repo is public: never put manual-derived content there.

## Stack
Symfony 7 (webapp), Twig, Doctrine ORM + Migrations, MariaDB/MySQL, EasyAdminBundle 5, AssetMapper (no npm build), Symfony UX Turbo, Leaflet and Three.js (local, importmap), Chart.js (lazy CDN import), `chillerlan/php-qrcode` 5.x, PHPUnit.
Dev environments: Windows (XAMPP, MariaDB 10.4.32, PowerShell, phpMyAdmin) and Linux (Ultramarine/Fedora, native MariaDB, zsh). Commands must match the shell.

## Environment and databases
- `.env.local` (never committed) holds `DATABASE_URL` (with the right `serverVersion`) and `DEFAULT_URI` (public base URL used in QR codes; must be reachable by phones).
- Databases: `roltech_gmao` (main), `roltech_gmao_copy` (manual testing), `<name>_test` for PHPUnit (`dbname_suffix: _test`). After a schema change recreate the test schema: `doctrine:schema:drop --full-database --force --env=test` then `doctrine:schema:create --env=test`.
- Per-shell override (PowerShell): `$env:DATABASE_URL="mysql://root:@127.0.0.1:3306/<db>?serverVersion=10.4.32-MariaDB"`; verify with `php bin/console doctrine:query:sql "SELECT DATABASE()"`.
- Backup: `C:\xampp\mysql\bin\mysqldump.exe -u root --result-file=<path outside the repo>\backup.sql <db>` (do not use `>` in PowerShell 5.1: UTF-16). Restore: `cmd /c "C:\xampp\mysql\bin\mysql.exe -u root <db> < backup.sql"`.
- `doctrine:migrations:status` once failed on a metadata mismatch (`doctrine_migration_versions.executed_at`): fixed with `sync-metadata-storage` plus `ALTER TABLE doctrine_migration_versions CHANGE executed_at executed_at DATETIME DEFAULT NULL` on the affected database.
- `machine_module` is `utf8mb4_general_ci`, other tables `utf8mb4_unicode_ci`: use explicit `COLLATE` in cross-table text comparisons (a migration already failed on this).
- After cloning: `composer install`, `php bin/console importmap:install` (`assets/vendor/` is not committed), enable PHP `intl`.

## Test accounts
`admin@roltechsystems.com`, `malek.responsable@roltechsystems.com`, `karim.technicien@roltechsystems.com` (Sousse), `sami.technicien@roltechsystems.com`. All fixture users share one demo password defined in `src/DataFixtures/AppFixtures.php` (not written here, rule 44). Technicians and the responsable are attached to sites.

## Features done
- **Base:** login with cosmetic role picker and role redirect, EasyAdmin dashboard (Nord theme), charts via `AdminStatsProvider`, notifications, `MaintenancePlanner` + `app:maintenance:check`, admin CRUDs.
- **Multi-model catalog:** `MachineModel`, modules per model (nested), parts, tasks, `dataStatus`, 7 models and 23 machines in fixtures (8 OC-CA1100 + 15 demo). Schedules generated per model by `MachineScheduleListener` → `MachineScheduleGenerator`; model change archives old schedules.
- **Catalog admin:** model detail, duplicate, CSV export/import with preview and confirm, regenerate schedules, deletion refusal, filters.
- **Fault declaration:** site-scoped machine list, model-filtered module/part, confirmation page, "mes pannes", idempotency token, toasts, 422 inline errors, prefill `?machine=&module=&part=` validated server-side.
- **Technicien workflow:** intervention detail with safety banner, lock-out confirmation to start, procedure checklist saved server-side, completion with comment and replaced parts (`InterventionPart`), curative completion resolves the fault and notifies, "Je ne peux pas intervenir" returns it to dispatch. (details: à vérifier)
- **Responsable tools:** dispatch board with assign/reassign, manual intervention creation, fiche editing with timeline (`?module=` filter), close/reopen fault, postpone a due task with reason. (details: à vérifier)
- **Map and navigation:** `/carte` (Leaflet, local Tunisia GeoJSON, marker health: red/orange/green with icon and text), site page by zone, machine list `/parc` with search and filters, breadcrumbs, scoped by role.
- **3D viewer:** schematic Three.js scene per model, module health colors, exploded view, side panel, 2D fallback, fault-form picker.
- **KPI, search, QR, login redirect:** KPI page and CSV (availability, mean repair time, faults per site/month), global search, QR per machine (SVG), safe post-login redirect.
- **UI polish (9b):** app shell (sidebar / icon rail / tab bar / drawer), design system `rtn-ui.css`, inline SVG icons (Lucide ISC / Feather MIT), motion with reduced-motion support, contrast tools; later fix of badge colors, map popup and the login page.

## Routes and roles (from `debug:router`; roles per `security.yaml`, à vérifier for items marked ?)
| Area | Routes | Access |
|---|---|---|
| Public | `/login`, `/login_check`, `/logout` | anonymous |
| Dashboards | `/technicien`, `/responsable` | technicien / responsable |
| Faults | `/faults/new` (GET, POST), `/faults`, `/faults/{id}/confirmation` (owner only), `/faults/interventions/{id}` (? object-level check to test) | authenticated |
| Interventions | `/interventions`, `/interventions/{id}`, POST `/start`, `/checklist`, `/complete`, `/cannot-intervene` | technicien, own interventions only (?) |
| Notifications | `/notifications`, POST `/notifications/{id}/read`, POST `/notifications/read-all` | authenticated (?) |
| Map / sites / list | `/carte`, `/carte/card`, `/sites/{id}`, `/parc` | technicien and above, scoped by site |
| Viewer data | `/parc/{id}/viewer-data` | technicien (own site) and above |
| Search | `/recherche` | authenticated |
| Responsable | `/responsable/dispatch`, POST `/responsable/dispatch/{id}/assign`, `/responsable/machines/{id}/interventions/new`, POST `/responsable/faults/{id}/close|reopen`, POST `/responsable/schedules/{id}/postpone` | responsable and above |
| Responsable pages | `/machines/{id}/fiche` (GET, POST), `/maintenance-plan`, `/indicateurs`, `/indicateurs.csv` | responsable and above (a technicien gets 403) |
| Admin | `/admin/**`: CRUDs for fault, intervention, machine, machine-model, machine-module, maintenance-schedule, maintenance-task, notification, part, site, user; `/admin/model/{id}/detail`, `duplicate`, `export`, `import`, POST `import/confirm`, POST `regenerate`; `/admin/maintenance-plan`, `/admin/stats-data` | ROLE_ADMIN |

## Entities (à vérifier: phase 4-5 fields)
User (site, roles, fullName), Site (name, city, latitude, longitude), MachineModel, Machine, MachineModule (parent), Part, MaintenanceTask, MaintenanceSchedule (`archivedAt`), Fault (module, part, symptom, `submissionToken`), Intervention (fault xor schedule, technician nullable, `startedAt`, lock-out and checklist progress, assignment tracking), InterventionPart, Notification, and a postponement record for postponed tasks. List the exact classes with `Get-ChildItem src\Entity -Name`.

## Key services and files
`MaintenancePlanner`, `FaultWorkflow`, `MachineScheduleGenerator` + `MachineScheduleListener`, `CatalogCsvService`, catalog duplicator, `CsvCellEscaper`, `KpiCalculator` (pure) + `AdminStatsProvider`, `QrCodeService`, `SiteMapDataProvider` (`canViewSite`), `ModuleHealthProvider`, controllers `FaultController`, `MachineFicheController`, `MachineViewerController`, `KpiController`, `SearchController`, `MapController`, `SiteController`, `MachineController`, `NotificationController`, `Admin/MachineModelCrudController`. Intervention and dispatch services of phases 4-5: à vérifier.

## Migrations (`ls migrations`)
`Version20260810200829` (initial schema), `20261108110609`, `20261108110837`, `20261108112255` (base CMMS: sites, modules, parts, tasks, schedules, notifications; à vérifier), `20261113123000` (multi-model, irreversible), `20261113132646`, `20261113203352`, `20261113205742` (phases 2-5; à vérifier), `20261113210000` (fault submission token), `20261115130000` (site latitude/longitude). The real database `roltech_gmao` may be behind: apply them via the finalization procedure below.

## Catalog CSV format
UTF-8 with BOM, delimiter `;` (`,` also detected), max 1 MB and 5,000 rows, exact header:
`record_type;module_code;parent_module_code;name;part_number;quantity;description;action;frequency_days;frequency_hours;procedure;data_status`
- `module`: `module_code`, `name` required, `parent_module_code` optional (defined earlier in the file or existing in the model; loops rejected).
- `part`: `module_code`, `name` required; `part_number`, `quantity` optional.
- `task`: `module_code` (or empty for a model-level task), `action` required, at least one positive frequency; `procedure` steps separated by ` | `.
- `data_status`: `confirmed` or `demo` (empty = `demo`).
- Export prefixes a single quote to cells starting with `=`, `+`, `-`, `@`; import removes only that quote.
- Upsert, never delete: modules by (model, code); parts by (module, part_number) or (module, name); tasks by (model, module, action). Preview before any write; confirm re-validates the raw CSV from the session (one-time nonce, hash, 15 min expiry, CSRF, single transaction).

## KPI formulas
Window: default last 12 months, max 5 years; invalid dates fall back to the default. Availability per machine: denominator from `max(window start, installation date)` to window end; downtime only from `critique` faults, interval `[reportedAt, resolution)` where resolution is the latest end of a finished intervention (still-open faults run to the window end; faults closed without a finished intervention add no downtime and are flagged `resolution_sans_horodatage`); overlapping intervals merged; clamped 0-100%; "n/d" when the denominator is zero. Mean repair time: curative finished interventions only. Months grouped in PHP with `Africa/Tunis`. Limitations: moyenne/faible faults and planned maintenance are not downtime.

## 3D viewer contract
Element `[data-rtn-viewer]` with `data-url` (`/parc/{id}/viewer-data`). JSON: `{machine, model:{code,name,family,dataStatus,maxLoadKg}, modules:[{id,code,name,parentId,dataStatus,health,nextTask,openFaults,parts:[...]}]}`. Entries in `importmap.php`: `viewer`, `viewer-panel`, `map`, `three`, OrbitControls, Leaflet. Per-model configs in `assets/viewer/models/<code>.config.js` (shape `{modelCode, modules:{code:{shape,label,color,position,explodeAxis,explodeDistance,focus}}}`), loader `assets/viewer/configs.js` with generic fallback. Add a model: create the config and add it to the loader map. `.glb` is not supported.

## UI system and hooks
Tokens, components and motion live in `assets/styles/rtn-ui.css` (dark Nord, contrast-checked); icons are the inline macro `_partials/icons.html.twig`; the shell is `shell.html.twig` + `_partials/nav.html.twig` + `assets/ui.js`. The login page is separate (`login.css`).
Hooks that JS and tests depend on (do not rename): `[data-rtn-viewer]`, `data-url`, `data-machine-id`, `data-intervention-id`, `data-qr-url`, `data-sites`, `data-geojson-url`, `data-can-see-timeline`, `data-rtn-*` attributes of the viewer panel, `#rtn-map`, `#rtn-bell`, `#rtn-kpi-data`, `#rtn-kpi-charts-root`, `#rtn-fault-viewer-collapsible`, `#rtn-main-content`, `.rtn-qr`, `.rtn-qr-code`, `.rtn-qr-url`, `.rtn-toasts[aria-live]`, `ol.rtn-timeline`, `input[name="fault[_token]"]`, `fault[*]` field names, `data-turbo="false"` where present, the print stylesheet (viewer hidden, QR printed).

## Testing
`php vendor/bin/phpunit --testdox` on a `_test` database. Last recorded run before the 9b fix: 132 tests, 593 assertions, 0 failures, 1 PHPUnit deprecation (the fix added `LoginAssetsTest`). Tools: `node tools/check-contrast.mjs`, `check-css-vars.mjs`, `check-css-collisions.mjs`; `php bin/console lint:twig templates`. Not covered by automated tests: 3D rendering, map interaction, the drawer and focus handling, Chart.js, print layout, responsive breakpoints.

## Known gotchas
1. EasyAdmin `field.customOptions.get(...)`. 2. `addAssetMapperEntry`/`importmap('x')` need an importmap entry. 3. Prefix custom classes with `rtn-` (generic names collided with the debug toolbar and with the login CSS). 4. Turbo breaks JS-built forms after redirects: `/login` has `data-turbo="false"`; maps and 3D pages initialize on `turbo:load` and clean up on `turbo:before-cache`. 5. Do not gate listener setup behind `await import()`. 6. EasyAdmin dark mode ignored plain `:root` overrides (`!important`). 7. `intl` must be enabled. 8. `make:admin:crud` placeholders. 9. EasyAdmin pretty URLs. 10. Never flush inside a Doctrine lifecycle callback (listener collects in onFlush, generates in postFlush). 11. EasyAdmin action links are GET: data-changing actions are POST forms with CSRF. 12. `doctrine:fixtures:load` wipes all tables. 13. An agent's terminal does not inherit the user's `DATABASE_URL`. 14. An undefined `var(--rtn-*)` silently drops the declaration (badges and popup bug). 15. Three.js has no default export. 16. Fixtures do not reset auto-increment, so ids are not 1, 2, 3. 17. AssetMapper cannot import `.geojson`: load it with `fetch()` from a `data-` URL.

## Known limitations and open items
- 3D viewer is schematic only; `.glb` loading not implemented.
- OC-CA1100 checklist frequencies are proposed defaults (the tick marks of the manual's checklist were unreadable): confirm with RolTech.
- Every non-OC-CA1100 model, part and task is invented demo data.
- Font Awesome icons (some panels) and Chart.js load from CDNs: offline, icons/charts degrade.
- `composer audit` reported 23 advisories in Symfony/EasyAdmin packages (pre-existing): update on a separate branch with tests green before and after.
- Intervention search cannot match ids 1-9 (2-character minimum).
- User CRUD has no password editing.
- Duplicate intervention detail routes (`/interventions/{id}` and `/faults/interventions/{id}`): verify object-level authorization on both.
- Phase 4-5 entity and test details unverified in this document.
- `templates/dashboard/placeholder.html.twig` is an unused dead-code candidate.
- No screenshots/browser automation: visual checks are manual.

## Finalization checklist (real database and repository)
1. `git status` clean on `main`; full test suite green on a `_test` database; tools scripts pass.
2. Backup `roltech_gmao` outside the repo. 3. Rehearse on a fresh copy (`roltech_gmao_rehearsal`): BEFORE counts, `migrate`, AFTER counts equal, `doctrine:schema:validate` (a collation-only difference on `machine_module` is the known cosmetic case). 4. Then migrate the real database. 5. Fixtures only on a database that holds nothing but fixture data. 6. Schedule `app:maintenance:check` (README). 7. Check repository visibility (the confidential files are tracked) before any push, and ensure no `.sql` or `.env.local` is tracked.

## Working conventions
Several AI tools touched this repository (Aider, Kilo, Sylix, Copilot, Cursor, Trae, Antigravity): read the actual files before editing. Verify asset and viewer fixes with the rendered HTML and the browser Console, not code review alone. Reports state which requirements lack a test.

## Audit checklist (for the next maintainer)
`git status`; `Get-ChildItem src\Entity,src\Service,src\Controller,src\Repository,tests -Recurse -Name`; `php bin/console debug:router`; `ls migrations`; full PHPUnit run; `doctrine:schema:validate` on the target database; read `config/packages/security.yaml` `access_control`; update the "à vérifier" items of this file.