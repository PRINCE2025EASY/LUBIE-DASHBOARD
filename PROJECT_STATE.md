# PROJECT_STATE

Last updated: 21/10/2026 (Africa/Accra)

## Current stage

Stage 6, Deploy: prepared (Docker image, Render blueprint, Aiven guide). Waiting for the owner to create the two free accounts and enter the secrets. Next after that: live checks, then Stage 7 (Documentation).

## Decisions recorded

| Date | Decision |
|---|---|
| 21/10/2026 | Director has no audit-trail access. |
| 21/10/2026 | Site engineers may create and edit activities on assigned projects (never cost fields; PM sets budgeted cost and milestones). |
| 21/10/2026 | Clients receive in-app notices only; no SMS to clients. |
| 21/10/2026 | GitHub repository exists: PRINCE2025EASY/LUBIE-DASHBOARD, PUBLIC, empty, default branch main. Not touched. |
| 21/10/2026 | Offline mode is Must, Iteration 1 (FR-38). Progress entries queue offline now; labour, material and equipment follow with their modules. |
| 21/10/2026 | Repository stays public for now; owner will make it private later. Push still needs approval at Stage 5. |
| 21/10/2026 | Project folder approved: C:\xampp\htdocs\lubie-dashboard. |
| 21/10/2026 | Administrator sees the audit trail; Director does not. |
| 21/10/2026 | XAMPP confirmed from its readme: Apache 2.4.58, MariaDB 10.4.32, PHP 8.2.12. php.ini change approved and made (backup kept). |
| 21/10/2026 | Push of main and iteration-1 to the public repository approved. Owner runs usability sessions T1 to T5. |
| 21/10/2026 | Owner pushed main (0c2ae89) from C:\Users\anaay\LUBIE-DASHBOARD. First CI run failed on Dompdf advisories; fixed with 3.1.6. |
| 21/10/2026 | Hosting: Render free web service (Docker, Frankfurt) + Aiven free MySQL recommended (docs/DEPLOYMENT_OPTIONS.md). |

## Work completed

Stage 1: findings, Issues Register (I-01 to I-33), Assumptions Register (A-01 to A-22), MoSCoW recalculation, master requirements (41 FR, 13 NFR, 12 Won't), traceability matrix.

Stage 2:
- `docs/DESIGN_SPECIFICATION.md`: scope, 13 architecture decisions, design-principles table, ISO/IEC 25010 targets, final permission matrix, security controls, STRIDE threat model mapped to OWASP Top 10, API specification (about 70 endpoints), KPI and alert service design, database design, SMS, refresh, offline, reporting, backup, audit, UI navigation and wireframes, configuration, test strategy.
- `docs/DATA_DICTIONARY.md` (generated from schema).
- `docs/USE_CASE_SPECIFICATIONS.md` (15 use cases).
- `database/schema.sql` v0.2.0 (31 tables), `migrations/0001_initial_schema.sql`, `seed_production.sql`, `seed_demo.sql`, `remove_demo.sql`, `privileges_template.sql`, `privileges_generated.sql`.
- Eight `.drawio` files (39 pages) in `docs/diagrams/`, PNG previews, README.
- Tools: `tools/reference_kpi_check.py`, `tools/gen_data_dictionary.py`, `tools/diagrams/*` (generators, validator, preview renderer).

## Checks run (cloud workspace, MariaDB 10.11.14, PHP 8.3 CLI for hashing only)

| Check | Result |
|---|---|
| schema.sql import | Pass: 31 tables, 57 foreign keys, 27 CHECK constraints |
| seed_production.sql + seed_demo.sql import | Pass |
| Demo statuses | Progress: submitted 2, approved 12, returned 1. Budgets: draft, pending, approved x3, rejected, superseded. Certificates: submitted, certified, part_paid, paid x4 (overdue derived) |
| Eight constraint probes (second current budget, 110 %, duplicate assignment, finish before start, current flag on pending budget, duplicate open alert, negative expenditure, non-+233 phone) | All rejected by the database |
| Cleared alert can re-open with same key | Pass |
| remove_demo.sql | Pass: back to reference data only |
| Least-privilege account | UPDATE and DELETE on audit_logs refused; DROP refused |
| reference_kpi_check.py on demo seed | All 16 alert rules (A1-A15, A6b) triggered; P1 SPI 0.91, CPI 0.55, consumed 112.0 %; P2 SPI 0.86, CPI 0.64, 85.0 % |
| Diagram validation | 8 files, 39 pages, 1,418 vertices, 726 connectors; 0 problems (XML, IDs, connectors, grid, overlaps, empty shapes) |
| ERD vs schema | 31/31 tables, all columns, 57/57 FKs |

## Stage 3 (Iteration 1) delivered

- Application: 87 PHP classes in `src/` (front controller, router with a guard per route, DB sessions, CSRF, permission matrix, project scope checks, field-level DTOs, KPI engine, 16 alert rules, SMS sandbox and HTTP adapter with caps, reports CSV/PDF, encrypted backup and restore check, offline sync).
- Front end: six role views plus login and password pages, dark/light toggle, service worker and encrypted IndexedDB outbox, 44 px touch targets, strict CSP (no inline script).
- `bin/console.php`: migrate, setup:first-admin, seed:demo, test-db:reset, backup:create, restore:verify, alerts:run, sms:retry. `bin/lint.php`.
- Tests: 51 unit and 37 integration tests (88 tests, 830 assertions).
- `docs/INSTALL_XAMPP.md`, `README.md`, `.env.example` (placeholders only). Schema v0.3.0 (`change_seq` poll token, migration 0002). ERD and data dictionary regenerated.
- Fixes found by testing: cost lookups hidden from Site Engineers; reports card hidden for roles without reports; "null" text on the site progress form; stale offline badge; alert dates shown DD/MM/YYYY; restore KPI comparison; user-form error grouping; X-Powered-By removed.

## Checks run in Stage 3 (cloud workspace: PHP 8.3.6, MariaDB 10.11.14, Apache 2.4)

| Check | Result |
|---|---|
| `php bin/lint.php` | Pass, 0 errors |
| ESLint on all app JavaScript (unused and undefined names) | Pass |
| PHPUnit unit (KPI worked example and edge cases, 16 alert rules, money, validation, CSV guard, SQL splitter, permission matrix, route guards) | Pass |
| PHPUnit integration (login, redirect, lockout, IP throttle, CSRF, session fixation, forced password change, IDOR 404, field leakage for Site Engineer and Client, approval, sync idempotency, budget supersession, separation of duties, expenditure confirm and void, payments, exports, thresholds, users, settings, SMS caps, backup and restore verify) | Pass, 88 tests |
| Browser smoke at 360 px, six roles, 44 views, light and dark, PHP server and Apache with /lubie-dashboard base path | 0 console errors, 0 failed requests, no horizontal scroll |
| Offline: capture while offline, encrypted outbox, reload offline from service worker, auto sync on reconnect | Pass (Chromium) |
| Apache .htaccess: .env, src, config, vendor, database, docs blocked | Pass (403) |
| Least-privilege app account: all write paths, backup download | Pass |
| migrate on empty DB, upgrade 0.2.0 to 0.3.0, setup:first-admin refuses a second admin, seed:demo refused in production | Pass |

## Stage 4 (Test) delivered

- `docs/TEST_REPORT.md`: environments, results, suites, requirement coverage, OWASP mapping, accessibility, performance, compatibility, usability protocol T1 to T5, defect log D-01 to D-15, open items O-1 to O-6.
- New tests: SecurityProbeTest (9), ProjectScheduleTest (5). Total 102 tests, 921 assertions, line coverage 82.3 %.
- `php bin/console.php doctor`: read-only set-up check for XAMPP.
- Fixes: contrast, keyboard-scrollable tables, input names, Chart.js loaded on demand (dashboard 3.2 s to 2.1 s on throttled mobile), clear message for wrong DB credentials, completed projects closed to new activities (already enforced; now tested).

## Stage 5 (GitHub) prepared

- `.github/workflows/ci.yml`: PHP 8.2 and MariaDB 10.4.32 service, composer validate and audit, PHP lint, test database build, doctor, PHPUnit with coverage, JavaScript lint.
- `.github/dependabot.yml`, `SECURITY.md`, `package.json` and `eslint.config.mjs` (development only).
- History scanned before push: no .env, keys, backups or dumps. The only fixed password is the documented demo password in `database/seed_demo.sql` (synthetic accounts only).
- `C:\xampp\htdocs\lubie-dashboard` is now the git working copy (branches main and iteration-1, remote origin set). CI has not run yet: it starts on the first push.

## Stage 6 (Deploy) prepared

- `Dockerfile`, `docker/` (Apache site, production php.ini, entrypoint), `.dockerignore`, `render.yaml`.
- `docs/DEPLOYMENT_OPTIONS.md` (comparison with sources) and `docs/DEPLOY.md` (owner steps).
- New: `setup:app-account` console command, `DB_SSL_CA_PEM`, `TRUSTED_PROXY_HOPS`, CI container job, manual Backup workflow.
- Verified on MySQL 8.0 over TLS (103 tests) and under the container's Apache layout. Docker image itself is built only in CI.
- Local XAMPP: doctor showed every PHP check OK; the database refused `lubie_app` (account missing or password mismatch). `.env.example` had a password typed into it; restored to the blank template, nothing reached GitHub. php.ini line 942 reverted because openssl was already loaded at line 984.

## Not run yet

- On your XAMPP: PHP 8.2.12 and MariaDB (10.4 expected, not confirmed). The app was not run on your computer yet.
- Your php.ini has `extension=openssl` commented out: backups stop with a clear message until you enable it (needs your approval).
- Native date pickers follow the browser language (US order in the test browser).
- Real SMS: sandbox only. No message has been sent to a phone.
- MySQL 8 import: Stage 6.

## Open defects

None open. Open environment items O-1 to O-6 in docs/TEST_REPORT.md.

## Decisions awaiting approval

1. php.ini: enable openssl, set expose_php=Off and display_errors=Off (see docs/INSTALL_XAMPP.md step 3).
2. Assumptions marked "Yes" in `docs/stage1/03_Assumptions_Register.md`.
3. Stage 5: push branch iteration-1 to PRINCE2025EASY/LUBIE-DASHBOARD (public).

## Next steps

1. Owner runs `setup:app-account --owner=root` locally, then doctor. 2. Owner pushes from C:\xampp\htdocs\lubie-dashboard; CI must be green. 3. Owner follows docs/DEPLOY.md. 4. Live checks, tag v0.5.0, Stage 7.
