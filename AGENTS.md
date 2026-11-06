# RolTech GMAO — agent instructions

Symfony 7 webapp (Twig, Doctrine, MySQL/MariaDB, EasyAdminBundle 5, AssetMapper).
Internship CMMS: machines, faults ("pannes"), interventions, users with roles
ROLE_ADMIN / ROLE_RESPONSABLE / ROLE_TECHNICIEN. UI text in French, code and DB names in English.
Longer background: `PROJECT_HANDOFF.md` (open it only when you need it).

## Layout
- `src/Entity`: User, Machine, Fault, Intervention (all define `__toString()`, EasyAdmin needs it)
- `src/Controller/Admin`: DashboardController (Theme API, configureAssets), *CrudController, StatsApiController
- `src/Service/AdminStatsProvider.php`: single source of chart data (dashboard + section pages)
- `src/Security` + `config/packages/security.yaml`: login redirect by real roles
- `templates/security/login.html.twig`, `templates/admin/dashboard.html.twig`, `templates/admin/field/*.twig`
- `assets/admin-overrides.css|js`, `assets/login.js`, `assets/styles/login.css`

## Rules (each one is a bug we already hit)
1. The role picker on the login page is cosmetic. Authorization comes only from the user's roles in the DB.
2. Prefix every custom CSS class with `rtn-`. Generic names (.content, .panel) collided with EasyAdmin/profiler CSS and made content invisible.
3. Before writing CSS/Twig that touches EasyAdmin internals, grep `vendor/easycorp/easyadmin-bundle` for the real variable/class names. Do not guess them.
4. In EasyAdmin field templates use `field.customOptions.get('key', default)`, never `field.customOptions.key`.
5. Load plain assets with `Assets::addCssFile()` / `addJsFile()`. `addAssetMapperEntry()` needs an `importmap.php` entry.
6. `/login` keeps `data-turbo="false"`; Turbo broke the JS-built form after a failed login.
7. In JS, never gate listener setup behind `await import(...)`. Chart.js is a lazy dynamic import from a CDN.
8. EasyAdmin 5 uses pretty URLs (`/admin/fault`), not `?crudControllerFqcn=`.
9. Status/category/severity/type are free-text strings and matched exactly: `en service|en panne|en maintenance`, `critique|moyenne|faible`, `ouverte|en cours|resolue`, `preventive|curative`, `planifiee|en cours|terminee`.
10. `make:admin:crud` generates placeholder fields; always rewrite `configureFields()` against the real entity.
11. Secrets live in `.env.local` only, never in `.env`. Dev happens on Windows (PowerShell, XAMPP) and Linux (zsh): give commands for the right shell.

## Not done yet
Technicien/Responsable dashboards are placeholders, User CRUD has no password editing, `tests/` is empty.
