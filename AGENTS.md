# RolTech GMAO — agent instructions

Symfony 7 webapp (Twig, Doctrine, MySQL/MariaDB, EasyAdminBundle 5, AssetMapper).
Internship CMMS for about 40 machines on sites in Tunisia (Sousse, Béja, Jendouba, Tunis, ...), running 24h/day. Mostly RolTech overhead conveyors of several models, plus demo models (a press, a compressor) that prove the catalog is generic.
Users have roles ROLE_ADMIN / ROLE_RESPONSABLE / ROLE_TECHNICIEN. UI text in French, code and DB names in English.
Longer background: `PROJECT_HANDOFF.md` (open it only when you need it).
Data sources (in `docs/`, see rule 19):
- `oc-ca1100-data.md`: REAL data for the OC-CA1100 (modules, parts, checklist, procedures, troubleshooting). Confidential.
- `roltech-product-families.md`: the seven models, the demo (mockup) structure of every model except OC-CA1100, and the demo fleet.
- `LEONI_2025v02.pdf`: the original manual, reference only. Confidential.
Never invent data for OC-CA1100. Data for other models is intentionally invented (demo) and must be labelled as such (rules 27-30).

## Domain model
- Site -> has many Machine and Users (technicians/responsables belong to a site).
- MachineModel (code, name, family, maxLoadKg nullable, dataStatus `confirmed|demo`) -> has many MachineModule and MaintenanceTask. New models are added as data, never as code.
- Machine (serial number, machineModel, site, zone in `location`, status, operating hours).
- MachineModule (per model, codes M1..Mn, nestable, dataStatus) -> has many Part (part number nullable, description, quantity nullable, dataStatus).
- MaintenanceTask = template per model (module or part, action, frequency in days and/or hours, optional procedure checklist, dataStatus).
- MaintenanceSchedule = a task applied to one machine (last done date/hours, next due, state).
- Fault -> has many Intervention. An Intervention comes from a Fault (curative) or from a MaintenanceSchedule (preventive), never both, never neither.
- InterventionPart (intervention, part, quantity). Notification (recipient, message, type, createdAt, readAt, link).

## Layout
- `src/Entity`: User, Site, MachineModel, Machine, MachineModule, Part, MaintenanceTask, MaintenanceSchedule, Fault, Intervention, InterventionPart, Notification (all define `__toString()`, EasyAdmin needs it)
- `src/Controller/Admin`: DashboardController (Theme API, configureAssets), *CrudController, StatsApiController
- `src/Service/AdminStatsProvider.php`: single source of chart data
- `src/Service/`: maintenance, assignment, schedule generation, catalog import/export (keep business logic here, not in controllers, commands or templates)
- `src/Command/`: `app:maintenance:check`
- `src/Security` + `config/packages/security.yaml`: login redirect by real roles
- `templates/` (security, admin, admin/field, dashboard, fault, notification), shared Twig components/macros (status badge)
- `assets/admin-overrides.css|js`, `assets/login.js`, `assets/styles/login.css`, `assets/viewer/` (3D viewer: shared builder + `models/<model-code>.config.js`), `assets/models/` (optional `.glb` files)
- `docs/`: data sources above

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
12. Maintenance frequencies are data, not code: keep them in MaintenanceTask rows (fixtures, CRUD, CSV import), never hardcode intervals in services. The OC-CA1100 checklist frequencies are still marked "to confirm" in the data file, so they must stay editable.
13. Operating hours: machines run 24h/day, so hours derive from the commissioning date plus a manual correction field. 1 week = 168 h, 1 month = 720 h, 1 quarter = 2160 h.
14. `app:maintenance:check` must be idempotent: never two open preventive interventions for the same schedule.
15. Automatic technician assignment: same site as the machine, fewest open interventions. If none is available, leave the intervention unassigned and notify the site's responsables. A responsable can always reassign.
16. "Predictive" means usage-based thresholds (80%/100%) plus a fault-frequency alert (a machine or module with more faults than the fleet average over a recent period). No machine learning. Do not describe it as more than that.
17. Every intervention screen shows the safety rules: lock out electrical and pneumatic supply before work, qualified staff only, original or ROLTECH-validated parts only. Starting an intervention requires ticking the lock-out checklist.
18. Business logic (due detection, assignment, notification creation, schedule generation, CSV import) lives in services so it can be unit tested. Controllers and commands stay thin.
19. Files in `docs/` derived from ROLTECH manuals (`oc-ca1100-data.md`, the PDF) are confidential. Never copy their text or drawings into the README, the public docs repo, or any public page. Planche references appear as text only.
20. A MaintenanceSchedule starts counting from the date it is created (last done = creation date and the machine's hours at that date), never from `installation_date`. Total operating hours on the fiche can still derive from `installation_date`.
21. Every user action that changes data gives visible feedback: a success flash or confirmation page, or inline French validation errors. A successful form redirects (POST/redirect/GET), an invalid form returns HTTP 422 so Turbo renders it. Flash messages are rendered only in `base.html.twig`.
22. New front-end libraries (Leaflet, Three.js) are installed locally through AssetMapper importmap, never from a CDN. The only CDN exception is Chart.js (rule 7). Pages with maps or 3D scenes must work with Turbo.
23. The 3D viewer is schematic: a shared procedural builder plus a per-model config `assets/viewer/models/<model-code>.config.js` (module code -> mesh, label, focus). Do not invent geometry details for the OC-CA1100 that are not in the manual. A real `.glb` can replace a model if its node names equal the module codes. Unknown models and non-conveyor models fall back to a generic config built from their modules.
24. Status is never shown by color alone: use the shared status badge component (icon + color + text) for machine status, fault severity, intervention status, schedule state and data status.
25. Technicien pages are mobile-first with large touch targets. Responsable pages must stay usable on tablets.
26. Visibility by role: responsable and admin see all sites, a technicien sees only their own site on the map and in machine lists. Enforce this in queries (services/repositories), not only in templates.
27. The app supports any number of machine models. Modules, parts and tasks belong to a MachineModel, and a machine's modules and tasks always come from its model. Never hardcode a model code, module code or part number in logic.
28. Only OC-CA1100 data is `confirmed`. Every other model, module, part and task is `demo` (invented for demonstration). Demo data always shows the "Données de démonstration" badge, its part numbers use the pattern `DEMO-<MODEL>-<MODULE>-<nn>`, and exports (CSV, KPI) include the data status column so demo data can never be mistaken for RolTech data. A missing part number displays "référence à confirmer"; a missing load limit displays "charge max. à confirmer".
29. Do not copy OC-CA1100 part numbers or its 15 kg per hook limit to other models. `maxLoadKg` is per model.
30. Real data enters through the admin catalog: model CRUD, "Dupliquer ce modèle" (clones modules, parts and tasks into a new `demo` model), and CSV import/export (the CSV has a `data_status` column, so imported real data can replace demo data). Imports validate every row, show a preview with errors, and run in one transaction. The CSV columns are documented in `PROJECT_HANDOFF.md`.
31. Adding or changing tasks on a model never deletes existing schedules or history. The admin action "Régénérer les plannings" only creates missing schedules.
32. A machine's active schedules come only from its current model. Schedules from a previous model are archived (`archivedAt`), never deleted, and ignored by the planner, the plan pages and the dashboards. Schedule generation runs from a Doctrine listener through `MachineScheduleGenerator`, never only from a controller.
33. A Fault's module and part must belong to the model of the Fault's machine. This is validated on the server, not only by filtering the form.
34. CSV exports escape formula-leading characters. CSV imports never write before the admin confirms a preview, never delete records, and run in a single transaction.

## Not done yet
Tracked in the current task prompt: multi-model catalog (MachineModel, demo seed data, catalog admin, CSV import), fault declaration feedback fix, Tunisia map, 3D viewer, technicien intervention workflow, responsable dispatch board, KPI page. Still outstanding: User CRUD has no password editing.