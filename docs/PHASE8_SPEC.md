# Phase 8 spec: KPIs, search, QR codes, login redirect

No schema changes and no migrations. If you think one is needed, stop and ask.

## Facts from an earlier audit of this repo
- `Fault` has `reportedAt`, `status` (`ouverte|en cours|resolue`), `severity`, `machine`; it has NO resolution timestamp.
- `Intervention` has `type` (`preventive|curative`), `status`, `startedAt` (set when work starts), `startDate` (planned), `endDate` (set on completion), `fault`/`schedule`, `technician`.
- `AdminStatsProvider` is the only place chart numbers are computed; reuse it.
- The fault form already accepts `/faults/new?machine=<id>`.
- The login success handler (`src/Security/RoleBasedAuthenticationSuccessHandler.php`) always redirects to the role dashboard and ignores the page the user wanted.
- Fixture accounts use password `roltech1234` (check `AppFixtures.php` if you can see it; it may be hidden in your checkout, then ask me).
- Timezone: group months with `Africa/Tunis` in PHP, not with database date functions.

## KPI formulas (document them in the code docblock and in `docs/PHASE_NOTES_trae.md`)
Window: default last 12 months through today; user can pick a range (invalid dates fall back to the default, maximum 5 years).
- Availability per machine: machines run 24h/day. Denominator = window length from `max(window start, machine installationDate)` to the window end; if zero or negative the machine shows "n/d" (never divide by zero).
- Downtime comes from `critique` faults only. For each such fault the interval is `[reportedAt, resolution)` clipped to the window. Resolution = latest `endDate` among that fault's `terminee` interventions. If the fault is still `ouverte` or `en cours`, the interval runs to the end of the window. If the fault is `resolue` with no finished intervention (a responsable closed it), it adds no downtime and the CSV marks it `resolution_sans_horodatage`. Ignore intervals whose resolution is before `reportedAt`.
- MERGE overlapping intervals per machine (union) before summing; clamp availability to 0-100%.
- `moyenne` and `faible` faults are not downtime. Planned maintenance (`en maintenance` status, preventive interventions) is not downtime. Document these as known limitations.
- Mean repair time: curative interventions only, status `terminee`, `endDate` inside the window, duration = `endDate - startedAt`, or `endDate - startDate` if `startedAt` is null. Open interventions are excluded.
- Faults per site per month: faults whose `reportedAt` is inside the window, grouped by site and month; also groupable by model. The CSV adds the model's `dataStatus` so demo machines are marked.
- Put the computation in a pure class/method taking plain arrays or value objects (no repositories, no EntityManager) so it tests without a database. Load faults and interventions with a constant number of queries (no query per machine in a loop).

## Routes (all GET, read-only)
- `/indicateurs` (KPI page) and `/indicateurs.csv`: `#[IsGranted('ROLE_RESPONSABLE')]` (admin included via hierarchy) AND `security.yaml`. A technicien gets 403. Tables always render (primary content, works offline). Charts use the Chart.js lazy CDN import in `assets/kpi.js` as an optional enhancement; listeners registered immediately (rule 7); the page must still work if the CDN is unreachable. French empty states.
- `/recherche`: any logged-in user.

## CSV
Semicolon, UTF-8 BOM, formula escaping through a new `src/Service/CsvCellEscaper.php` (single quote prefix for cells starting with `=`, `+`, `-`, `@`). The catalog export (`CatalogCsvService`) must call the same escaper with identical behavior; do not change the import's unescape rule; existing catalog CSV tests must stay green. KPI CSV columns include a model data-status column and the `resolution_sans_horodatage` marker.

## Search
Machines by name, serial number or model; interventions by id. Scoped by role in the query: technicien finds only machines of their site and interventions assigned to them (anything else returns the same empty result as no match, no hint that it exists); responsable/admin find everything. Escape `%`, `_` and `\` before using text in `LIKE`; bind every parameter; require at least 2 characters (French hint otherwise); max 20 results per type. UI is a partial `templates/_partials/global_search.html.twig` included in `base.html.twig` with a one-line edit.

## Login redirect
Use the saved target path only if it is a relative path starting with a single `/` (reject `//`, anything containing `://`, `/login`, `/logout`); use it once (remove it from the session); otherwise today's behavior. Authorization still comes only from DB roles (rule 1).

## QR code
Library: `chillerlan/php-qrcode` 5.0.5 (MIT, SVG output, no GD). Install with `composer require chillerlan/php-qrcode:^5.0`, then `composer audit` and report. The QR encodes only the absolute URL `/faults/new?machine=<id>`, built from a configurable base URL: use `framework.router.default_uri` read from env variable `DEFAULT_URI` with the default `http://127.0.0.1:8000` documented in `.env` (the real value goes in `.env.local`; do not create or edit it, tell me what to put). Render SVG server-side on the machine fiche, show the encoded URL as text under it, and add an `@media print` stylesheet so it prints with the machine name and serial number.

## Tests
- Pure unit: KPI formula (overlapping critique faults, an open critique fault, a machine installed inside the window, fault resolved by a responsable without intervention, no faults = 100%, curative vs preventive in repair time, zero denominator); `CsvCellEscaper`.
- Functional (`roltech_gmao_test`): KPI routes 403 for technicien and 200 for responsable/admin; CSV has the BOM, semicolon, escaped formula case, data-status column and marker; search scoping (technicien cannot find another site's machine or another technician's intervention; wildcard input; minimum length); login redirect (valid saved path works; `//evil.example`, `https://evil.example` and `/logout` are ignored; no saved path keeps current behavior); QR URL contains the machine id and the configured base.