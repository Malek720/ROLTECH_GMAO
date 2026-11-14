# Remaining phases (6-9)

Standing instructions: follow `AGENTS.md` (especially rules 21-26, 32-39). List the files you will change before editing. No migrations or fixtures against the main database; new schema changes are tested on the copy first. After each phase give PHPUnit totals, new tests mapped to requirements, requirements without tests, URLs, test accounts and a manual script, then stop and wait.

## Phase 6: Tunisia map and navigation
- Add nullable `latitude` and `longitude` to `Site` (migration with backfill; Tunis 36.8065, 10.1815; Sousse 35.8256, 10.6084; Béja 36.7256, 9.1817; Jendouba 36.5011, 8.7803; check the sites in the fixtures).
- Leaflet installed locally through AssetMapper importmap (no CDN). Tunisia outline from a public-domain GeoJSON file stored in `assets/` (do not hand-draw coordinates; if you cannot download one, ask the user to provide it). OpenStreetMap tiles: optional toggle, fallback to the outline when offline.
- `/carte` (technicien and above). Responsable and admin see all sites; a technicien sees only their own site, enforced in the query. Marker color by worst state: red (any `en panne` machine or open `critique` fault), orange (any `echue` or `proche` active schedule), green otherwise; always icon + text. Popup: site name, machine counts per status and per model, button "Voir les machines". Compact map card on both dashboards. Must work with Turbo.
- Breadcrumb everywhere: Tunisie > Site > Zone > Machine > Module > Pièce.
- Site page with machine cards grouped by zone (`Machine.location`): status badge, model with demo badge, serial number, next due task, open fault count. A technicien cannot open another site's page.
- Machine list with search (name, serial number, site, model) and status, model, family filters, scoped by role.
- Tests: map data per role, marker severity logic, search/filters, site page access, breadcrumb.

## Phase 7: 3D viewer
- Three.js through the AssetMapper importmap, lazy-loaded on viewer pages only. Shared procedural builder for the common conveyor modules (rail/track, traction element, trolleys, 180° and 90° return groups, motor-reducer on its support, tensioner) plus per-model configs `assets/viewer/models/<model-code>.config.js` (module code -> mesh group, French label, color, camera focus); generic fallback built from a model's modules (press, compressor). No invented geometry details for the OC-CA1100. A `.glb` at `assets/models/<model-code>.glb` replaces the procedural model if node names equal module codes.
- Interactions: orbit/zoom/pan (mouse and touch). Clicking a module highlights it and opens a side panel: name, parts list (part number or "référence à confirmer", quantity, description), health from active schedules (icon + text), next due task, buttons "Déclarer une panne sur ce module" (fault form with machine and module prefilled; reuse the validated `?machine=` prefill and extend it) and "Voir l'historique". Clicking a part in the list highlights its module. "Vue éclatée" slider.
- Use it on the machine fiche and as an optional visual picker in the fault form (dropdowns stay). Demo badge when the model is demo.
- Fallback without WebGL: 2D module list with the same panel. Light for mobile; dispose of Three.js resources on navigation (Turbo).
- Tests: module-health computation service, prefill link generation and validation, config coverage per seeded model, access by role. State what cannot be tested automatically (rendering) and provide a manual script.

## Phase 8: KPIs, search, QR codes
- KPI page (responsable, admin): faults per site per month, mean repair time (intervention start to end), availability per machine (define the formula in code and in `PROJECT_HANDOFF.md`; machines run 24h/day), grouping by model, date range filter. Use `AdminStatsProvider`; no second source of chart data.
- CSV export of the KPI tables: semicolon, UTF-8 BOM, formula escaping (rule 34), plus a model data-status column so demo machines are marked.
- Global search: machine by name, serial number, model; interventions by id; scoped by role.
- Printable QR code on each machine fiche linking to the fault form prefilled with that machine (login required, redirect back after login). The QR contains only the URL. Ask the user before adding any dependency and state its license and size.
- Helpful French empty states and confirmation dialogs for destructive actions across the app.
- Tests: KPI formulas with known fixtures and edge cases (no interventions, open interventions), CSV escaping, search scoping, QR target URL and redirect after login.

## Phase 9: mobile, accessibility, cleanup, documentation
- Mobile and tablet pass on technicien and responsable pages (large touch targets, readable tables, no horizontal scroll), keyboard navigation and visible focus, labels on inputs, contrast, `aria-live` on toasts, no status by color alone.
- Fix PHPUnit deprecation notices, remove dead code, check that no confidential content is in public templates (rule 19), set up `README.md` in French (no confidential details): install steps for Windows and Linux, test accounts, how to schedule `app:maintenance:check` (Windows Task Scheduler and cron).
- Make `PROJECT_HANDOFF.md` and `AGENTS.md` match the final code (entities, statuses, rules, formats, KPI formulas).
- Run the full suite and a final manual script covering: login per role, fault declaration, dispatch, intervention workflow, maintenance command, map, viewer, catalog import/export, KPI export.
- List known limitations honestly: what has no automated test, what is demo data, what needs RolTech to confirm.