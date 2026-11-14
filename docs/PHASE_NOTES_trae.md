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
