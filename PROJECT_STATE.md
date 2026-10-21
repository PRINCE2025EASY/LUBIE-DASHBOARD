# PROJECT_STATE

Last updated: 21/10/2026 (Africa/Accra)

## Current stage

Stage 3, Build (Iteration 1): delivered, waiting at the gate for "proceed". Next: Stage 4 (Test).

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

## Not run yet

- On your XAMPP: PHP 8.2.12 and MariaDB (10.4 expected, not confirmed). The app was not run on your computer yet.
- Your php.ini has `extension=openssl` commented out: backups stop with a clear message until you enable it (needs your approval).
- Native date pickers follow the browser language (US order in the test browser).
- Real SMS: sandbox only. No message has been sent to a phone.
- MySQL 8 import: Stage 6.

## Open defects

None known. Stage 4 will run the full test plan.

## Decisions awaiting approval

1. php.ini: enable openssl, set expose_php=Off and display_errors=Off (see docs/INSTALL_XAMPP.md step 3).
2. Assumptions marked "Yes" in `docs/stage1/03_Assumptions_Register.md`.
3. Stage 5: push branch iteration-1 to PRINCE2025EASY/LUBIE-DASHBOARD (public).

## Next steps (Stage 4, Test)

Full functional and security test plan with evidence, usability tasks (T1 to T5), accessibility check, performance timing, MariaDB 10.4 check on your XAMPP, defect log.
