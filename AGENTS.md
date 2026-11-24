# RolTech GMAO — agent instructions

Symfony 7 webapp (Twig, Doctrine, MySQL/MariaDB, EasyAdminBundle 5, AssetMapper, Turbo).
Internship CMMS for about 40 machines on sites in Tunisia (Sousse, Béja, Jendouba, Tunis), running 24h/day. Mostly RolTech overhead conveyors of several models, plus demo models (a press, a compressor) that prove the catalog is generic.
Roles: ROLE_ADMIN / ROLE_RESPONSABLE / ROLE_TECHNICIEN. UI text in French, code and DB names in English.
Full context, routes, entities, formats and open items: `PROJECT_HANDOFF.md`. Read it before changing anything.

## First thing in any new session
1. Read this file and `PROJECT_HANDOFF.md`.
2. `git status` and `git log --oneline -15`.
3. Check which database you are connected to (see "Database safety") before any console command that touches data.
4. If asked to continue work, audit the repository first (read-only). `PROJECT_HANDOFF.md` marks the parts that were written without reading every file ("à vérifier").

## Data sources (in `docs/`, see rule 19)
- `oc-ca1100-data.md`: REAL data for the OC-CA1100 (modules, parts, checklist, procedures, troubleshooting). Confidential.
- `roltech-product-families.md`: the seven models, the demo (invented) structure of every model except OC-CA1100, and the demo fleet.
- `LEONI_2025v02.pdf`: the original manual, reference only. Confidential.
Never invent data for OC-CA1100. Data for the other models is intentionally invented (demo) and labelled as such (rules 27-30).

## Domain model (details in PROJECT_HANDOFF.md)
- Site (latitude, longitude) -> Machines and Users (technicians/responsables belong to a site).
- MachineModel (code, family, maxLoadKg nullable, dataStatus `confirmed|demo`) -> MachineModule (nestable, codes per model), Part, MaintenanceTask. New models are data, never code.
- Machine (serial number, machineModel, site, zone in `location`, status, operating hours) -> MaintenanceSchedule per task (state, `archivedAt`).
- Fault (module, part, symptom, `submissionToken`) -> Intervention. An Intervention has a Fault (curative) or a MaintenanceSchedule (preventive), never both, never neither.
- InterventionPart, Notification, postponement/assignment records (phases 4-5): see the handoff.

## Layout
- `src/Entity`, `src/Repository`, `src/Controller` (+ `Admin/`), `src/Service`, `src/EventListener` (`MachineScheduleListener`), `src/Command` (`app:maintenance:check`), `src/Security`, `src/DataFixtures`
- `templates/`: `base.html.twig` (assets, toasts), `shell.html.twig` (app shell for all non-admin pages), `_partials/` (nav, icons, breadcrumb, global_search, machine_viewer), `components/status_badge.html.twig`, `security/login.html.twig` (standalone, see rule 41)
- `assets/`: `styles/rtn-ui.css` (design system), `styles/login.css`, `ui.js` (shell), `map.js`, `viewer-page.js`, `viewer-panel.js`, `viewer/` (engine, builder, configs), `kpi.js`, `tunisia.geojson`, `admin-overrides.css|js`
- `tools/`: `check-contrast.mjs`, `check-css-vars.mjs`, `check-css-collisions.mjs`
- `docs/`: data sources and phase notes. `tests/`: PHPUnit (unit and functional).

## Database safety (read before running anything)
- Databases: `roltech_gmao` (main), `roltech_gmao_copy` (copy for manual tests), `roltech_gmao_test` or `<name>_test` (PHPUnit: configured name + `_test`, so the copy's test database is `roltech_gmao_copy_test`).
- Never write to `roltech_gmao` without a verified backup and the user's explicit approval. `doctrine:fixtures:load` wipes every table: never run it on a database holding data the user wants to keep.
- `DATABASE_URL` is set per machine in `.env.local` or in the shell session. Before any write run `php bin/console doctrine:query:sql "SELECT DATABASE()"` and stop if it is not the intended database. An agent terminal does not inherit the user's shell variables: set `$env:DATABASE_URL` in the same command session first. Do not edit `.env`; do not create `.env.local`.
- `serverVersion` lives only in `DATABASE_URL` (XAMPP is MariaDB 10.4.x), never hard-coded in `doctrine.yaml`.
- Every migration is tested on a copy first (rule 35). `migrate --dry-run` does not execute SQL and cannot catch runtime errors.
- Backups (`mysqldump --result-file=...`) are stored outside the repository. Never commit `.sql` dumps or `.env.local`.

## Rules (each one is a bug we already hit, or a project decision)
1. The role picker on the login page is cosmetic. Authorization comes only from the user's roles in the DB.
2. Prefix every custom CSS class with `rtn-`. Generic names collided with EasyAdmin/profiler CSS and with the login styles.
3. Before writing CSS/Twig that touches EasyAdmin internals, grep `vendor/easycorp/easyadmin-bundle` for the real variable/class names. Do not guess them.
4. In EasyAdmin field templates use `field.customOptions.get('key', default)`, never `field.customOptions.key`.
5. Load plain assets with `Assets::addCssFile()` / `addJsFile()` or `asset()`. `addAssetMapperEntry()` and `importmap('entry')` need an `importmap.php` entry.
6. `/login` keeps `data-turbo="false"`; Turbo broke the JS-built form after a failed login.
7. In JS, never gate listener setup behind `await import(...)`. Chart.js is a lazy dynamic import from a CDN.
8. EasyAdmin 5 uses pretty URLs (`/admin/fault`), not `?crudControllerFqcn=`.
9. Status/category/severity/type are free-text strings matched exactly:
   - machine status: `en service|en panne|en maintenance`
   - fault severity: `critique|moyenne|faible`; fault status: `ouverte|en cours|resolue`
   - intervention type: `preventive|curative`; intervention status: `planifiee|en cours|terminee`
   - maintenance schedule state: `ok|proche|echue` (proche = 80% of the interval, echue = 100%)
   - notification type: `panne|intervention|maintenance`
   - data status (model, module, part, task): `confirmed|demo`
   If you add or change a value, update this list and `PROJECT_HANDOFF.md` in the same change.
10. `make:admin:crud` generates placeholder fields; always rewrite `configureFields()` against the real entity.
11. Secrets live in `.env.local` only, never in `.env`. Dev happens on Windows (PowerShell, XAMPP) and Linux (zsh): give commands for the right shell.
12. Maintenance frequencies are data, not code (MaintenanceTask rows, fixtures, CRUD, CSV import). The OC-CA1100 checklist frequencies are proposed defaults to confirm with RolTech, so they must stay editable.
13. Operating hours: machines run 24h/day, so hours derive from the commissioning date plus a manual correction field. 1 week = 168 h, 1 month = 720 h, 1 quarter = 2160 h.
14. `app:maintenance:check` must be idempotent: never two open preventive interventions for the same schedule.
15. Automatic technician assignment: same site as the machine, fewest open interventions. If none is available, leave the intervention unassigned and notify the site's responsables. A responsable can always reassign.
16. "Predictive" means usage-based thresholds (80%/100%) plus a fault-frequency alert. No machine learning. Do not describe it as more than that.
17. Every intervention screen shows the safety rules (lock out electrical and pneumatic supply, qualified staff only, original or ROLTECH-validated parts only). Starting an intervention requires ticking the lock-out checklist.
18. Business logic lives in services so it can be unit tested. Controllers and commands stay thin.
19. Files in `docs/` derived from ROLTECH manuals (`oc-ca1100-data.md`, the PDF), and the real OC-CA1100 part numbers in `AppFixtures.php`, are confidential. Never copy them into the README, the public docs repo, or any public page, and never push them to a public repository.
20. A MaintenanceSchedule starts counting from the date it is created, never from `installation_date`.
21. Every user action that changes data gives visible feedback: a success flash or confirmation page, or inline French validation errors. A successful form redirects (POST/redirect/GET), an invalid form returns HTTP 422. Flash messages are rendered only in `base.html.twig` with `aria-live`.
22. Front-end libraries (Leaflet, Three.js) are installed locally through the AssetMapper importmap, never from a CDN. CDN exceptions that already exist: Chart.js (rule 7) and Font Awesome icons in some panels. Pages with maps or 3D scenes must work with Turbo.
23. The 3D viewer is schematic: a shared procedural builder plus a per-model config `assets/viewer/models/<model-code>.config.js`. Do not invent geometry details for the OC-CA1100 beyond its modules. `.glb` loading is NOT implemented. Unknown and non-conveyor models fall back to a generic config built from their modules.
24. Status is never shown by color alone: use the shared status badge component (icon + color + text).
25. Technicien pages are mobile-first with 44px touch targets. Responsable pages stay usable on tablets.
26. Visibility by role: responsable and admin see all sites, a technicien only their own site (map, machine list, site page, search, viewer data). Enforce it in queries/services, not only in templates.
27. The app supports any number of machine models. Modules, parts and tasks belong to a MachineModel, and a machine's modules and tasks always come from its model. Never hardcode a model code, module code or part number in logic.
28. Only OC-CA1100 data is `confirmed`. Everything else is `demo` and shows "Données de démonstration"; demo part numbers use `DEMO-<MODEL>-<MODULE>-<nn>`; exports include the data status. Missing part number: "référence à confirmer". Missing load limit: "charge max. à confirmer".
29. Do not copy OC-CA1100 part numbers or its 15 kg per hook limit to other models. `maxLoadKg` is per model.
30. Real data enters through the admin catalog: model CRUD, "Dupliquer ce modèle", CSV import/export (format in `PROJECT_HANDOFF.md`).
31. Adding or changing tasks on a model never deletes schedules or history. "Régénérer les plannings" only creates missing schedules.
32. A machine's active schedules come only from its current model. Schedules from a previous model are archived (`archivedAt`), never deleted, and ignored by the planner, plan pages and dashboards. Generation runs from a Doctrine listener through `MachineScheduleGenerator`.
33. A Fault's module and part must belong to the model of the Fault's machine, validated on the server.
34. CSV exports escape formula-leading characters (`=`, `+`, `-`, `@`). CSV imports never write before the admin confirms a preview, never delete records, run in a single transaction, and the confirm step re-validates the raw CSV.
35. Every migration is tested on a copy first. Cross-table text comparisons in migrations use an explicit `COLLATE utf8mb4_unicode_ci`. New tables use `utf8mb4_unicode_ci`. `machine_module` is a legacy `utf8mb4_general_ci` table and is left alone.
36. Database-touching tests run only on a `_test` database and isolate each test (own data, repeatable twice in a row). Ask before adding any Composer or npm dependency, stating license and size.
37. Every route that changes data is POST-only with a CSRF check. GET only renders. EasyAdmin actions that change data are POST forms. Admin-only routes carry `#[IsGranted('ROLE_ADMIN')]` in addition to `access_control` (first match wins: put specific rules before general ones).
38. Object-level authorization: a technicien opens and changes only interventions assigned to them and sees only their site's data; enforce with a voter or scoped query, return 403/404 otherwise. Every route that shows an intervention, a fault or a machine needs it (there are two intervention detail routes: `/interventions/{id}` and `/faults/interventions/{id}`).
39. Reports say plainly which requirements have no test. Never claim a requirement is covered unless a named test covers it.
40. Every `var(--rtn-*)` used in CSS, templates or JS must be defined (an undefined variable silently drops the whole declaration: this made badges unreadable and the map popup transparent). Before merging any UI change run `node tools/check-css-vars.mjs`, `node tools/check-css-collisions.mjs` and `node tools/check-contrast.mjs` (the contrast script only checks token values, it is not a visual test). Never set a background and a text color from unrelated token families.
41. `/login` is standalone: `login.html.twig` must not load `rtn-ui.css` or `ui.js` (their generic class names broke its layout), and its own classes must never collide with `rtn-ui.css` (covered by `LoginAssetsTest` and the collision script). All other non-admin pages extend `shell.html.twig`.
42. 3D viewer contract: the element with `data-rtn-viewer` always carries `data-url` rendered with `path()` (nothing is mounted without it); Three.js is loaded as a namespace (`const THREE = await import('three')`, never `.default`: Three.js has no default export); the endpoint `/parc/{id}/viewer-data` returns a flat module list with `parentId`; events `rtn:viewer-ready`, `rtn:viewer-unsupported`, `rtn:module-selected`. A viewer container must never have zero size.
43. Navigation links in the shell carry `data-turbo="false"` (full page loads); keep hooks listed in `PROJECT_HANDOFF.md` ("Hooks") when restyling.
44. Demo passwords are defined only in `src/DataFixtures/AppFixtures.php` and are not written in documentation.

## How to work
- Work in small steps. Before editing, list the files you will create or change.
- Schema changes: state the exact change, generate with `make:migration` (if blocked by metadata errors, write it by hand from `doctrine:schema:update --dump-sql` and say so), show the SQL, test on the copy.
- After each step: PHPUnit totals (tests, assertions, failures, errors), new tests mapped to requirements, requirements with no test, URLs and accounts to try. Then stop.
- Keep `AGENTS.md` and `PROJECT_HANDOFF.md` current when entities, statuses, formats or conventions change.

## Not done yet
User CRUD has no password editing. 3D viewer is schematic only (no `.glb`). Dependency advisories (Symfony/EasyAdmin packages) are not updated. Intervention search cannot match single-digit ids. Phase 4/5 entity and test details need an audit. See `PROJECT_HANDOFF.md`, "Known limitations".