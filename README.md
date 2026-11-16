# Lubie Construction Works: Project Monitoring Dashboard

![CI](https://github.com/PRINCE2025EASY/LUBIE-DASHBOARD/actions/workflows/ci.yml/badge.svg)

A mobile-first web dashboard for near-real-time construction project monitoring, built for
Lubie Construction Works (Upper West Region, Ghana) 

Site staff record progress, materials, labour and equipment from a phone, even without signal.
Project Managers approve the work. The system calculates completion, schedule and cost indicators
the same way for everyone, raises alerts, and shows each role only what it needs.

Version 0.7.0 (16/11/2026). Runs on XAMPP (Windows) and as a Docker container on Render with an
Aiven MySQL database.

---

## Contents

1. [What the system does](#1-what-the-system-does)
2. [Roles and what each one sees](#2-roles-and-what-each-one-sees)
3. [Indicators and formulas](#3-indicators-and-formulas)
4. [Alert rules](#4-alert-rules)
5. [Architecture](#5-architecture)
6. [Folder structure](#6-folder-structure)
7. [Run it locally on XAMPP](#7-run-it-locally-on-xampp)
8. [Deploy online (Render and Aiven)](#8-deploy-online-render-and-aiven)
9. [Configuration (environment variables)](#9-configuration-environment-variables)
10. [Command-line tool](#10-command-line-tool)
11. [API overview](#11-api-overview)
12. [Database](#12-database)
13. [Security](#13-security)
14. [Offline use](#14-offline-use)
15. [Testing](#15-testing)
16. [Troubleshooting](#16-troubleshooting)
17. [Limitations and next steps](#17-limitations-and-next-steps)
18. [Documents](#18-documents)

---

## 1. What the system does

| Area | Functions |
|---|---|
| Access | One login page for all roles; the server sends each user to their dashboard. Password change, admin reset with forced change, lockout after 5 failures, automatic logout after 20 idle minutes. |
| Projects and schedule | Projects, team assignments, activities with planned dates, budgeted cost and dependencies, milestones with due and achieved dates. |
| Progress | Site Engineer enters % complete with a delay reason; the Project Manager approves or returns it with a reason. Only approved progress counts. Work stoppage or a safety incident raises a critical alert. |
| Indicators | Completion % (cost-weighted), planned %, PV, EV, AC, SV, SPI, CV, CPI, budget consumed, schedule status per activity, days since update, days to planned finish. KPI cards show the formula. |
| Charts | Planned versus earned value over time; budget versus expenditure by category. |
| Cost and payments | Budget versions with Director approval (the requester cannot approve their own), expenditure with a confirmation step and voiding instead of deleting, valuations, certificates and part payments. |
| Labour, materials, equipment | Materials received and used with store balance and reorder level; labour attendance per day; equipment hours and status (working, faulty, idle, off site); material requests (requested, approved, delivered, cancelled) where delivery adds stock automatically. |
| Alerts | Sixteen rules, in-app bell with read and acknowledge, automatic clearing, SMS adapter (sandbox until a provider is set up). |
| Overview | Director portfolio of all projects; client one-page summary with PDF. |
| Reports | PDF and CSV: project status, delayed activities, milestones, expenditure, budget, cost variance, valuations and payments, alerts, labour/materials/equipment, client summary, audit trail. |
| Records | Append-only audit trail of logins, changes, approvals, exports, backups and refused access. |
| Recovery | Encrypted backups (AES-256-GCM) and a restore check that compares row counts and indicators. |
| Field use | Installable web app; works offline for viewing and for recording progress and resources; sends entries when the connection returns. |
| Display | Light and dark theme; layouts from 360 px phones to desktops; British English, GH₵, Africa/Accra time, DD/MM/YYYY, +233 numbers. |

## 2. Roles and what each one sees

| Function | Admin | Director | Project Manager | Quantity Surveyor | Site Engineer | Client |
|---|---|---|---|---|---|---|
| Users, settings, backups, audit trail | Yes | No | No | No | No | No |
| Projects and team | Yes | View all | Assigned | No | No | No |
| Activities and milestones | No | View all | Edit (assigned) | View | Edit, no cost | Milestone timeline |
| Enter progress | No | No | Yes | No | Yes | No |
| Approve or return progress | No | No | Yes | No | No | No |
| Budget, expenditure, valuations, payments | No | View all | View, request revision | Record | No | Summary only |
| Approve budget revisions, company thresholds | No | Yes | No | Propose | No | No |
| Materials, labour, equipment | No | View with values | Record, set rates, decide requests | View with values | Record, request (no prices) | No |
| Indicators, charts, alerts | Counts only | All projects | Assigned | Assigned | Assigned, no money | Own summary |

Rules enforced on the server for every request:

- Every route declares a permission; anything not allowed is refused (deny by default).
- Every project, activity or record ID is checked against the user's assignments. A record from
  another project returns "not found" and the attempt is written to the audit trail.
- Money fields (rates, values, budgets, costs) are removed before data leaves the server for
  Site Engineers and Clients.

## 3. Indicators and formulas

All indicators come from one service (`src/Services/KpiService.php`), so dashboards, reports and
alerts always agree. Money is calculated in whole pesewas, never floating point.

| Indicator | Formula | Not shown when |
|---|---|---|
| BAC | Sum of budgeted cost of active activities | No priced activity (duration weighting is used and labelled) |
| EV | Sum of budgeted cost x approved % complete | BAC = 0 |
| Completion % | EV / BAC x 100 | No activities |
| PV | Sum of budgeted cost x planned fraction (linear between planned start and finish) | BAC = 0 |
| AC | Active expenditure up to the status date | Never |
| SV, SPI | EV - PV; EV / PV | SPI when PV = 0 |
| CV, CPI | EV - AC; EV / AC | CPI when AC = 0 |
| Budget consumed | AC / current approved budget x 100 | No approved budget |
| Store balance | Material received - material used | Never |

Worked example held by a unit test: BAC 200,000, PV 98,000, EV 70,000, AC 80,000 gives completion
35.00%, SV -28,000.00, SPI 0.71, CV -10,000.00 and CPI 0.88.

## 4. Alert rules

Rules run after every relevant save and on the first dashboard visit of the day. One open alert
per condition; it clears by itself when the condition ends. Thresholds are settings, not code.

| Rule | Condition | Severity | SMS (when enabled) |
|---|---|---|---|
| A1 | Activity past planned finish, below 100% | High | Director, PM if delay > 14 days |
| A2 | Activity past planned start, no progress | Medium | No |
| A3 | Milestone due within 7 days | Information | No |
| A4 | Milestone overdue (client gets an in-app notice) | High | PM |
| A5 | Actual cost at 80% of budget | Warning | No |
| A6 | Actual cost above 100% of budget | Critical | No |
| A6b | Actual cost above 110% of budget | Critical | Director, PM, QS |
| A7 | CPI below 0.90 | Warning | No |
| A8 | SPI below 0.90 | Warning | No |
| A9 | Certificate unpaid past the payment period | High | QS, PM |
| A10 | Material below its reorder level | Information | No |
| A11 | No approved progress for 7 days | Warning | No |
| A12 | Budget revision waiting for the Director | High | Director |
| A13 | Progress waiting for PM approval | Information | No |
| A14 | Equipment reported faulty | Information | No |
| A15 | Work stoppage or safety incident reported | Critical | Director, PM, QS |


## 5. Architecture

Three tiers:

- **Browser**: one page shell per role (`templates/`), JavaScript modules (`public/assets/js/`),
  Bootstrap 5.3.3, Bootstrap Icons 1.11.3, Chart.js 4.4.4 loaded only when a chart is drawn.
  No CDN: every library is served from the app so it works offline.
- **Application**: `public/index.php` is the only entry point. The router (`config/routes.php`)
  checks each route's guard, then thin controllers call services that hold the rules;
  repositories hold the SQL. A small container wires the classes.
- **Data**: MariaDB 10.4+ or MySQL 8 through PDO with prepared statements only. Sessions are
  stored in the database, so the web server keeps no state.

Refresh: each dashboard asks the server every 60 seconds (while the page is visible) whether the
project changed. If it did, the page redraws without a full reload. Every view shows when the data
last changed.

Design decisions, diagrams and the full API are in `docs/DESIGN_SPECIFICATION.md`,
`docs/diagrams/` and `docs/API_REFERENCE.md`.

## 6. Folder structure

```
lubie-dashboard/
  public/              Web root: index.php, .htaccess, sw.js, manifest, assets (css, js, img, vendor)
  src/                 PHP classes (namespace Lubie\)
    Controllers/       HTTP handlers, one per area
    Services/          Business rules: KPI, alerts, progress, cost, resources, sync, backup, reports
    Repositories/      SQL per area
    Alerts/Rules/      One class per alert rule (A01 to A15)
    Security/          Sessions, CSRF, authoriser
    Http/, Db/, Sms/, Reports/, Support/, Validation/, Views/
  config/              routes.php (guards), permissions.php (role matrix), bootstrap.php
  templates/           Layouts, login page, role page shells
  database/            schema.sql, migrations/, seed_production.sql, seed_demo.sql, privileges_*.sql
  bin/                 console.php (commands), lint.php
  tests/               Unit/ and Integration/ (PHPUnit)
  docker/, Dockerfile, render.yaml   Container image and Render blueprint
  docs/                Specification, manuals, guides, test report, diagrams
  tools/               Generators, live smoke test, reference KPI check
  demo-setup.bat, demo-start.bat, cloud-load.bat   One-click helpers for Windows
```

`database/seed_lubie.sql` (the company presentation data) and `.env` are deliberately not in
the repository.

## 7. Run it locally on XAMPP

Tested with XAMPP for Windows: Apache 2.4.58, PHP 8.2.12, MariaDB 10.4.32.

1. Copy the project folder to `C:\xampp\htdocs\lubie-dashboard`.
2. In `C:\xampp\php\php.ini` make sure `extension=openssl` is enabled (needed for backups).
3. Start **Apache** and **MySQL** in the XAMPP Control Panel.
4. Double-click **demo-setup.bat**:
   - press Enter for the MySQL root password (XAMPP default is empty);
   - type `RESET` if the database already has tables;
   - choose one password (10+ characters, letters and digits) for all accounts.
   It builds the database `lubie_dashboard`, loads the data, creates the restricted `lubie_app`
   account with a random password and writes `.env`. The window ends with a check where every
   line should start with OK.
5. Double-click **demo-start.bat**, or open http://localhost/lubie-dashboard

Accounts after set-up: `admin`, `director`, `pm1`, `pm2`, `qs1`, `qs2`, `se1`, `se2`, `se3`,
`client1`, `client2`, `client3` (`se4` is deactivated to show that old accounts cannot log in).
All use the password chosen in step 4.

## 8. Deploy online (Render and Aiven)

| Part | Service |
|---|---|
| Web | Render free web service, Docker, Frankfurt, deployed from the `main` branch (`render.yaml`) |
| Database | Aiven free MySQL 8, TLS required, database `defaultdb` |

1. **Database**: create the Aiven MySQL service and download its CA certificate to
   `storage\aiven-ca.pem`. Create the schema and the restricted account
   (`php bin/console.php migrate`, then `setup:app-account --owner=avnadmin --host=% --require-ssl`).
2. **Data**: with the Aiven settings saved in `.env.before-demo`, double-click **cloud-load.bat**.
   It asks for the Aiven owner password at the prompt, replaces the data after you type `RESET`,
   and renews the app account's grants without changing its password.
3. **Code**: `git push origin main`. Render builds the image from the `Dockerfile`.
4. **Secrets**: in Render, set `DB_HOST`, `DB_PORT`, `DB_PASSWORD` and `DB_SSL_CA_PEM` (paste the CA
   file's text). Render generates `APP_KEY`, `BACKUP_ENCRYPTION_KEY` and `BACKUP_TOKEN`.
5. **Check**: open `https://<your-service>.onrender.com/health` (expect `{"status":"ok","db":"ok"}`),
   then run `php tools/verify_live.php https://<your-service>.onrender.com`.

The free service sleeps after 15 idle minutes and takes about a minute to wake. Before a
demonstration run `php tools/verify_live.php <address> --warm`. Full guide: `docs/DEPLOY.md`.

Updating a live deployment: push to `main` and Render redeploys. Database changes are applied
with `php bin/console.php migrate` using the owner account; version 0.7.0 needs no schema change.

## 9. Configuration (environment variables)

Copy `.env.example` to `.env` for local use. On a host, set the same names in its secret settings.
Never commit `.env` or paste its values into chat or e-mail.

| Variable | Purpose |
|---|---|
| `APP_ENV` | `local`, `staging` or `production` |
| `APP_DEBUG` | `1` shows PHP errors (local only) |
| `APP_KEY` | Random secret, 32+ characters |
| `APP_BASE_PATH` | `/lubie-dashboard` on XAMPP; empty at a domain root |
| `APP_FIXED_NOW` | Testing only: freeze the clock |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | Restricted application account, never root |
| `DB_TEST_NAME` | Test database, must end in `_test` |
| `DB_SSL_CA` or `DB_SSL_CA_PEM` | CA certificate path, or its text, for TLS to a cloud database |
| `DB_SSL_VERIFY`, `DB_CONNECT_RETRIES` | Verify the server certificate; retries on start-up |
| `SESSION_SECURE_COOKIE` | `1` on HTTPS, `0` on http://localhost |
| `TRUSTED_PROXY_HOPS` | Number of proxies in front of the app (Render: 1) |
| `SMS_PROVIDER`, `SMS_BASE_URL`, `SMS_API_KEY`, `SMS_SENDER_ID` | `sandbox` logs only; `arkesel` sends |
| `BACKUP_ENCRYPTION_KEY` | 32+ characters; keep an offline copy or backups cannot be read |
| `BACKUP_TOKEN` | Token for the scheduled backup endpoint |

## 10. Command-line tool

Run from the project folder: `php bin/console.php <command>` (on XAMPP: `C:\xampp\php\php.exe`).

| Command | What it does |
|---|---|
| `doctor [--env-file=.env.before-demo]` | Read-only check of PHP, extensions, settings, database version, 31 tables and the append-only audit trail. Shows no secrets. |
| `migrate` | Create the schema in an empty database or apply new migrations |
| `setup:first-admin` | Create the first Administrator (refuses if one exists) |
| `setup:app-account --owner=root [--host=%] [--require-ssl]` | Create the restricted application account with per-table grants |
| `demo:install --owner=root` | Local only: rebuild `lubie_dashboard` with the presentation data and write `.env` |
| `cloud:load-data --owner=avnadmin` | Replace the cloud database's data with the presentation data |
| `seed:demo --yes` | Load synthetic demo data (refused in production) |
| `test-db:reset --yes` | Rebuild the test database |
| `backup:create --out=FILE` | Write an encrypted backup outside `public/` |
| `restore:verify --file=FILE --target=DB` | Restore into an empty database and compare counts and indicators |
| `alerts:run` | Evaluate alerts for all active projects (for a daily task) |
| `sms:retry` | Retry SMS messages that are due |

## 11. API overview

JSON API under `/api/v1`, 93 routes, same-origin only. Every state-changing request needs the
`X-CSRF-Token` header. Errors use one format: `{"error":{"code","message","fields","request_id"}}`.

| Area | Main endpoints |
|---|---|
| Authentication | `POST /auth/login`, `POST /auth/logout`, `GET /auth/me`, `POST /auth/password` |
| Projects | `GET/POST /projects`, `GET/PATCH /projects/{id}`, assignments |
| Schedule | `/projects/{id}/activities`, `/projects/{id}/milestones`, `/activities/{id}` |
| Progress | `POST /activities/{id}/progress`, `GET /projects/{id}/progress-queue`, `POST /progress/{id}/approve` or `/return` |
| Cost | `/projects/{id}/budgets`, `/budgets/{id}/approve`, `/projects/{id}/expenditures`, `/projects/{id}/certificates` |
| Resources | `GET/POST /projects/{id}/resources`, `PATCH /resources/{id}`, `POST /resources/{id}/usage`, `POST /resources/{id}/equipment-status` |
| Material requests | `POST /projects/{id}/material-requests`, `POST /material-requests/{id}/approve`, `/cancel`, `/deliver` |
| Dashboards | `GET /projects/{id}/dashboard`, `GET /dashboard/portfolio`, `GET /client/summary`, `GET /changes` |
| Alerts | `GET /alerts`, `POST /alerts/{id}/read`, `POST /alerts/{id}/acknowledge` |
| Offline | `POST /sync` (progress, resource usage, equipment status) |
| Reports | `GET /reports`, `GET /reports/{type}?project_id=&format=pdf|csv` |
| Health | `GET /health` |

Full list with the role allowed for each route: `docs/API_REFERENCE.md`.

## 12. Database

31 tables in third normal form: identity and sessions, projects and assignments, activities and
dependencies, milestones, progress records, budgets, expenditure, certificates and payments,
resources, resource usage, material requests, notifications and recipients, SMS log, audit log,
backup log, settings, offline sync requests and migrations. Integrity is enforced in the database
by 57 foreign keys, 27 CHECK constraints and unique keys

The application connects with a restricted account: read and write on business tables, read and
insert only on `audit_logs`, read only on `schema_migrations`, no schema changes. Column details:
`docs/DATA_DICTIONARY.md`; diagram: `docs/diagrams/01-database-erd.drawio`.

## 13. Security

Designed against the OWASP Top 10 (2021):

- Passwords hashed with bcrypt (cost 12); one generic login error; lockout after 5 failures for
  15 minutes; 20 attempts per IP per 15 minutes.
- Sessions in the database; HttpOnly, Secure and SameSite cookie with the `__Host-` prefix online;
  new session ID at login; 20-minute idle and 12-hour absolute timeout.
- CSRF token on every change; prepared statements only; escaped output; strict Content Security
  Policy with no inline script; CSV formula guard.
- HTTPS with HSTS online; TLS to the database; secrets only in environment variables.
- Encrypted backups (AES-256-GCM); encrypted offline queue (AES-GCM, key tied to the login session).
Report a security problem privately (see `SECURITY.md`).

## 14. Offline use

Open the site once with a connection; on a phone choose "Install app" or "Add to Home screen".
Afterwards:

- viewed pages and the latest data open without signal;
- progress, labour attendance, materials received or used, equipment hours and equipment status
  can be recorded offline; they are encrypted on the phone and sent automatically when the
  connection returns ("Saved on this phone");
- the server checks every offline entry exactly like a direct one; a repeated upload never
  creates a duplicate;
- approvals, budgets, expenditure, payments and administration need a connection.

## 15. Testing
    composer install
    php bin/console.php test-db:reset --yes
    vendor/bin/phpunit
    php bin/lint.php
    npm install && npm run lint

Current result: 116 tests, 1,069 assertions, all passing on MariaDB 10.11 and on MySQL 8.0 over
TLS. Browser checks of all six roles at 360 px in light and dark themes show no errors.
Details: `docs/TEST_REPORT.md`.

## 16. Troubleshooting

| Symptom | Fix |
|---|---|
| "Not Found" in the browser | Start Apache; use http://localhost/lubie-dashboard |
| "The database refused the connection settings" | Start MySQL, or run demo-setup.bat again |
| Old page design or a button not responding | Press Ctrl+Shift+R once to load the new files |
| Backups say openssl is missing | Enable `extension=openssl` in php.ini and restart Apache |
| Forgot the presentation password | Run demo-setup.bat again and type RESET |
| Render page takes a minute | The free service was asleep; it wakes by itself |
| Dates show as MM/DD in date pickers | The browser language decides the picker; set it to English (UK) |
| Anything else | Run `php bin/console.php doctor` and read the lines marked FIX |

## 17. Limitations and next steps

- Built and evaluated for one company; schedules and amounts in the presentation data are illustrative.
- Figures are only as current as the data staff enter; the last-updated stamp shows how fresh they are.
- SMS works in sandbox mode only until a paid provider is configured.
- Offline mode was tested in Chromium; iPhone Safari is untested.
- Free hosting sleeps after 15 idle minutes.
- Planned: progress photos (FR-39). Out of scope for this version: GPS, predictive analytics,
  Full list: `docs/KNOWN_LIMITATIONS.md`.

## 18. Documents

| Document | Content |
|---|---|
| `docs/stage1/` | Findings, issues, assumptions, MoSCoW priorities, requirements, traceability matrix |
| `docs/DESIGN_SPECIFICATION.md` | Architecture decisions, permissions, security, API, KPI and alert design, UI |
| `docs/DATA_DICTIONARY.md` | Every table and column |
| `docs/USE_CASE_SPECIFICATIONS.md` | Fifteen use cases |
| `docs/diagrams/` | ERD, structure chart, DFDs, sequence, activity, state, component and use case diagrams |
| `docs/USER_MANUAL.md` | Guide for each role |
| `docs/TECHNICAL_GUIDE.md`, `docs/API_REFERENCE.md` | Code structure and the API routes |
| `docs/INSTALL_XAMPP.md`, `docs/PRESENTATION_GUIDE.md` | Local installation and the demonstration script |
| `docs/DEPLOY.md`, `docs/DEPLOYMENT_OPTIONS.md`, `docs/OPERATIONS_GUIDE.md` | Online deployment, backup, restore, security checklist |
| `docs/TEST_REPORT.md`, `docs/TRACEABILITY_PACK.csv` | Test evidence and requirement status |
| `docs/KNOWN_LIMITATIONS.md` | Limitations, open items, future work |
| `PROJECT_STATE.md` | Development log, decisions and checks |

---

Licence: proprietary, for academic assessment and Lubie Construction Works. All rights reserved.
