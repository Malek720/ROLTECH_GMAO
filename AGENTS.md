# RolTech GMAO — agent instructions

Symfony 7 webapp (Twig, Doctrine, MySQL/MariaDB, EasyAdminBundle 5, AssetMapper).
Internship CMMS for about 40 machines on sites in Tunisia (Sousse, Béja, Jendouba, Tunis...), running 24h/day. Mostly RolTech overhead conveyors of several models, plus demo models (a press, a compressor) that prove the catalog is generic.
Roles: ROLE_ADMIN / ROLE_RESPONSABLE / ROLE_TECHNICIEN. UI text in French, code and DB names in English.
Full context and current state: `PROJECT_HANDOFF.md`. Remaining work: `docs/REMAINING_PHASES.md`. Read both before changing anything.

## First thing in any new session
1. Read this file, `PROJECT_HANDOFF.md` and `docs/REMAINING_PHASES.md`.
2. Run `git status` and `git log --oneline -15`.
3. Check which database you are connected to (see "Database safety") before running any console command that touches data.
4. If asked to continue the phases, audit the repository first (read-only) and report what exists; do not trust the handoff blindly, it was written without seeing every file.

## Data sources (in `docs/`, see rule 19)
- `oc-ca1100-data.md`: REAL data for the OC-CA1100 (modules, parts, checklist, procedures, troubleshooting). Confidential.
- `roltech-product-families.md`: the seven models, the demo (invented) structure of every model except OC-CA1100, and the demo fleet.
- `LEONI_2025v02.pdf`: the original manual, reference only. Confidential.
Never invent data for OC-CA1100. Data for the other models is intentionally invented (demo) and must be labelled as such (rules 27-30).

## Domain model
- Site -> has many Machine and Users (technicians/responsables belong to a site; latitude/longitude are added in phase 6).
- MachineModel (code, name, family, maxLoadKg nullable, dataStatus `confirmed|demo`) -> has many MachineModule and MaintenanceTask. New models are data, never code.
- Machine (serial number, machineModel, site, zone in `location`, status, operating hours).
- MachineModule (per model, codes M1..Mn, nestable, dataStatus) -> has many Part (part number nullable, description, quantity nullable, dataStatus).
- MaintenanceTask = template per model (module or part, action, frequency in days and/or hours, optional procedure checklist, dataStatus).
- MaintenanceSchedule = a task applied to one machine (last done date/hours, next due, state, `archivedAt`).
- Fault (module, part, symptom, `submissionToken` for idempotency) -> has many Intervention. An Intervention comes from a Fault (curative) or from a MaintenanceSchedule (preventive), never both, never neither.
- InterventionPart (intervention, part, quantity). Notification (recipient, message, type, createdAt, readAt, link).
- Phase 4/5 additions (checklist progress, lock-out confirmation, postponements, assignment records): verify the exact entities and fields in `src/Entity` and migrations; the handoff lists what was specified.

## Layout
- `src/Entity`, `src/Repository`
- `src/Controller` (FaultController, MachineFicheController, NotificationController, role dashboards, MaintenancePlan) and `src/Controller/Admin` (DashboardController with Theme API and `configureAssets`, `*CrudController`, `MachineModelCrudController` with catalog actions, StatsApiController)
- `src/Service`: `MaintenancePlanner`, `FaultWorkflow`, `MachineScheduleGenerator`, `CatalogCsvService`, catalog duplicator, `AdminStatsProvider` (single source of chart data)
- `src/EventListener/MachineScheduleListener.php` (onFlush/postFlush schedule sync)
- `src/Command`: `app:maintenance:check`
- `src/Security`, `config/packages/security.yaml`
- `templates/` (security, admin, admin/field, dashboard, fault, notification, shared status badge component)
- `assets/` (admin-overrides, login, styles, later `viewer/` and `models/`), `docs/`, `migrations/`, `tests/`

## Database safety (read before running anything)
- Three databases may exist: `roltech_gmao` (main), `roltech_gmao_copy` (copy used for testing migrations and manual tests), `roltech_gmao_test` (PHPUnit, via `dbname_suffix: _test`).
- Do not write to `roltech_gmao` without a verified backup and the user's explicit approval. Do not run `doctrine:fixtures:load` on any database that holds data the user wants to keep (it wipes all tables).
- `DATABASE_URL` is set per machine in `.env.local` or in the shell session. Before any write, run `php bin/console doctrine:query:sql "SELECT DATABASE()"` and stop if it is not the database you intend. Do not edit `.env`. Do not create `.env.local` yourself; tell the user what to put in it.
- Windows/XAMPP is MariaDB 10.4.x. `serverVersion` lives only in `DATABASE_URL`, never hard-coded in `doctrine.yaml`.
- Every migration is tested on a copy first (rule 35). `migrate --dry-run` does not execute, so it does not prove a migration works.

## Rules (each one is a bug we already hit, or a project decision)
1. The role picker on the login page is cosmetic. Authorization comes only from the user's roles in the DB.
2. Prefix every custom CSS class with `rtn-`. Generic names (.content, .panel) collided with EasyAdmin/profiler CSS and made content invisible.
3. Before writing CSS/Twig that touches EasyAdmin internals, grep `vendor/easycorp/easyadmin-bundle` for the real variable/class names. Do not guess them.
4. In EasyAdmin field templates use `field.customOptions.get('key', default)`, never `field.customOptions.key`.
5. Load plain assets with `Assets::addCssFile()` / `addJsFile()`. `addAssetMapperEntry()` needs an `importmap.php` entry.
6. `/login` keeps `data-turbo="false"`; Turbo broke the JS-built form after a failed login.
7. In JS, never gate listener setup behind `await import(...)`. Chart.js is a lazy dynamic import from a CDN.
8. EasyAdmin 5 uses pretty URLs (`/admin/fault`), not `?crudControllerFqcn=`.
9. Status/category/severity/type are free-text strings and matched exactly:
   - machine status: `en service|en panne|en maintenance`
   - fault severity: `critique|moyenne|faible`; fault status: `ouverte|en cours|resolue`
   - intervention type: `preventive|curative`; intervention status: `planifiee|en cours|terminee`
   - maintenance schedule state: `ok|proche|echue` (proche = 80% of the interval, echue = 100%)
   - notification type: `panne|intervention|maintenance`
   - data status (model, module, part, task): `confirmed|demo`
   If you add or change a value, update this list and `PROJECT_HANDOFF.md` in the same change.
10. `make:admin:crud` generates placeholder fields; always rewrite `configureFields()` against the real entity.
11. Secrets live in `.env.local` only, never in `.env`. Dev happens on Windows (PowerShell, XAMPP) and Linux (zsh): give commands for the right shell.
12. Maintenance frequencies are data, not code (MaintenanceTask rows, fixtures, CRUD, CSV import). The OC-CA1100 checklist frequencies are "to confirm" with RolTech, so they must stay editable.
13. Operating hours: machines run 24h/day, so hours derive from the commissioning date plus a manual correction field. 1 week = 168 h, 1 month = 720 h, 1 quarter = 2160 h.
14. `app:maintenance:check` must be idempotent: never two open preventive interventions for the same schedule.
15. Automatic technician assignment: same site as the machine, fewest open interventions. If none is available, leave the intervention unassigned and notify the site's responsables. A responsable can always reassign.
16. "Predictive" means usage-based thresholds (80%/100%) plus a fault-frequency alert (a machine or module with more faults than the fleet average over a recent period). No machine learning. Do not describe it as more than that.
17. Every intervention screen shows the safety rules: lock out electrical and pneumatic supply before work, qualified staff only, original or ROLTECH-validated parts only. Starting an intervention requires ticking the lock-out checklist.
18. Business logic (due detection, assignment, notification creation, schedule generation, CSV import) lives in services so it can be unit tested. Controllers and commands stay thin.
19. Files in `docs/` derived from ROLTECH manuals (`oc-ca1100-data.md`, the PDF) are confidential. Never copy their text or drawings into the README, the public docs repo, or any public page. Planche references appear as text only.
20. A MaintenanceSchedule starts counting from the date it is created (last done = creation date and the machine's hours at that date), never from `installation_date`.
21. Every user action that changes data gives visible feedback: a success flash or confirmation page, or inline French validation errors. A successful form redirects (POST/redirect/GET), an invalid form returns HTTP 422 so Turbo renders it. Flash messages are rendered only in `base.html.twig`.
22. New front-end libraries (Leaflet, Three.js) are installed locally through AssetMapper importmap, never from a CDN. The only CDN exception is Chart.js (rule 7). Pages with maps or 3D scenes must work with Turbo.
23. The 3D viewer is schematic: a shared procedural builder plus a per-model config `assets/viewer/models/<model-code>.config.js` (module code -> mesh, label, focus). Do not invent geometry details for the OC-CA1100 beyond the data file. A real `.glb` can replace a model if its node names equal the module codes. Unknown and non-conveyor models fall back to a generic config built from their modules.
24. Status is never shown by color alone: use the shared status badge component (icon + color + text) for machine status, fault severity, intervention status, schedule state and data status.
25. Technicien pages are mobile-first with large touch targets. Responsable pages must stay usable on tablets.
26. Visibility by role: responsable and admin see all sites, a technicien sees only their own site on the map and in machine lists. Enforce this in queries (services/repositories), not only in templates.
27. The app supports any number of machine models. Modules, parts and tasks belong to a MachineModel, and a machine's modules and tasks always come from its model. Never hardcode a model code, module code or part number in logic.
28. Only OC-CA1100 data is `confirmed`. Every other model, module, part and task is `demo`. Demo data always shows the "Données de démonstration" badge, its part numbers use `DEMO-<MODEL>-<MODULE>-<nn>`, and exports include the data status so demo data is never mistaken for RolTech data. A missing part number displays "référence à confirmer"; a missing load limit displays "charge max. à confirmer".
29. Do not copy OC-CA1100 part numbers or its 15 kg per hook limit to other models. `maxLoadKg` is per model.
30. Real data enters through the admin catalog: model CRUD, "Dupliquer ce modèle" (clones into a new `demo` model), CSV import/export (format in `PROJECT_HANDOFF.md`).
31. Adding or changing tasks on a model never deletes schedules or history. "Régénérer les plannings" only creates missing schedules.
32. A machine's active schedules come only from its current model. Schedules from a previous model are archived (`archivedAt`), never deleted, and ignored by the planner, plan pages and dashboards. Schedule generation runs from a Doctrine listener through `MachineScheduleGenerator`, never only from a controller.
33. A Fault's module and part must belong to the model of the Fault's machine. Validated on the server, not only by filtering the form.
34. CSV exports escape formula-leading characters (`=`, `+`, `-`, `@`). CSV imports never write before the admin confirms a preview, never delete records, run in a single transaction, and the confirm step re-validates the raw CSV against the current database.
35. Every migration is tested on a copy of the database before the real one. Cross-table text comparisons in migrations use an explicit `COLLATE utf8mb4_unicode_ci`. New tables use `utf8mb4_unicode_ci` (`default_table_options`). `machine_module` is a legacy `utf8mb4_general_ci` table and is left alone unless asked.
36. Database-touching tests run only against `roltech_gmao_test` and isolate each test. Ask before adding any Composer or npm dependency, and state its license and size.
37. Every route that changes data is POST-only with a CSRF token check. GET only renders. EasyAdmin actions that change data are POST forms, not links. Admin-only routes carry `#[IsGranted('ROLE_ADMIN')]` in addition to `access_control`.
38. Object-level authorization: a technicien opens and changes only interventions assigned to them and sees only faults they reported or their site's; enforce with a voter or scoped query, return 403/404 otherwise. Test "technicien A cannot open technicien B's intervention".
39. Reports say plainly which requirements have no test. Never claim a requirement is covered unless a named test covers it.

## How to work
- Work in small phases. Before editing, list the files you will create or change.
- No migrations or fixtures against the main database. For schema changes: state the exact change, generate with `make:migration` (if blocked by metadata errors, write the migration by hand and say so), show the SQL, test on the copy.
- After each phase: PHPUnit totals (tests, assertions, failures, errors), new test names with the requirement each covers, requirements with no test, URLs to open, test accounts, a manual test script. Then stop.
- Keep `AGENTS.md` and `PROJECT_HANDOFF.md` current when you add entities, statuses, formats or conventions.

## Not done yet
Phases 6-9 (see `docs/REMAINING_PHASES.md`): Tunisia map and navigation, 3D viewer, KPI page / global search / QR codes, final mobile-accessibility-documentation pass. Still outstanding: User CRUD has no password editing.