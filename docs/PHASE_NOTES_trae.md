# Phase 8 notes (branch `trae/phase8`) — work by Trae

`AGENTS.md` and `PROJECT_HANDOFF.md` are owned by the user: this file holds the phase 8 notes instead.
Chunk 8a = KPI formulas + CSV escaper only (no controller, no template, no schema change).

## Chunk 8a — files added/used

- Added `src/Service/CsvCellEscaper.php`, used by `CatalogCsvService` for export escaping and import
  unescaping (identical behaviour; `CatalogCsvServiceTest` still green).
- Added `src/Service/KpiCalculator.php` (pure, plain arrays in/out, no repository, no EntityManager).
- Added KPI loaders (constant number of queries): `MachineRepository::findAllWithSiteAndModel()`,
  `FaultRepository::findReportedUpTo()`, `InterventionRepository::findFinishedUpTo()`.
- Added `AdminStatsProvider::getKpiReport(?string $from, ?string $to)` which resolves the window, loads
  the three datasets and delegates to `KpiCalculator::compute()`.
- Added `tests/Unit/CsvCellEscaperTest.php`, `tests/Unit/KpiCalculatorTest.php`.

## CSV escaping rule

A cell whose first character is `=`, `+`, `-` or `@` is a spreadsheet formula: the export prefixes it with a
single quote (`CsvCellEscaper::escape`). The import strips that quote only when it directly precedes one of
those four characters (`CsvCellEscaper::unescape`), so a legitimate value starting with an apostrophe is kept.
Catalog and KPI exports use the same escaper; UTF-8 BOM (headers only) and `;` delimiter are unchanged.

## KPI formulas

Window: default = last 12 months through today; the user may pick a range; dates that are not a real
`YYYY-MM-DD` day (including rolled-over dates such as `2026-02-31`), or a range that ends before it starts,
fall back to the default; the range is capped at 5 years (the start is clamped to `end - 5 years`).
Months are grouped in PHP with the `Africa/Tunis` timezone, never with database date functions.

Availability per machine (machines run 24h/day):
- Denominator = `window end - max(window start, machine installationDate)`. If that duration is `<= 0`
  the machine shows "n/d" (availability `null`, never a division by zero).
- Downtime comes from `critique` faults only. For each such fault the interval is `[reportedAt, resolution)`
  clipped to the window. Resolution = the latest `endDate` among that fault's `terminee` interventions.
  A fault still `ouverte` or `en cours` runs to the end of the window. A fault `resolue` with no finished
  intervention (closed by a responsable) adds no downtime and is flagged `resolution_sans_horodatage`.
  Intervals whose resolution is before `reportedAt` are ignored.
- Overlapping intervals are merged (union) per machine before summing; availability is clamped to 0-100 %.
- Known limitations: `moyenne` / `faible` faults and planned maintenance (`en maintenance` status, preventive
  interventions) are **not** counted as downtime.

Mean repair time: curative interventions only, status `terminee`, `endDate` inside the window;
duration = `endDate - startedAt`, or `endDate - startDate` when `startedAt` is null. Open interventions excluded.
Mean = total / count, "n/d" when the count is zero.

Faults per site per month: faults whose `reportedAt` is inside the window, grouped by site and month; also
groupable by model. Model rows carry the model `dataStatus` so demo data is never mistaken for RolTech data.

## Notes / limits

- `getKpiReport()` runs exactly three queries (machines, faults, finished interventions) whatever the number
  of machines; there is no query inside a loop.
- The availability table row exposes `openCritiqueFaults` and `faultsWithoutResolutionTimestamp` so the CSV
  can mark `resolution_sans_horodatage`.
- No KPI route, CSV route or template was added in this chunk.

## Chunk 8b — KPI page and CSV export

Chunk 8b adds the presentation layer on top of the 8a calculators. No schema change, no migration, no new
dependency (Chart.js stays the single CDN exception, rule 7).

- Added `src/Controller/KpiController.php`: `GET /indicateurs` (`kpi_index`) and `GET /indicateurs.csv`
  (`kpi_export`). Both carry `#[IsGranted('ROLE_RESPONSABLE')]` and a matching `security.yaml` rule
  (`^/indicateurs`, ROLE_RESPONSABLE). Both read `from`/`to` and delegate to
  `AdminStatsProvider::getKpiReport()`, where `KpiCalculator::resolveWindow()` validates the range once,
  so the page and the CSV always share the same validated window (a bad range falls back to the default
  12-month window). Non-string query values (e.g. `?from[]=`) are ignored.
- Added `templates/kpi/index.html.twig`: GET filter form, the four tables always rendered (availability
  per machine grouped by model with the "n/d" case, mean repair time, faults per site per month, faults
  per model per month), demo/confirmed badges through the shared status badge component (rule 24), French
  empty states, `rtn-` classes and horizontal scroll for narrow screens (rule 25). The chart data is
  embedded as JSON for the optional charts.
- Added `assets/kpi.js`: lazy Chart.js import from the CDN (rule 7). The listeners are registered
  immediately, never behind `await import`. When the CDN is unreachable the charts show a French notice
  and the tables above stay usable, so the page works offline.
- CSV: single file, UTF-8 BOM, `;` delimiter, every cell through `CsvCellEscaper` (rule 34). Columns:
  `type;site;machine;modele;statut_donnees;periode;nombre;valeur;resolution_sans_horodatage`. The
  `statut_donnees` column exports the model data status and `resolution_sans_horodatage` carries the count
  of critical faults closed without a finished intervention.
- Links: an "Indicateurs KPI" button on the responsable dashboard and a `MenuItem` in the EasyAdmin menu.
- Added `tests/Functional/KpiControllerTest.php`.

### Known limits of chunk 8b

- The KPI CSV is a single flat file with a `type` column rather than one file per table; the spec did not
  fix an exact column list, so the columns above are this implementation's choice.
- Charts are an enhancement only: there is no chart-specific test (Chart.js runs in the browser).

## Chunk 8c — global search

Search covers machines (name, serial number, model code/name) and interventions (by id). No schema change,
no migration, no new dependency.

- Added `src/Controller/SearchController.php`: `GET /recherche` (`global_search`), `#[IsGranted('IS_AUTHENTICATED_FULLY')]`
  plus a matching `security.yaml` rule (`^/recherche`, IS_AUTHENTICATED_FULLY). The controller only trims `q`,
  applies the 2-character minimum (French hint, no query runs) and renders; all business logic is in the repositories.
- Scoping lives in the queries (rule 26), never in the template:
  - `MachineRepository::search(User $user, string $term, int $limit = 20)` — admin/responsable see every site,
    a technicien only `m.site = :site` (empty list if they have no site). The OR group is wrapped in parentheses
    so the site condition cannot be swallowed by `OR` precedence.
  - `InterventionRepository::searchById(User $user, string $term, int $limit = 20)` — admin/responsable find every
    intervention, a technicien only `i.technician = :user`. A non-numeric term matches nothing.
- `%`, `_` and `\` in the user input are escaped with `addcslashes($term, '\\%_')` before the `LIKE` pattern; every
  value is a bound parameter; each search is capped at 20 rows.
- "No match" and "not allowed" render the same French empty state ("Aucun résultat."); nothing reveals that a
  hidden record exists.
- Added `templates/_partials/global_search.html.twig` (a labelled `<input type="search">` inside a `role="search"`
  form, `minlength="2"`, pre-filled with the current `q`, mobile-friendly 44px targets) and a one-line include in
  `templates/base.html.twig` (inside the existing `{% if app.user %}` block next to the notification bell).
- Added `templates/search/results.html.twig`: GET form is the shared global bar, two always-rendered sections
  (Machines, Interventions) with `data-machine-id` / `data-intervention-id` markers, demo/confirmed badge on
  machines (rules 24/28), `rtn-` classes, mobile list.
- Added `tests/Functional/SearchTest.php`.

### Known limits of chunk 8c

- Interventions are searched by exact id: given the 2-character minimum, single-digit ids are not searchable
  (the test pins intervention ids to 4 digits with `ALTER TABLE intervention AUTO_INCREMENT` for determinism).
- Results are capped at 20 per type with no pagination, and there is no test for that cap or for a term that
  matches more than 20 rows.

## Chunk 8d — login redirect and QR code

No schema change, no migration. One new dependency (the QR library), see below. This is the last chunk of the
phase 8 spec.

### Login redirect

`src/Security/RoleBasedAuthenticationSuccessHandler.php` now uses `TargetPathTrait` and returns the user to the
page they wanted before being sent to the login form, but only when that saved path is safe:

- accepted: a relative path of this site starting with a single `/` (e.g. `/indicateurs`);
- rejected (falls back to the role dashboard): an empty string, a value not starting with `/`, a
  protocol-relative URL (`//evil.example`), anything containing `://` (an absolute URL), and the paths `/login`
  and `/logout` (never bounce back to the authentication endpoints).
- The saved path is read once and removed from the session in the same call, so it can never be replayed.
- The fallback is unchanged: `ROLE_ADMIN` → `admin`, `ROLE_RESPONSABLE` → `responsable_dashboard`,
  `ROLE_TECHNICIEN` → `technicien_dashboard`, otherwise `app_login`. The role picker stays cosmetic (rule 1).
- The firewall name is injected by Symfony through `setFirewallName()` (the security bundle wraps the handler in
  `CustomAuthenticationSuccessHandler`). Without it the handler cannot read the session key
  `_security.<firewall>.target_path` and goes straight back to the role dashboard.

Tests — `tests/Functional/LoginRedirectTest.php` (drives the real login form: GET `/login` for the session-bound
CSRF token, then POST `/login_check`):

- `testValidSavedTargetPathIsUsedOnce` — a valid saved path is used and then consumed.
- `testProtocolRelativeTargetPathIsIgnored` — `//evil.example` → role dashboard.
- `testAbsoluteTargetPathIsIgnored` — `https://evil.example` → role dashboard.
- `testLogoutTargetPathIsIgnored` — `/logout` → role dashboard.
- `testNoSavedTargetPathKeepsRoleDashboard` — no saved path keeps today's behaviour.

### QR code

- Library: `chillerlan/php-qrcode` **5.0.5** (MIT), installed with `composer require chillerlan/php-qrcode:^5.0`.
  It pulls `chillerlan/php-settings-container` 3.3.0; on disk ~318 KiB (67 files). SVG output, no GD extension.
  `composer audit` reports 23 advisories affecting 14 packages, **all pre-existing** Symfony/EasyAdmin ones;
  none concerns chillerlan.
- `src/Service/QrCodeService.php`: `faultDeclarationUrl(int $machineId)` returns
  `rtrim(DEFAULT_URI, '/') . <route fault_new generated with ?machine=<id>>`; `faultDeclarationSvg()` renders it
  with `QROutputInterface::MARKUP_SVG`, `outputBase64 = false` (raw SVG, not a base64 data URI) and
  `svgAddXmlHeader = false` (an XML prolog is invalid inline in an HTML document). The base comes from
  `#[Autowire('%env(DEFAULT_URI)%')]`.
- `config/packages/framework.yaml`: `framework.router.default_uri: '%env(DEFAULT_URI)%'`. `.env` documents the
  default `DEFAULT_URI=http://127.0.0.1:8000`. The real deployment value goes in `.env.local` (not created or
  edited here): `DEFAULT_URI=https://<hôte réel>`.
- `templates/machine/fiche.html.twig`: the QR is rendered server-side inside `.rtn-qr`, the encoded URL is shown
  as text (`.rtn-qr-url`) and exposed on the container (`data-qr-url`); a `{% block stylesheets %}` adds the
  `rtn-` styles plus an `@media print` block that hides the chrome, switches to black on white and shows a print
  title with the machine name and serial number.
- Access is unchanged: the fiche keeps `#[IsGranted('ROLE_RESPONSABLE')]`, so the QR is visible only to roles
  that could already open the fiche (technicien → 403). No route was added in this chunk.

Tests — `tests/Functional/MachineFicheQrTest.php`:

- `testFicheRendersTheQrCodeOfTheFaultDeclarationUrl` — the rendered URL contains the machine id and the
  configured base (`router.request_context.base_url`), appears as text and on `data-qr-url`, and an inline
  `<svg>` sits in the QR container.
- `testTechnicienCannotObtainTheQrCode` — a technicien gets 403 on the fiche (access rule not weakened).

### New routes and roles (chunk 8d)

Chunk 8d adds no route: it reuses the existing `fault_new` (`GET /faults/new?machine=<id>`). Phase-8 routes:
`/indicateurs` + `/indicateurs.csv` (ROLE_RESPONSABLE) and `/recherche` (any authenticated user).

### Phase 8 requirements with no test (rule 39)

- Login redirect: no test for the `/login` rejection branch specifically (only `/logout` is pinned), nor for a
  saved path carrying a query string / `parse_url` edge cases.
- QR: no test decodes the produced SVG back to the URL (only its structure and the URL text are asserted); no
  test proves that a non-default `DEFAULT_URI` from `.env.local` is honoured (the test env uses the documented
  default); the `@media print` stylesheet has no automated test (CSS).
- KPI / search: no test for the 20-results-per-type cap or for a term matching more than 20 rows; Chart.js runs
  in the browser only (no chart test).

