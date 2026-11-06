# RolTech GMAO — Project Context & Handoff Document

> Purpose: this file exists so another AI session (or a future you) can pick up
> this project without re-deriving decisions already made. Read this before
> making structural changes.

## What this project is

A student internship (stage) project for **Roller Technology Systems
Industry (RolTech)**, a manufacturing company in Tunis, Tunisia. The
deliverable is a simplified CMMS ("GMAO simplifiée") — a web app for tracking
industrial machines, faults ("pannes"), and maintenance interventions,
replacing a manual paper/Excel process.

The internship runs 4 sprints (S1–S4):
- **S1**: cahier des charges + UML modeling
- **S2**: MySQL database design
- **S3**: core modules (machines, faults, interventions) + functional tests
- **S4**: dashboard/indicators, technical docs, final report

Stack was specified loosely by the company contact (Ahmed): "Symfony (PHP) ou
autre", MySQL, Git. We chose **Symfony 7 (webapp skeleton) + Twig + MySQL +
EasyAdminBundle** as the concrete stack.

## Two separate repositories

1. **`ROLTECH_GMAO`** — the actual Symfony application (code).
2. **`ROLTECH_STAGE_DOCS`** — MkDocs Material documentation site (cahier des
   charges, UML diagrams as Mermaid, meeting notes, stage journal), deployed
   to GitHub Pages via `.github/workflows/deploy.yml`. Each repo's README
   links to the other.

This document describes **`ROLTECH_GMAO`** only.

## Tech stack specifics (matters for troubleshooting)

- Symfony 7, `--webapp` skeleton (Twig, Doctrine ORM, Security, Forms,
  Validator all included by default)
- **AssetMapper** for frontend assets (no npm/webpack build step) — CSS/JS
  files under `assets/` are auto-mapped; reference them with
  `{{ asset('path/to/file.css') }}` in Twig, or `addCssFile()`/`addJsFile()`
  in PHP (see EasyAdmin gotchas below)
- **Symfony UX Turbo** is active by default on the whole app except the
  login page (`data-turbo="false"` set there deliberately — see Known
  Gotchas)
- **EasyAdminBundle 5** (`easycorp/easyadmin-bundle`) powers `/admin`
- MySQL/MariaDB via Doctrine ORM + Migrations
- Dev environment is **split across two machines**: a Windows PC (XAMPP,
  PowerShell) and an Ultramarine Linux (Fedora-based) laptop (native
  MariaDB, zsh). Commands given to the user must match whichever machine
  they're on — don't assume bash syntax works if they're on the Windows box.

## Entities (`src/Entity/`)

- **`User`** — `email` (identifier), `fullName`, `roles` (array:
  `ROLE_ADMIN` / `ROLE_RESPONSABLE` / `ROLE_TECHNICIEN`), `password`
  (hashed). Created via `make:user`, not `make:entity`.
- **`Machine`** — `name`, `model`, `serialNumber`, `installationDate`,
  `category` (free text: Presse/Usinage/Manutention/Utilitaire in fixture
  data), `location` (nullable), `status` (free text: "en service"/"en
  panne"/"en maintenance"). Has many `Fault`s.
- **`Fault`** ("Panne") — `reportedAt`, `description`, `severity` (free
  text: critique/moyenne/faible), `status` (free text: ouverte/en
  cours/resolue), `machine` (ManyToOne), `reportedBy` (ManyToOne → User).
  Has many `Intervention`s.
- **`Intervention`** — `type` (preventive/curative), `startDate`,
  `endDate` (nullable), `status` (planifiee/en cours/terminee), `comment`
  (nullable), `fault` (ManyToOne), `technician` (ManyToOne → User).

All status/category/severity/type fields are **free-text strings**, not
enums — chosen for simplicity at this project's scale, but means exact
string matching everywhere (badge maps, stat counts) is fragile to typos.
All four entities have `__toString()` implemented (required — EasyAdmin's
`AssociationField` crashes without it).

Fixtures (`src/DataFixtures/AppFixtures.php`) seed 4 users, 4 machines, 3
faults, 3 interventions with realistic French data matching the above
vocabulary exactly.

## Authentication / login system

**Design**: a role-picker screen (`/login`) where the user picks a role tab
(Technicien / Responsable / Administrateur) purely for **UI theming** — the
actual authorization always comes from the authenticated user's real
`roles` in the database, never from what was clicked. This was a deliberate
security decision, tested explicitly (log in as a technicien while the
"Administrateur" tab is selected → must still land on `/technicien`, not
`/admin`).

Files:
- `templates/security/login.html.twig` — sidebar-nav-style glass card,
  light "Nord" palette (not dark — went through several visual iterations:
  desktop-window metaphor → dark neon/glass → current light Nord-muted
  style, matching a reference screenshot of a light card-based dashboard
  app)
- `assets/styles/login.css`, `assets/login.js` — **all classes are
  `rtn-`-prefixed** (RolTech Neon, name stuck from an earlier iteration).
  This prefix discipline exists because of a real bug (see Known Gotchas).
- `src/Controller/SecurityController.php` — `/login`, `/login_check`,
  `/logout` routes
- `src/Security/RoleBasedAuthenticationSuccessHandler.php` — redirects
  based on `$user->getRoles()`, ignoring any client-side role selection
- `config/packages/security.yaml` — `form_login` with the custom success
  handler, `role_hierarchy` (ADMIN inherits RESPONSABLE inherits
  TECHNICIEN), `access_control` per route prefix
- `src/Controller/RoleDashboardController.php` +
  `templates/dashboard/placeholder.html.twig` — bare-bones landing pages
  for `/technicien` and `/responsable` (not yet built out; Admin uses the
  real EasyAdmin dashboard at `/admin`)

Test accounts (from fixtures): `admin@roltechsystems.com` / `admin1234`,
`karim.technicien@roltechsystems.com` / `tech1234`,
`sami.technicien@roltechsystems.com` / `tech1234`,
`malek.responsable@roltechsystems.com` / `resp1234`.

## Admin panel (`/admin`, EasyAdminBundle)

`src/Controller/Admin/DashboardController.php` configures:
- `Theme::new()` — official EA5 theming API: `primaryColor()` (Nord frost
  blue `#5e81ac`/`#88c0d0`), `radius('xl')`, `spacing('xl')`,
  `grays('slate')`, `setDefaultColorScheme('dark')`
- `configureAssets()` loads two custom files via `addCssFile()` /
  `addJsFile()` — **not** `addAssetMapperEntry()` (see gotchas)
- `configureMenuItems()` links to the 4 CRUD controllers

**CRUD controllers** (`src/Controller/Admin/{Machine,Fault,Intervention,
User}CrudController.php`) — `make:admin:crud` generates placeholder fields
(`title`/`description`) that don't match real entities; these were
rewritten by hand to use real fields. Status/category/severity/type/role
columns use a shared **badge template** system:
- `templates/admin/field/badge.html.twig` — generic icon+color pill,
  driven by a `badgeMap` custom option per field
- `templates/admin/field/role_badge.html.twig` — variant for the `roles`
  array field on User
- `templates/admin/field/lead_icon.html.twig` — bigger standalone icon
  tile (used as Machine's first card field in grid view)

`assets/admin-overrides.css` / `assets/admin-overrides.js` (loaded via
`configureAssets()`) add, on top of EasyAdmin's stock look:
- Sidebar/font/icon size increases using **confirmed real CSS variable
  names** pulled directly from the installed
  `vendor/easycorp/easyadmin-bundle/assets/css/easyadmin-theme/*.css` (do
  not guess these — grep the actual installed version first, they change
  between EA releases)
- A glass/blur treatment on the sidebar and stat cards
- A **Table/Grille view toggle** — JS-injected button pair above every
  CRUD index page's table, toggling a `body.rtn-grid-mode` class that
  CSS-transforms the `<table class="datagrid">` into a card grid (pure
  CSS trick: `tbody { display: grid }`, hide `<thead>`) — chosen over a
  full custom index template rewrite to avoid breaking EasyAdmin's
  built-in sorting/pagination/bulk-actions JS
- **Charts**: `src/Service/AdminStatsProvider.php` is the single source of
  truth for all chart data (counts by status/category/severity/type/role,
  monthly time series for faults/interventions), consumed two ways:
  - Main dashboard (`templates/admin/dashboard.html.twig`) embeds the full
    stats as inline JSON (`<script type="application/json"
    id="rtn-stats-embedded">`) — no extra network round-trip
  - Each CRUD index page fetches the same data from
    `/admin/stats-data` (`src/Controller/Admin/StatsApiController.php`)
    and renders just its own section's charts, detecting which section
    it's on from the **URL path** (EasyAdmin 5 uses pretty URLs like
    `/admin/fault`, NOT `?crudControllerFqcn=...` — an earlier version
    keyed off that query param and silently never matched). The old
    query param and the page `<h1>` remain as fallbacks.
  - Chart.js is loaded via a **dynamic `import()` from a CDN URL** inside
    `admin-overrides.js`, not a static `import` statement and not
    AssetMapper/importmap — this sidesteps needing to know whether
    EasyAdmin's generated `<script>` tag has `type="module"` (see
    gotchas)

## Known gotchas (hard-won — don't rediscover these)

1. **EasyAdmin `customOptions` needs `.get('key', default)`, not
   `.badgeMap` property/array access** in Twig — the latter silently
   resolves to nothing (no error), causing every badge to render the
   fallback icon/color. This exact bug shipped once already.
2. **`Assets::addAssetMapperEntry()` requires the file to be registered in
   `importmap.php`** (it renders through Twig's `importmap()` function) —
   it is NOT the same as AssetMapper auto-mapping a file for `asset()`.
   For plain CSS/JS files that don't need importmap, use `addCssFile()` /
   `addJsFile()` instead.
3. **Generic CSS class names caused a real invisible-content bug** on the
   login page — `.content`, `.panel` etc. collided with either
   `app.css` or the Symfony Web Debug Toolbar's own stylesheet
   (`_wdt/styles`, loaded on every page in dev). Root-caused by comparing
   computed styles across ancestors in DevTools. Fix was a full rewrite
   with a unique prefix (`rtn-`). **Always prefix custom classes uniquely
   in this project.**
4. **Symfony UX Turbo intercepts form submissions** and can leave a
   dynamically-JS-built form in a broken half-updated state after a
   redirect (e.g., failed login), because Turbo's morph doesn't reliably
   re-run inline `<script>` tags that set up CSRF tokens / re-trigger JS
   init. Fixed on `/login` via `data-turbo="false"` on the outer wrapper.
   If new pages have JS-heavy dynamic forms, consider the same.
5. **Don't wrap an entire script's setup (including event listener
   registration) inside an `async` function gated on an `await import()`
   of a slow external resource.** A previous version of
   `admin-overrides.js` did this with the Chart.js CDN import, which
   could delay `DOMContentLoaded` listener registration past the point
   the event actually fires, silently breaking on some page loads. Fix:
   keep synchronous setup (like the view-toggle button) unconditional and
   immediate; only gate the actual chart-rendering code behind the async
   import.
6. **EasyAdmin dark mode was ignoring plain `:root` CSS variable
   overrides** for sizing (font/icon sizes) despite no competing
   dark-scheme rule existing anywhere in the installed CSS (verified via
   full grep). Root cause not fully diagnosed (possible cascade-layer
   interaction); worked around with `!important` on the override block,
   applied to both `:root` and `.ea-dark-scheme` explicitly.
7. **PHP `intl` extension must be enabled** in `php.ini` (commented out by
   default in XAMPP) — EasyAdmin throws a hard `LogicException` on any
   date field without it.
8. **`make:admin:crud` does not know your entity's real fields** — it
   generates placeholder `title`/`description` fields. Always rewrite
   `configureFields()` by hand against the actual entity.
9. **XAMPP MySQL**: default is `root` with no password, service must be
   manually started in XAMPP Control Panel. **Native MariaDB (Linux)**:
   `root` uses unix-socket auth by default, requires `sudo` for
   `mariadb-secure-installation`; app credentials should be a dedicated
   `.env.local`-only user, never committed.
10. Real DB credentials belong in `.env.local` (gitignored), never in the
    committed `.env`.

## Outstanding / not yet done

- `RoleDashboardController`'s Technicien/Responsable pages are placeholder
  stubs only — real functionality (viewing/reporting faults, managing
  interventions per role) not built yet.
- `User` CRUD intentionally has **no password-edit field** — editing it
  correctly needs a Symfony password-hasher event listener, not a plain
  form field (flagged, not built).
- Dashboard/section charts were reworked (fixed-height chart bodies,
  denser grid, wide trend charts packed first, section detected from the
  URL path, toggle/listeners no longer gated behind the Chart.js CDN
  import). Verify on a real browser that the dashboard fits acceptably
  and that Machines/Faults/Interventions/Users each show their charts.
  Density is one CSS variable: `--rtn-chart-h` on `.rtn-charts-grid`.
- Sprint work itself (S3 core modules beyond CRUD scaffolding, S4
  finalized indicators/report) is still ahead — this whole conversation
  covers S1 environment setup through early S2/S3 tooling, not the full
  internship scope.
- **No automated tests exist** (`tests/` is empty) despite this being an
  explicit S3 deliverable ("premiers tests fonctionnels").

## Working conventions established in this project

- **This repo is worked on by multiple AI tools, not just Claude** — the
  project root also contains `.aider.tags.cache.v4`, `.kilo/`, and
  `.sylix/` (Aider, Kilo Code, and a "Sylix" agent conversation/skills
  folder). Don't assume this document or a Claude chat history is the
  complete record of what's changed — check actual file contents before
  assuming staleness either way.
- Verified against a real `tree` output of the project root (confirms:
  `src/{Controller/Admin,DataFixtures,Entity,Repository,Security,Service}`,
  `templates/{admin/field,dashboard,security}`,
  `config/{packages,routes}`, `assets/{styles,controllers,vendor}`,
  `migrations/`, `public/bundles/easyadmin/` all exist as described above).
  `tests/` exists but is **empty** — no automated tests written yet,
  despite S3's stated deliverable including "premiers tests fonctionnels".
- When editing a file already in the repo, always ask to see current
  content first if there's any chance it's been hand-modified since —
  several bugs came from blindly overwriting.
- Before writing CSS/JS that touches EasyAdmin internals, get the real
  variable/class names from the user's actual installed
  `vendor/easycorp/easyadmin-bundle` via grep — never guess. This
  discipline was adopted after the class-collision bug and paid off
  repeatedly afterward.
- Verify asset-loading fixes with `curl`/`Invoke-WebRequest` against the
  rendered HTML (checking for the actual `<link>`/`<script>` tag and its
  resolved hashed filename) rather than trusting a fix worked from code
  review alone.
