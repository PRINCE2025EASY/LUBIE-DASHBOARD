# Technical guide

For developers who maintain or extend the dashboard. Design reasons are in
`docs/DESIGN_SPECIFICATION.md`; tables and columns in `docs/DATA_DICTIONARY.md`; every endpoint
in `docs/API_REFERENCE.md` (generated); diagrams in `docs/diagrams/`.

## 1. Architecture in one page

- Plain PHP 8.2, no framework. Layers: Controllers (HTTP only) > Services (rules, transactions,
  scope checks) > Repositories (SQL, prepared statements) > MySQL/MariaDB. Read-heavy dashboard
  queries live in query services (Dashboard, AlertQuery, AuditQuery, Threshold).
- Front controller `public/index.php` > `Kernel`: match route, start database session, load user,
  check the route guard (`public`, `auth`, `perm:<name>`), check CSRF, run controller, save session,
  add security headers. Errors become one JSON format (API) or an error page.
- Permissions: `config/permissions.php` (role list per permission). Project scope:
  `Authorizer::requireProject()` in every service method that takes an ID (404 outside scope).
  Field filtering: `Presenter` decides which fields each role receives.
- KPIs: `KpiService` is pure calculation on a `ProjectSnapshot` (money in integer pesewas,
  percentages in hundredths). Only approved progress counts.
- Alerts: one class per rule in `src/Alerts/Rules` (Strategy pattern). `AlertService` evaluates
  them after each save and on the first request of the day, de-duplicates by key, clears alerts
  whose condition ended, and sends SMS through `SmsDispatcher` with daily and monthly caps.
- Front end: Bootstrap 5.3, Chart.js 4 (loaded on demand), ES modules per role in
  `public/assets/js/views`. Strict CSP: no inline script or style. Service worker `public/sw.js`
  caches the app shell; `lib/outbox.js` keeps offline entries in IndexedDB, encrypted with a
  per-user AES-GCM key from the server.
- Stateless: sessions in the database, settings from environment variables, no local files,
  no cron. Runs the same on XAMPP and in the Docker image.

## 2. Folders

| Folder | Content |
|---|---|
| `public/` | Web root: index.php, assets, service worker, manifest |
| `src/` | Application classes (`Lubie\\` namespace, PSR-4) |
| `config/` | bootstrap, routes, permissions |
| `templates/` | PHP page and report templates (escaped output only) |
| `database/` | schema, migrations, seeds, privilege scripts |
| `bin/` | console.php (admin commands), lint.php |
| `tests/` | Unit and integration tests (PHPUnit 11) |
| `docker/`, `Dockerfile`, `render.yaml` | Container image and Render blueprint |
| `tools/` | Generators (data dictionary, API reference, diagrams), reference KPI check |

## 3. Configuration (environment variables)

Set in `.env` on XAMPP or in the host's settings online. Values never go into the repository.

| Variable | Meaning | Default |
|---|---|---|
| APP_ENV | local, testing, production | local |
| APP_DEBUG | 1 shows error details (local only) | 0 |
| APP_KEY | 32+ random characters; derives offline-queue keys | none (required) |
| APP_BASE_PATH | Sub-folder, e.g. /lubie-dashboard on XAMPP; empty online | empty |
| APP_FIXED_NOW | Freeze the clock for tests | empty |
| DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD | Database connection (least-privilege account) | 127.0.0.1, 3306, lubie_dashboard, lubie_app |
| DB_TEST_NAME | Test database, must end in _test | lubie_dashboard_test |
| DB_SSL_CA | Path to the database CA certificate | empty |
| DB_SSL_CA_PEM | The CA certificate as text (hosts without files) | empty |
| DB_SSL_VERIFY | Verify the server certificate | 1 |
| DB_CONNECT_RETRIES | Connection attempts (cold databases) | 3 |
| SESSION_SECURE_COOKIE | 1 on HTTPS: Secure `__Host-` cookie and HSTS | 0 |
| TRUSTED_PROXY_HOPS | Proxies in front of the app (Render: 1) | 0 |
| SMS_PROVIDER | sandbox or arkesel | sandbox |
| SMS_BASE_URL, SMS_API_KEY, SMS_SENDER_ID | Provider settings | empty |
| BACKUP_ENCRYPTION_KEY | 32+ characters; without it backups cannot be read | none |
| BACKUP_TOKEN | Bearer token for `/api/v1/backup/run` | none |
| PORT | Listening port in the container | 10000 |
| MIGRATE_DB_USER, MIGRATE_DB_PASSWORD | Container only: owner account for a one-off migration at start | empty |

Run-time settings (refresh interval, lockout, SMS caps, offline on or off, thresholds) live in
the database and are edited in the app.

## 4. Coding standards

- `declare(strict_types=1)` in every PHP file; PSR-12 layout; final classes unless extension is planned.
- SQL only with bound parameters. Identifiers never come from user input.
- Output escaped in templates (`$e()`); JavaScript writes text with `textContent` only (`lib/dom.js`).
- Every route declares a guard; every service method taking an ID calls `requireProject()`.
- Money: `Lubie\Support\Money` (integer pesewas). No floats for money.
- Every change that matters is audit-logged through `AuditLogger`.
- British English in all user text; dates DD/MM/YYYY on screen, ISO in storage.
- Before a commit: `php bin/lint.php`, `npm run lint`, `vendor/bin/phpunit`. CI runs the same.

## 5. How to add an alert rule

1. Create `src/Alerts/Rules/A16Something.php` implementing `AlertRule::evaluate(ProjectSnapshot $s, KpiResult $k, Thresholds $t, string $today): array`.
   Return one `Condition` per true case with a stable dedup key (`A16:entity:id`), severity,
   title, description, recipient roles, SMS roles (never `client`) and `clientVisible`.
2. If it needs new data, add it to `ProjectSnapshot` and `ProjectSnapshotRepository::load()`.
3. If it needs a threshold, add the key to `Thresholds::defaults()`, `ThresholdService::KEYS` and
   a migration inserting the setting.
4. Register the class in the list in `AlertService::__construct()`.
5. Add tests in `tests/Unit/AlertRulesTest.php` (fires, stays quiet, recipients). The test
   `testNoRuleEverSendsSmsToClients` must still pass.
6. Document it in Design Specification section 9.2.

## 6. How to add an SMS provider

1. Create `src/Sms/MyProviderGateway.php` implementing `SmsGateway` (`name()`, `send()` returning
   `ok`, `ref`, `error`). Read credentials only with `Env::get()`. Use a 5-second timeout.
2. Select it in `lubie_container()` in `config/bootstrap.php` when `SMS_PROVIDER=myprovider`.
3. Add the variables to `.env.example` (empty) and section 3 above.
4. Test with Administrator > Settings > Send test, then confirm on a real phone. Caps and retries
   are handled by `SmsDispatcher`, not the adapter.

## 7. Extension points

- Offline: `SyncService` accepts item type `progress`. To queue labour, material or equipment
  entries, add a type there that calls the matching service (which re-checks role, scope and
  validation), then enqueue that type in the view with `outbox.enqueue()`.
- Analytics: read-only query services and the CSV exports are the safe way in. A reporting
  database user with SELECT only, or the encrypted backup restored elsewhere, keeps analysis away
  from the live tables.
- New role: add it to `roles` (migration), `config/permissions.php`, `Presenter` field rules,
  a view module in `public/assets/js/views`, and PermissionMatrixTest.

## 8. Regenerate generated documents

    php tools/gen_api_reference.php       # docs/API_REFERENCE.md
    python3 tools/gen_data_dictionary.py  # docs/DATA_DICTIONARY.md
    bash tools/diagrams/build_all.sh      # docs/diagrams/*.drawio
