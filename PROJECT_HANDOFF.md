# RolTech GMAO — Project Context & Handoff

> Purpose: lets any AI tool or developer continue this project without re-deriving past decisions. This file was regenerated when work moved from GitHub Copilot to another tool. It was written from the task specifications and reports, NOT from a fresh read of every file. Verify against the code (see "Audit checklist").

## What this project is
Student internship project (ESPRIT, 3rd year computer engineering) for **Roller Technology Systems Industry (RolTech)**, Tunis. A web CMMS ("GMAO simplifiée") for about 40 machines on several sites in Tunisia (Sousse, Béja, Jendouba, Tunis), running 24h/day: machine fiche (serial number, model, site, status), preventive maintenance plans by module and part, automatic and "predictive" triggering, automatic work orders to technicians, fault declaration with notifications, role dashboards.

Internship sprints: S1 cahier des charges + UML, S2 MySQL design, S3 core modules + functional tests, S4 dashboard/indicators + docs + report.
Two repos: `ROLTECH_GMAO` (this one, code) and `ROLTECH_STAGE_DOCS` (MkDocs site on GitHub Pages: cahier des charges, UML, meeting notes). The docs repo is public: never put manual-derived content there.

## Stack
Symfony 7 (webapp skeleton), Twig, Doctrine ORM + Migrations, MySQL/MariaDB, EasyAdminBundle 5, AssetMapper (no npm build), Symfony UX Turbo, Chart.js (lazy CDN import, the only CDN exception), PHPUnit.
Dev environments: Windows (XAMPP, MariaDB 10.4.32, PowerShell, phpMyAdmin) and Linux (Ultramarine/Fedora, native MariaDB, zsh). Give commands for the right shell. `serverVersion` is set per machine in `DATABASE_URL` (`.env.local`), not in `doctrine.yaml`.

## Databases and environment
- `roltech_gmao`: main database. May NOT yet have all migrations applied; check before use.
- `roltech_gmao_copy`: copy used to test migrations and for manual testing; the schema and fixtures were loaded there.
- `roltech_gmao_test`: PHPUnit (`dbname_suffix: _test`). Create once per machine: `php bin/console doctrine:database:create --env=test` and `doctrine:schema:create --env=test`; recreate after schema changes.
- `DATABASE_URL` can be overridden per shell: `$env:DATABASE_URL="mysql://root:@127.0.0.1:3306/roltech_gmao_copy?serverVersion=10.4.32-MariaDB"` (PowerShell) / `export DATABASE_URL=...` (zsh). Always verify with `php bin/console doctrine:query:sql "SELECT DATABASE()"`.
- Backups on Windows: `C:\xampp\mysql\bin\mysqldump.exe -u root --result-file=backup.sql <db>` (do not use `>` in PowerShell 5.1, it writes UTF-16). To restore: `cmd /c "C:\xampp\mysql\bin\mysql.exe -u root <db> < backup.sql"`.
- `doctrine:migrations:status` once failed because of a metadata-table mismatch (`doctrine_migration_versions.executed_at`); fixed by `sync-metadata-storage` plus `ALTER TABLE doctrine_migration_versions CHANGE executed_at executed_at DATETIME DEFAULT NULL` on the affected database. If `make:migration` is blocked, write the migration by hand from `doctrine:schema:update --dump-sql` (never `--force` outside `--env=test`).
- Collations: `machine_module` is `utf8mb4_general_ci` (legacy), everything else `utf8mb4_unicode_ci`. Cross-table text comparisons in migrations need an explicit `COLLATE utf8mb4_unicode_ci` (a migration already failed on this).
- PHP `intl` extension must be enabled (EasyAdmin dates). XAMPP MySQL: `root`, no password, started manually.

## Test accounts (fixtures)
`admin@roltechsystems.com`, `karim.technicien@roltechsystems.com`, `sami.technicien@roltechsystems.com`, `malek.responsable@roltechsystems.com`. Original passwords were `admin1234`, `tech1234` (both technicians), `resp1234`. A tool once reported `roltech1234`: read the real values in `src/DataFixtures/AppFixtures.php` before trusting any of these. Technicians and the responsable are assigned to sites.

## Done so far (phases 1-5)
**Original base (S1-S3 tooling):** login with cosmetic role picker and role-based redirect, EasyAdmin dashboard (Nord theme), badge templates, Table/Grille toggle, charts via `AdminStatsProvider`, basic Technicien/Responsable dashboards, notifications (list, bell, mark as read), `MaintenancePlanner` + `app:maintenance:check`, `FaultWorkflow`, CRUDs for sites/modules/parts/tasks/schedules/notifications, `/maintenance-plan` (responsable) and `/admin/maintenance-plan`.

**Phase 1: multi-model catalog.** `MachineModel` (unique code), `Machine.machineModel`, modules unique per (model, code), tasks per model, `dataStatus` `confirmed|demo` on models/modules/parts/tasks, `Part.partNumber` nullable, `MaintenanceSchedule.archivedAt`. `MachineScheduleGenerator` (idempotent) driven by `MachineScheduleListener` (onFlush collects, postFlush generates and archives). Cross-model fault validation. Migration `Version20261113123000` (irreversible `down()`; creates `OC-CA1100` confirmed and placeholder `AUTRE`). Fixtures: seven models, 23 machines (8 OC-CA1100 + 15 demo) per `docs/roltech-product-families.md`.

**Phase 2: catalog admin.** Shared status badge component. `MachineModelCrudController`: detail page (`/admin/model/{id}/detail`), duplicate (clones nested modules, parts, tasks as `demo`, null part numbers, no machines/schedules), export, import with preview and confirm, regenerate schedules, deletion refusal when machines or schedules (even archived) reference the model, model and family filters (custom joined filters). `CatalogCsvService`.

**Phase 3: fault declaration.** `/faults/new` (prefill with `?machine=<id>`), visibility by site (technicien) or grouped by site (responsable/admin), cross-site submission 422, module/part filtered by the machine's model, demo badge, confirmation page `/faults/{id}/confirmation` (owner only, 403 otherwise), `/faults` ("Mes pannes déclarées"), `/faults/interventions/{id}`, toasts in `base.html.twig`, inline 422 errors, double-submit protection (`Fault.submissionToken`, unique, migration `Version20261113210000`). Root cause of the original "does nothing" bug: never reported by the previous tool; flash messages were not rendered globally and success redirected back to the form.

**Phase 4 (technicien workflow) and phase 5 (responsable tools): completed per the user, details unverified.** As specified: `InterventionPart`, lock-out confirmation and procedure-checklist progress stored on Intervention, start/finish transitions with required comment, "Je ne peux pas intervenir" (back to dispatch with reason, responsables notified), curative completion resolving the fault and notifying reporter and responsable, my-interventions page, "Tout marquer comme lu"; dispatch board with assign/reassign, manual intervention creation, machine fiche editing and timeline, close/reopen fault, postponement of a due task with reason (logged), all POST + CSRF with object-level authorization. The exact entities, fields, routes and migrations: see the Audit checklist.

## Migrations present (verify with `ls migrations/`)
`Version20261108110609`, `Version20261108110837`, `Version20261108112255` (base schema, earlier work), `Version20261113123000` (multi-model), `Version20261113210000` (fault submission token), plus any created in phases 4 and 5 (unknown names).

## Audit checklist (do this first, read-only)
- List entities, routes (`php bin/console debug:router`), migrations, services and tests. Run the full PHPUnit suite and record tests/assertions/failures. The last known counts were about 36 tests after phase 2, then more in phases 3-5 (exact total unknown). Functional tests need the `_test` schema current.
- Confirm which of these exist: phase 4 and 5 entities and routes (list them in this file afterwards), toasts, dispatch board, postponement logging, object-level authorization tests (technicien A vs B), CSRF tests on every mutating route.
- Run `doctrine:schema:validate` on the copy and `doctrine:migrations:status`.
- Read `AppFixtures.php` for the real test passwords.
- Update this file with what you found.

## Catalog CSV format (import and export)
UTF-8 with BOM, delimiter `;` (the importer also detects `,`), max 1 MB, max 5,000 data rows, exact header:
`record_type;module_code;parent_module_code;name;part_number;quantity;description;action;frequency_days;frequency_hours;procedure;data_status`
- `record_type`: `module`, `part` or `task`.
- module: `module_code`, `name` required; `parent_module_code` optional (must be defined earlier in the file or exist in the model; loops rejected).
- part: `module_code`, `name` required; `part_number`, `quantity` optional.
- task: `module_code` (or empty for a model-level task), `action` required, at least one positive frequency; `procedure` steps separated by ` | `.
- `data_status`: `confirmed` or `demo`; empty = `demo`.
- Export prefixes a single quote to cells starting with `=`, `+`, `-`, `@`; import removes only that leading quote.
- Upsert, never delete: modules by (model, module_code); parts by (module, part_number) or (module, name) when no part number; tasks by (model, module, action). Preview before any write; the confirm step re-validates the raw CSV from the session (one-time nonce, hash, 15 min expiry), CSRF-checked, single transaction. Warning when `confirmed` rows go into a `demo` model.

## Key decisions (do not undo)
- Status/category/severity/type are free-text strings with a fixed vocabulary (AGENTS.md rule 9).
- Authorization comes only from DB roles, never from the login role picker.
- Demo data for non-OC-CA1100 models is deliberately invented and always badged; it must never be presented as RolTech data. Part numbers `DEMO-<MODEL>-<MODULE>-<nn>`.
- "Predictive" = usage thresholds (80%/100%) + fault-frequency alert, nothing more.
- Maintenance frequencies from the manual's checklist are proposed defaults to confirm with RolTech (the PDF's tick marks were unreadable); they are editable data.
- The 15 kg hook limit is OC-CA1100 only (`MachineModel.maxLoadKg`).
- Schedules of a previous model are archived, never deleted.
- Manual-derived docs are confidential (AGENTS.md rule 19). Use privacy settings on any AI tool; consider excluding `docs/` from indexing.

## Known gotchas
1. EasyAdmin `field.customOptions.get('key', default)`, never property access.
2. `addAssetMapperEntry()` needs an importmap entry; use `addCssFile()`/`addJsFile()` for plain files.
3. Prefix custom CSS classes with `rtn-` (generic names collided with the debug toolbar).
4. Turbo breaks JS-built forms after redirects: `/login` has `data-turbo="false"`; use `turbo:load` or `data-turbo="false"` for pages with maps and 3D scenes.
5. Do not gate listener setup behind `await import()`.
6. EasyAdmin dark mode ignored plain `:root` overrides; `!important` on both `:root` and `.ea-dark-scheme`.
7. `intl` must be enabled in `php.ini`.
8. `make:admin:crud` generates placeholder fields; rewrite `configureFields()`.
9. EasyAdmin 5 pretty URLs (`/admin/fault`).
10. Doctrine lifecycle: never flush inside a lifecycle callback; the schedule listener collects in onFlush and generates in postFlush with a guarded follow-up flush.
11. EasyAdmin action links are GET: data-changing actions must be POST forms with CSRF.
12. `doctrine:fixtures:load` wipes all tables.
13. A tool's terminal does not inherit the user's shell variables: set `DATABASE_URL` in the same session and verify with `SELECT DATABASE()` before any write.

## Remaining work
See `docs/REMAINING_PHASES.md` (phases 6-9). Also open: User CRUD has no password editing; some phase-3 requirements had no tests (symptom causes rendering, next-steps text, toast categories; a later step asked for them); exact phase 4/5 test coverage unknown.

## Working conventions
- Several AI tools have touched this repo (Aider, Kilo, Sylix, Copilot, now another). Read the actual files before editing; do not assume this file is complete.
- Before writing CSS or Twig touching EasyAdmin internals, grep `vendor/easycorp/easyadmin-bundle` for real names.
- Verify asset fixes with a request against the rendered HTML, not code review alone.
- Reports state which requirements lack a test. A change is not done until the full suite passes on `roltech_gmao_test`.