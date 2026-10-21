# PROJECT_STATE

Last updated: 21/10/2026 (Africa/Accra)

## Current stage

Stage 2, Analysis and design: delivered, waiting at the gate for "proceed". Next: Stage 3 (Build, Iteration 1).

## Decisions recorded

| Date | Decision |
|---|---|
| 21/10/2026 | Director has no audit-trail access. |
| 21/10/2026 | Site engineers may create and edit activities on assigned projects (never cost fields; PM sets budgeted cost and milestones). |
| 21/10/2026 | Clients receive in-app notices only; no SMS to clients. |
| 21/10/2026 | GitHub repository exists: PRINCE2025EASY/LUBIE-DASHBOARD, PUBLIC, empty, default branch main. Not touched. |
| 21/10/2026 | Offline priority (Q1) not answered: default Should, Iteration 2, applies. |

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

## Not run yet

- Import on your XAMPP MariaDB (10.4 expected): waiting for `env_check_result.txt` and your import test.
- MySQL 8 import (production engine): Stage 6.
- Opening the diagrams in diagrams.net itself: previews used mxGraph 4.2.2, an approximation. Please open one file to confirm.

## Open defects

None (no application code yet).

## Decisions awaiting approval

1. Offline mode: confirm Should Have, Iteration 2 (or Must).
2. Repository visibility: LUBIE-DASHBOARD is public; master prompt asked for private. Decide before Stage 5 push.
3. Assumptions marked "Yes" in `docs/stage1/03_Assumptions_Register.md` (thresholds, caps, timeouts, password rules).
4. Run `tools/env_check.ps1` and paste the result (PHP and MariaDB versions decide the PHP target).

## Next steps (Stage 3, Iteration 1)

Project skeleton in XAMPP (with your approval for `C:\xampp\htdocs\lubie-dashboard`), Composer, configuration and error handling, DB sessions and unified login, roles and permissions, projects, activities, milestones, progress and approval, KPI engine and dashboards, budgets and expenditure, valuations and payments, alerts, audit, reports, backup and restore; PHPUnit tests for KPI and alert services.
