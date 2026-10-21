# Design Specification

Lubie Construction Works Real-Time Project Monitoring Dashboard
Version 0.2.0, 21/10/2026. Stage 2 (Analysis and design). Status: for approval.

Companion files: `docs/stage1/05_Master_Requirements.md` (requirement IDs), `docs/stage1/06_Traceability_Matrix.csv`, `docs/DATA_DICTIONARY.md` (generated from `database/schema.sql`), `docs/diagrams/*.drawio` (eight diagram files).

## 1. Scope

The system is a web-based, mobile-first monitoring dashboard for one construction company. It covers projects, activities, milestones, progress with PM approval, cost-weighted KPIs and earned value, budgets with Director-approved revisions, expenditure, valuations and payments, labour, materials, material requests and equipment, rule-based alerts with SMS for critical events, reporting, audit, backup and recovery, and offline capture for site staff (Iteration 1, Must).

Near-real-time means: a saved entry is visible at once to the user who saved it, and to other users within one polling interval (60 s by default). The system does not sense site events; timeliness depends on staff entering data. Every dashboard and KPI view shows "Last updated".

Out of scope (Won't Have): WhatsApp and e-mail notification, native app, GPS, IoT, BIM, predictive analytics, EAC or forecast final cost, accounting or ERP integration, quality, inspection and safety module, multi-company use (W-01 to W-12).

Iterations:

| Iteration | Content |
|---|---|
| 1 (MVP, Must) | FR-01 to FR-16, FR-20 (A1-A7, A9, A11-A13, A15), FR-23 to FR-32, FR-35, FR-36, FR-41; NFR-01 to NFR-07, NFR-11 to NFR-13 |
| 2 (Should, then first Could) | FR-17 and A8, FR-18, FR-21, FR-33, FR-38 offline, NFR-08 to NFR-10 refinements, remaining FR-26 reports; then FR-22 SMS |
| 3 (Could, if time allows) | FR-19 and A14, FR-34 (A10), FR-37 (merged into FR-38 if offline ships), FR-39, FR-40 |

Decisions recorded on 21/10/2026: Director has no audit-trail access; site engineers may create and edit activities (never cost fields); clients receive in-app notices only, never SMS. Second decision set, same day: offline mode is Must, Iteration 1 (FR-38); the repository stays public for now; the project lives in C:\xampp\htdocs\lubie-dashboard; the Administrator, not the Director, sees the audit trail.

## 2. Architecture and technology decisions

Three-tier layered architecture: presentation (browser), application (PHP REST-style JSON API behind a front controller), data (MySQL/MariaDB through PDO). See `07-component-diagrams.drawio`.

| ID | Decision | Reason | Alternatives rejected |
|---|---|---|---|
| AD-01 | Plain PHP 8.1+ with strict types, PSR-4, PSR-12, small hand-written router; Composer for a few pinned libraries | Thesis stack; one developer; runs on XAMPP and any PHP container; no framework to learn or maintain | Laravel or Symfony (heavier, more to justify for an 8-week MSc build) |
| AD-02 | Server-rendered page shells per role plus vanilla JS modules calling `/api/v1` | API-first, field-level filtering in one place, no Node build step | SPA framework (build step, larger download) |
| AD-03 | Bootstrap 5.3 (colour modes for dark/light) and Chart.js 4, vendored into `public/assets/vendor` with pinned versions | Thesis stack; works offline once cached; no CDN dependency on poor networks | CDN delivery (fails offline) |
| AD-04 | MySQL/MariaDB-compatible schema, portable SQL through PDO; engine-specific SQL isolated in `src/Adapters/Db` | One schema for XAMPP and production | PostgreSQL first (only if the host forces it; Stage 6 decides) |
| AD-05 | Sessions in the database via a custom `SessionHandlerInterface` | Stateless container; sessions survive restarts and spin-down | File sessions (lost on ephemeral disk) |
| AD-06 | Polling with a light "changed since" endpoint, not WebSockets | Works on shared and free hosts; data change at the pace of manual entry | WebSockets or SSE (long-lived connections, sleep on free tiers) |
| AD-07 | Alerts evaluated on save and on the first dashboard request of each day | No cron on free tiers | Cron or worker (not available or not free) |
| AD-08 | Strategy pattern: one class per alert rule implementing `AlertRule` | Add a rule without touching others (open/closed) | One large switch statement |
| AD-09 | SMS behind `SmsGateway` interface with `SandboxSmsGateway` (log only) and `HttpSmsGateway` (Arkesel-compatible), chosen by a factory from environment | Testable; provider can change; no credentials in code | Hard-coded provider calls |
| AD-10 | Dompdf (pinned) for PDF, streamed; print stylesheet fallback; CSV streamed with formula-injection guard | Thesis default; no files kept on disk | wkhtmltopdf (binary dependency) |
| AD-11 | Offline: service worker (app shell and read cache) plus IndexedDB outbox, encrypted with Web Crypto using a per-session key; idempotent `POST /api/v1/sync` | Answers F2 and SE-01, SE-04 without a native app | Native app (W-02) |
| AD-12 | Configuration only from environment variables (`.env` for local XAMPP only) | 12-factor; secrets out of Git | Config files with secrets |
| AD-13 | Backups as encrypted, compressed logical dumps produced by PHP (no shell `mysqldump` dependency on the host), streamed to the caller; local XAMPP uses `mysqldump` | Works on hosts without shell tools; nothing stored on server disk | Server-side backup folder (ephemeral disk) |

Production PHP and DB versions are not assumed. Target = lower of your XAMPP PHP and the host's PHP, minimum 8.1 (pending `env_check_result.txt`). Schema tested on MariaDB 10.11 in Stage 2; XAMPP's MariaDB 10.4 import is still to run on your computer.

## 3. Repository structure

```
lubie-dashboard/
  public/              index.php (front controller), .htaccess, assets/ (css, js modules, vendor), sw.js, manifest.webmanifest
  src/
    Controllers/       thin HTTP handlers, one per resource
    Services/          business rules: KpiService, AlertService, ProgressService, BudgetService, PaymentService, ...
    Domain/            value objects and enums: Money, Percent, ScheduleStatus, Role, Permission
    Repositories/      PDO data access, one per aggregate
    Middleware/        Session, Auth, Csrf, Rbac, ProjectScope, RateLimit, SecurityHeaders
    Alerts/Rules/      A01ActivityDelayed ... A15StoppageReported (strategy classes)
    Adapters/          Db (engine specifics), Sms (Sandbox, Http), Pdf, Clock
    Validation/        field validators and request schemas
    Http/              Router, Request, Response, JsonError, DTO filters
  config/              routes.php, permissions.php (permission matrix as data)
  database/            schema.sql, migrations/, seed_production.sql, seed_demo.sql, remove_demo.sql, privileges_*.sql
  bin/console.php      setup:first-admin, migrate, backup:local, restore:verify
  tests/               Unit/, Integration/, Functional/, fixtures/
  storage/             temporary only (outside web root; never relied on)
  docs/                specification, data dictionary, diagrams/, guides
  tools/               env_check.ps1, reference_kpi_check.py, generators
  Dockerfile, .dockerignore, docker-compose.yml, composer.json, .env.example, README.md, PROJECT_STATE.md
```

## 4. Design-principles traceability

| Principle | Where applied | How |
|---|---|---|
| Separation of concerns, three tiers | `public/` vs `src/` vs DB | Browser never sees SQL; controllers call services; services call repositories |
| Thin controllers | `src/Controllers` | Parse request, call one service method, return DTO; no SQL, no rules |
| Service layer | `src/Services` | All business rules (approval, budget supersession, KPI, alerts) live here and are unit-tested |
| Repository | `src/Repositories` | All SQL in one place per aggregate; prepared statements only |
| Query service (read side) | `DashboardService`, `AlertQueryService`, `AuditQueryService`, `ThresholdService` | Read-only reporting queries that join many aggregates live in these services, not in repositories (a light CQRS split). Exception: ThresholdService and the alert writer also write their own small tables directly. All SQL uses prepared statements |
| Single responsibility | Alert rules, middleware | One class per alert rule; one middleware per concern |
| Open/closed | `Alerts/Rules`, `Adapters/Sms` | New rule or provider = new class registered in config |
| Liskov, interface segregation | `SmsGateway`, `Clock`, `AlertRule` | Small interfaces; sandbox and HTTP gateways interchangeable in tests |
| Dependency inversion | Service constructors | Services depend on interfaces (repositories, clock, gateway); a small container wires them |
| DRY | `KpiService` | Dashboards, reports and alerts call the same KPI service |
| KISS, YAGNI | Whole design | No framework, no queue, no WebSockets; extension points only for offline analytics |
| Front controller and router | `public/index.php`, `Http/Router` | One entry point; central role redirect |
| Observer / event dispatch | `Events\Dispatcher` | `ProgressApproved`, `ExpenditureRecorded`, etc. trigger audit logging and alert evaluation |
| DTO with field-level filtering | `Http/Dto/*` | Each DTO declares fields per role; clients and site engineers never receive hidden fields |
| Least privilege | DB account, roles | App DB user cannot alter schema or change audit rows (verified, Section 10) |
| Deny by default, complete mediation | `RbacMiddleware`, `ProjectScopeMiddleware` | Every route needs a permission; every ID checked against assignments |
| Fail-secure | Auth and errors | Missing permission = 403; exception = generic 500 with request ID, no detail |
| Separation of duties | Budgets, progress | Requester cannot approve own revision (DB check plus service rule); QS cannot change progress; Admin has no project duties |
| Defence in depth | Validation | Client-side hints, server validation, DB constraints |
| API-first | `/api/v1` | Same API for pages, offline sync and tests |
| Configuration over code | `system_settings`, `alert_settings`, env | Thresholds, intervals, caps editable without deploy |
| Traceability | Code docblocks, tests | `@covers FR-xx` tags in tests; RTM links tests to requirements |

## 5. ISO/IEC 25010 quality targets

| Characteristic | Sub-characteristic | Target | Verified by |
|---|---|---|---|
| Functional suitability | Correctness | KPI values equal hand-calculated examples to the cent; 16 alert rules trigger, de-duplicate and clear correctly | UT-KPI, UT-ALERT, `reference_kpi_check.py` |
| Performance efficiency | Time behaviour | API summary p95 <= 2 s; page load <= 3 s on throttled "Fast 3G" with 20 concurrent users | PT-01, PT-02, PT-05 |
| Performance efficiency | Resource use | Unchanged poll response < 1 KB; first page load < 500 KB compressed; later loads mostly cached | PT-03, PT-04 |
| Compatibility | Co-existence, interoperability | Runs on XAMPP and Docker from one commit; CSV opens in Excel | Deployment check; FT-30 |
| Usability | Learnability, operability | Evaluation tasks T1 to T11 completed; progress update in one screen; touch targets >= 44 px | Evaluation; FT-11; PT-06 |
| Usability | Accessibility | WCAG 2.1 AA contrast in both themes; status by colour plus text and icon | PT-07; axe check |
| Reliability | Availability, recoverability | Clear error on every failed request; RPO 24 h; RTO 4 h demonstrated | FT-32 timed restore |
| Reliability | Fault tolerance | Offline queue survives reload; idempotent replay; polling backoff after failure | FT-36, FT-28 |
| Security | Confidentiality, integrity, accountability, authenticity | ST-01 to ST-16 pass; no hidden field in client or SE responses; audit append-only | ST suite |
| Maintainability | Modularity, testability | No PHP file over 300 lines; services unit-tested; PSR-12 lint clean | CI lint, PHPUnit |
| Portability | Adaptability, installability | Configuration only via environment; install guide reproduces on a clean XAMPP | Install test |

## 6. Roles and permission matrix (final)

Enforced in the backend for every request and in DTO field filtering. "Assigned" = projects the user is assigned to; a PM who creates a project is assigned automatically.

| Function | Admin | Director | PM | QS | Site Engineer | Client |
|---|---|---|---|---|---|---|
| Users, roles, system and SMS settings, backups | Yes | No | No | No | No | No |
| View audit trail | Yes | No | No | No | No | No |
| Create/edit projects; assign users | Yes | View all | Assigned | No | No | No |
| Create/edit activities | No | View all | Assigned | View | Assigned (no cost fields) | No |
| Create/edit milestones | No | View all | Assigned | View | View | Own project timeline |
| Enter progress (with delay reason) | No | No | Assigned | No | Assigned | No |
| Approve/return progress | No | No | Assigned | No | No | No |
| Record budget, expenditure, valuations, payments | No | View all | View | Assigned | No | No |
| Request budget revision | No | No | Assigned | Assigned | No | No |
| Approve budget revisions; set global thresholds; approve QS proposals | No | Yes | No | Propose | No | No |
| Set project thresholds within global limits | No | No | Assigned | Propose | No | No |
| Labour, materials, equipment, material requests | No | View all | Assigned (approve requests) | View | Assigned | No |
| View KPIs, charts, alerts | Portfolio counts only | All projects | Assigned | Assigned (cost focus) | Assigned (no cost data) | Own project summary; A4 notice only |
| Export reports | Audit and admin reports | All | Assigned | Assigned | No | Own one-page summary |

Field rules: SE responses never contain `budgeted_cost`, any expenditure, budget, certificate amount, `unit_rate` or `supplier_name`, CV, CPI, AC, PV or EV money values (SE sees % complete, planned %, SPI and schedule status). Client responses contain only: project name, code, location, planned and actual dates, overall % complete, milestone timeline, contract sum, total certified, total paid, outstanding, payment status counts, last updated, and A4 notices. The Administrator sees user, settings, audit and backup data and portfolio counts (number of projects by status, open alerts by severity) but no money values.

Unauthorised access: opening another role's page or calling a forbidden endpoint returns 403 and writes `audit_logs` action `forbidden`.

## 7. Security design

### 7.1 Controls

| Area | Control |
|---|---|
| Authentication | One login page; `password_hash` (bcrypt cost 12 or Argon2id if available); generic error "Username or password is incorrect"; constant-time verify against a dummy hash for unknown users; lockout after 5 failures for 15 minutes per account plus IP rate limit (20 per 15 min); no self-registration |
| First administrator | `php bin/console.php setup:first-admin` prompts for username and password on the server console, refuses if any admin exists, sets `must_change_password = 1` |
| Sessions | DB-stored; cookie `__Host-lubie` with HttpOnly, Secure (production), SameSite=Lax, Path=/; ID regenerated at login and privilege change; idle timeout 20 min; absolute lifetime 12 h; deactivated user sessions deleted at once and rejected on next request |
| Role redirect | After login the server reads role from `users.role_id`, then redirects to `roles.dashboard_path`; the role is never read from form, URL or cookie; return-to URL accepted only if it is a same-origin path the role may view |
| CSRF | Synchroniser token per session, sent in `X-CSRF-Token` header for all POST, PUT, PATCH, DELETE; login form also protected |
| Authorisation | `RbacMiddleware` (permission per route from `config/permissions.php`), `ProjectScopeMiddleware` (resolves any project, activity, record ID to its project and checks assignment), DTO field filters |
| Input | Server-side validation for type, range, length, enum, date order; PDO prepared statements only (emulated prepares off); JSON body size limit 64 KB (photo endpoint 300 KB) |
| Output | `htmlspecialchars` in templates; JS inserts text with `textContent`, never `innerHTML` with data; JSON responses with `Content-Type: application/json` |
| Headers | `Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self'; script-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'`; `X-Content-Type-Options: nosniff`; `Referrer-Policy: same-origin`; `Permissions-Policy: camera=(self), geolocation=()`; HSTS in production |
| CORS | Same origin only; no `Access-Control-Allow-Origin` header |
| Errors | Production: `display_errors=Off`, JSON error with request ID; full detail to stderr log only |
| Secrets | Environment variables; `.env` outside `public/` and in `.gitignore`; backup token and SMS key never logged |
| Uploads | Photo only: size <= 300 KB after browser compression, MIME sniffed with `finfo`, re-encoded with GD to strip metadata, stored as BLOB |
| CSV export | Cells beginning with `=`, `+`, `-`, `@`, tab or CR prefixed with `'` |
| Audit | Append-only; DB account lacks UPDATE and DELETE on `audit_logs` |
| Transport | HTTPS forced in production (redirect plus HSTS); TLS to the database when the host supports it |

### 7.2 STRIDE threat model mapped to OWASP Top 10 (2021)

| # | Threat (STRIDE) | Asset / entry point | Mitigation | OWASP | Test |
|---|---|---|---|---|---|
| T1 | Spoofing: password guessing | Login | Lockout, IP rate limit, generic errors, strong hashing | A07 | ST-08 |
| T2 | Spoofing: session hijack or fixation | Session cookie | Regenerate ID, HttpOnly, Secure, SameSite, idle timeout | A07 | ST-05, ST-14 |
| T3 | Spoofing: role claimed by client | Login redirect | Role only from DB; no role parameter accepted | A01 | ST-06 |
| T4 | Tampering: SQL injection | All inputs | Prepared statements, validation | A03 | ST-01 |
| T5 | Tampering: CSRF on approvals or payments | State-changing endpoints | Token header, SameSite | A01 | ST-04 |
| T6 | Tampering: editing approved progress or baseline budget | Progress, budgets | No update path for approved records; versioning; audit | A04 | FT-33, FT-34 |
| T7 | Tampering: offline replay or forged sync | `/api/v1/sync` | Session required, CSRF, client_uuid idempotency, full server re-validation and scope checks | A08 | ST-16 |
| T8 | Repudiation: "I never approved that" | Approvals, payments | Audit with actor, time, IP, old and new values; append-only | A09 | ST-11 |
| T9 | Information disclosure: client sees costs | Client API | DTO allow-list per role; tests assert absent fields | A01 | ST-09 |
| T10 | Information disclosure: IDOR across projects | Any ID | Project-scope middleware on every ID | A01 | ST-07 |
| T11 | Information disclosure: stack traces, `.env`, directory listing | Web server | `public/` as document root, `Options -Indexes`, production error handler | A05 | ST-12, deployment script |
| T12 | Information disclosure: stolen backup | Backup file | AES-256-GCM encryption with key from environment; delivered off host | A02 | FT-32 |
| T13 | Information disclosure: XSS stealing data | Remarks, names | Output encoding, CSP without inline script | A03 | ST-03 |
| T14 | Denial of service: polling flood or export abuse | Poll, reports | Light poll endpoint, per-user rate limits, pagination, export row caps | A04 | PT-05 |
| T15 | Elevation of privilege: PM edits global thresholds or QS approves budget | Thresholds, budgets | Permission per action; DB check requester <> approver | A01 | ST-06 |
| T16 | Elevation: deactivated user keeps access | Sessions | Sessions deleted on deactivation; status checked each request | A07 | ST-10 |
| T17 | Vulnerable components | Composer and JS vendor libraries | Minimal pinned dependencies; `composer audit` before release | A06 | Release checklist |
| T18 | SSRF via SMS URL | SMS adapter | Provider base URL fixed in env; no user-supplied URLs | A10 | Code review |
| T19 | Formula injection in CSV | Exports | Prefix guard | A03 | ST-15 |

## 8. API specification (`/api/v1`)

Conventions: JSON over HTTPS; session cookie authentication; `X-CSRF-Token` on state changes; plural resource nouns; IDs are integers; dates `YYYY-MM-DD` in JSON (shown DD/MM/YYYY in the UI); money as strings with two decimals (e.g. `"2400000.00"`) to avoid float errors; list endpoints accept `page` (default 1) and `per_page` (default 25, max 100) and return `{ "data": [...], "meta": { "page", "per_page", "total" } }`.

Single error format:

```json
{ "error": { "code": "VALIDATION_FAILED", "message": "Some fields need attention.",
             "fields": { "percent_complete": "Enter a value from 0 to 100." }, "request_id": "9f2c..." } }
```

Status codes: 200 OK, 201 Created, 204 No Content, 400 malformed, 401 not logged in or session expired, 403 forbidden (audit-logged), 404 not found (also used for IDs outside scope where existence must not leak), 409 conflict (state transition not allowed, duplicate), 422 validation, 429 rate limited, 500 generic, 503 database waking (with `Retry-After`).

Role abbreviations: AD Admin, DI Director, PM, QS, SE, CL Client. "a" = assigned projects only.

| Method | Path | Roles | Request | Response / notes |
|---|---|---|---|---|
| POST | /auth/login | public | `username`, `password`, `csrf` | 200 `{redirect}`; 401 generic; 429 locked. Regenerates session |
| POST | /auth/logout | all | - | 204; session destroyed |
| GET | /auth/me | all | - | user name, role, theme, csrf token, permissions list |
| POST | /auth/password | all | `current_password`, `new_password` | 204; clears must_change_password |
| GET | /lookups | all | - | delay reasons, expenditure categories, units, statuses |
| GET | /users | AD | filters | paged users (no hashes) |
| POST | /users | AD | username, full_name, role, phone, temporary password | 201; must_change_password = 1 |
| PATCH | /users/{id} | AD | name, phone, role, status | 200; role change audit-logged; deactivation kills sessions |
| POST | /users/{id}/reset-password | AD | temporary password | 204; forces change |
| POST | /users/{id}/unlock | AD | - | 204 |
| GET | /projects | DI all; PM, QS, SE, CL a; AD counts | status filter | paged projects, fields per role |
| POST | /projects | AD, PM | project fields | 201; creator PM auto-assigned |
| GET | /projects/{id} | DI; PM, QS, SE, CL a | - | project DTO per role |
| PATCH | /projects/{id} | AD, PM a | fields, status transition | 200; 409 invalid transition |
| POST | /projects/{id}/archive | AD, PM a | reason | 204 |
| GET | /projects/{id}/assignments | AD, PM a | - | users assigned |
| POST | /projects/{id}/assignments | AD, PM a | user_id | 201; 409 duplicate |
| DELETE | /projects/{id}/assignments/{userId} | AD, PM a | - | 204 |
| GET | /projects/{id}/activities | DI; PM, QS, SE a | - | activities with status, % complete; cost fields omitted for SE |
| POST | /projects/{id}/activities | PM a, SE a | code, name, dates, budgeted_cost (PM only) | 201; SE-supplied cost ignored and logged |
| PATCH | /activities/{id} | PM a, SE a | fields | 200 |
| POST | /activities/{id}/archive | PM a | reason | 204 |
| PUT | /activities/{id}/dependencies | PM a | predecessor_ids | 200; cycle rejected 422 |
| GET | /projects/{id}/milestones | DI; PM, QS, SE, CL a | - | milestones (client: name, due, achieved, status) |
| POST | /projects/{id}/milestones | PM a | name, due_date, activity_id | 201 |
| PATCH | /milestones/{id} | PM a | fields, achieved_date | 200 |
| GET | /activities/{id}/progress | DI; PM, QS, SE a | - | history with status |
| POST | /activities/{id}/progress | PM a, SE a | record_date, percent_complete, remarks, delay_reason_id, client_uuid | 201 status submitted; triggers A13, A15 |
| GET | /projects/{id}/progress?status=submitted | PM a | - | approval queue |
| POST | /progress/{id}/approve | PM a | - | 200; 409 if not submitted; KPIs and alerts re-evaluated |
| POST | /progress/{id}/return | PM a | return_reason | 200 |
| PUT, GET | /progress/{id}/photo | PM a, SE a (PUT); DI, PM, QS, SE a (GET) | JPEG/PNG/WebP <= 300 KB | Iteration 3 |
| GET | /projects/{id}/budgets | DI; PM, QS a | - | versions and current |
| POST | /projects/{id}/budgets | QS a, PM a | amount, reason, submit flag | 201 draft or pending (A12) |
| POST | /budgets/{id}/submit | requester | - | 200 pending |
| POST | /budgets/{id}/approve | DI | note | 200; supersedes current in one transaction; 409 if requester |
| POST | /budgets/{id}/reject | DI | note | 200 |
| GET | /projects/{id}/expenditures | DI; PM, QS a | filters | paged |
| POST | /projects/{id}/expenditures | QS a | date, amount, category, description, ref, supplier, activity_id, confirm | 201; A5, A6, A6b, A7 evaluated |
| POST | /expenditures/{id}/void | QS a | void_reason | 200 |
| GET | /projects/{id}/certificates | DI; PM, QS a; CL a (totals) | - | certificates with paid and outstanding |
| POST | /projects/{id}/certificates | QS a | cert_no, valuation_amount, submitted_date | 201 |
| POST | /certificates/{id}/certify | QS a | amount_certified, certified_date | 200 |
| POST | /certificates/{id}/payments | QS a | paid_date, amount, reference_no | 201; status part_paid or paid; 422 overpayment |
| GET | /projects/{id}/resources | DI; PM, QS, SE a | category | resources with balance; rates omitted for SE |
| POST | /projects/{id}/resources | PM a, SE a | name, category, unit, reorder_level | 201 |
| PATCH | /resources/{id} | PM a, SE a | equipment_status, reorder_level | 200; A14 when faulty |
| POST | /resources/{id}/usage | PM a, SE a | date, movement, quantity, activity_id, client_uuid | 201; A10 evaluated |
| GET, POST | /projects/{id}/material-requests | DI; PM, QS, SE a (GET); SE, PM a (POST) | resource_id, quantity, required_by | 201 |
| POST | /material-requests/{id}/decide | PM a | approved, delivered or cancelled | 200 |
| GET | /projects/{id}/dashboard | DI; PM, QS, SE a | as_of (optional) | KPI DTO filtered by role, charts series, delayed list, milestones, open alerts, `last_updated`, `data_currency_days` |
| GET | /dashboard/portfolio | DI; AD (counts) | - | cross-project comparison (FR-35) |
| GET | /client/summary | CL a | project_id | one-screen client DTO |
| GET | /changes?since=ISO8601 | all | project_id optional | `{changed: bool, last_change_at}`; < 1 KB; 304-style cheap path |
| GET | /alerts | all except AD | status, project_id | alerts for the current user |
| POST | /alerts/{recipientId}/read | recipient | - | 204 |
| POST | /alerts/{recipientId}/acknowledge | recipient | - | 204 |
| GET | /thresholds | DI, PM a, QS a | project_id | effective values and limits |
| PUT | /thresholds/global/{key} | DI | value, min_allowed, max_allowed | 200 |
| PUT | /projects/{id}/thresholds/{key} | PM a | value | 200; 422 outside global limits |
| POST | /threshold-proposals | QS a | key, value, project_id, justification | 201 |
| POST | /threshold-proposals/{id}/decide | DI | approve or reject | 200 |
| GET | /reports/{type} | per report | project_id, from, to, format=pdf or csv | streamed file; types: status, delayed, milestones, expenditure, budget, cost-variance, payments, resources, alerts, audit (AD), client-summary |
| GET | /audit | AD | user, action, entity, date range | paged |
| GET, PATCH | /settings | AD | key, value | 200; audit-logged |
| POST | /settings/sms/test | AD | phone | sends through the configured gateway; sandbox writes log only |
| GET | /admin/backups | AD | - | backup_log |
| POST | /admin/backups | AD | - | streams encrypted `.sql.gz.enc`; logs backup |
| POST | /backup/run | scheduler | header `Authorization: Bearer <BACKUP_TOKEN>` | streams encrypted backup to the caller; rate-limited; no session |
| POST | /sync | PM, SE | `{ items: [{client_uuid, endpoint, body}] }` max 50 | per-item result `{client_uuid, status, entity_id, error}`; replay returns the first result |
| GET | /health | public | - | `{status:"ok", db:"ok"}`; no versions or secrets |

Pages (server-rendered shells): `/login`, `/` (central redirect), `/admin`, `/director`, `/pm`, `/qs`, `/site`, `/client`, `/password`, plus sub-pages per role (for example `/pm/projects/{id}/approvals`).

## 9. KPI and alert service design

### 9.1 KPI service (`KpiService`)

Input: project ID, status date t (default today, Africa/Accra). Uses only approved progress records, active activities and active expenditure.

| Indicator | Rule | Not available when |
|---|---|---|
| Activity % complete | Latest approved record (by record_date, then id); 0 if none | - |
| BAC | Σ budgeted_cost of active activities with a cost | No priced activity: switch to duration weighting, labelled "duration-weighted" |
| EV | Σ cost x % / 100 | BAC = 0 |
| Completion % | EV / BAC x 100; duration fallback Σ duration x % / Σ duration | No activities |
| Planned fraction(t) | 0 before planned start; (t - start) / (finish - start) during; 1 on or after finish; a zero-length activity is 0 before and 1 from its date | - |
| PV | Σ cost x planned fraction | BAC = 0 |
| AC | Σ active expenditure with expense_date <= t | - |
| SV, SPI | EV - PV; EV / PV | SPI not shown when PV = 0 |
| CV, CPI | EV - AC; EV / AC | CPI not shown when AC = 0 |
| Budget variance | current approved budget - AC | No current budget |
| Budget consumed % | AC / current approved budget x 100 | No current budget or budget = 0 |
| Schedule status | Not started (t <= planned start, 0%); Late start (t > planned start, no actual start, no progress); In progress (started, < 100%, t <= planned finish); Delayed (t > planned finish, < 100%); Completed (100%, actual finish <= planned finish); Completed late (100%, actual finish > planned finish) | - |
| Data currency | Days since the latest approved progress record on the project | No approved record: "no approved progress yet" |
| Days to planned completion | planned_finish - t (FR-40) | - |

Money uses integer pesewas internally (`Lubie\Support\Money`, decided in Stage 3; no extra library); never float. Ratios rounded to 2 decimals only for display. EAC is not implemented.

Worked example (thesis Table 2.1, unit test UT-KPI-01): BAC 200,000; PV 98,000; EV 70,000; AC 80,000; completion 35.00%; SV -28,000.00; SPI 0.71; CV -10,000.00; CPI 0.88. Demo seed reference values (computed by `tools/reference_kpi_check.py` on 21/10/2026, change daily because dates are relative): DEMO-P1 completion 61.8%, SPI 0.91, CPI 0.55, budget consumed 112.0%; DEMO-P2 54.7%, SPI 0.86, CPI 0.64, 85.0%.

### 9.2 Alert service (`AlertService`)

- Rules are classes implementing `AlertRule { code(); evaluate(ProjectSnapshot $s, Thresholds $t): Condition[] }`. Each `Condition` carries a dedup key (`rule:entity:id`), severity, title, description, client-safe flag.
- Triggers: (1) after any save that affects a project (event dispatcher), for that project; (2) on the first dashboard or changes request of the day per project (guarded by `projects.last_alert_eval_on`). Only projects in status active or on_hold are evaluated.
- Algorithm per project: build snapshot (one query set shared with `KpiService`); evaluate all rules; for each true condition with no open alert of the same dedup key, insert `notifications` and recipient rows (unique open key prevents duplicates even under race); for each open alert whose condition is now false, set `cleared_at`, `status='resolved'`; one transaction.
- Escalation: when A6 is open for a project, A5 is cleared; when A6b is open, A6 stays open (different channel). SMS level of A1 is a separate condition `A1:sms` when delay > `delay_sms_days`.
- Recipients resolved at creation from assignments and roles per the table below. Director recipients for every project (no assignment needed).

| ID | Condition | Default | Severity | In-app recipients | SMS |
|---|---|---|---|---|---|
| A1 | t > planned finish and % < 100 | definition; SMS > 14 days | High | PM, SE | Director, PM (above threshold) |
| A2 | t > planned start, no actual start, no progress | definition | Medium | PM, SE | No |
| A3 | Milestone not achieved, due within n days | 7 | Information | PM, SE | No |
| A4 | Milestone not achieved, t > due | definition | High | PM, SE; Client in-app notice | PM |
| A5 | AC >= x% of current budget | 80 | Warning | PM, QS | No |
| A6 | AC > 100% | definition | Critical | PM, QS, Director | No |
| A6b | AC > (100 + y)% | 10 | Critical | Director, PM, QS | Director, PM, QS |
| A7 | CPI < c and EV > 0 | 0.90 | Warning | PM, QS | No |
| A8 | SPI < s and PV > 0 | 0.90 | Warning | PM | No |
| A9 | Certificate not fully paid and t > submitted + p days | p per project | High | QS, PM | QS, PM |
| A10 | Material balance < reorder level | per material | Information | SE, PM | No |
| A11 | No approved progress for d days | 7 | Warning | PM, SE | No |
| A12 | Budget revision pending | definition | High | Director, PM, QS | Director |
| A13 | Progress awaiting approval | definition | Information | PM | No |
| A14 | Equipment faulty | definition | Information | PM, SE | No |
| A15 | Delay reason with raises_critical (stoppage, safety) | definition | Critical | Director, PM, QS | Director, PM, QS |

A1 to A15 conditions in the demo seed were checked by `tools/reference_kpi_check.py`: all 16 rules (including A6b) triggered.

## 10. Database design

- 31 tables: the 16 thesis entities kept (roles, users, projects, project_assignments, activities, milestones, progress_records, budgets, expenditures, payment_certificates, resource_categories, resources, resource_usage, notifications, alert_settings, audit_logs) plus 15 justified additions (sessions, login_attempts, activity_dependencies, delay_reasons, progress_photos, expenditure_categories, payment_transactions, material_requests, threshold_proposals, notification_recipients, sms_log, backup_log, system_settings, sync_requests, schema_migrations). Purpose and columns: `docs/DATA_DICTIONARY.md`.
- Normalisation: 3NF. Deliberate exceptions: `activities.actual_start/actual_finish` are stored although derivable from progress (they are the site's recorded dates and drive schedule status); `projects.last_change_at` is a denormalised polling stamp; certificate "overdue" is derived and not stored; current % complete is derived from progress, not stored on activities (the thesis Table 3.12 field was dropped to avoid an update anomaly).
- Integrity in the database: 57 foreign keys, 27 CHECK constraints, unique keys for (project, user), (project, version), (project, is_current) for one current budget, (project, cert_no), (certificate, reference_no), open alert dedup key, offline client_uuid.
- Verified on MariaDB 10.11 (21/10/2026): schema, production seed and demo seed import without error; eight constraint probes rejected invalid data (second current budget, 110%, duplicate assignment, finish before start, current flag on a pending budget, duplicate open alert, negative expenditure, non-+233 phone); a cleared alert can re-open; `remove_demo.sql` returns the database to reference data only; the least-privilege account was refused UPDATE and DELETE on audit_logs and DROP on tables.
- Migrations: `database/migrations/0001_initial_schema.sql` (= schema v0.2.0); each later change is a new numbered file recorded in `schema_migrations`.
- Portability: MySQL 8.0.16+ required for enforced CHECK constraints. Generated columns use STORED. No triggers, procedures or engine-specific functions. If the host forces PostgreSQL, Stage 6 documents the mapping (ENUM to CHECK, AUTO_INCREMENT to identity, TINYINT(1) to boolean, DATETIME to timestamp, generated columns syntax).

## 11. Notification and SMS design

- Channel order: in-app always; SMS only for the rules marked SMS above, only to users with a +233 number and `sms_opt_in = 1`, never to clients.
- One SMS per alert per recipient. Caps: per-user daily cap (default 5) and optional company monthly cap. A capped message is logged with status `capped`; the in-app alert still appears.
- Sending: synchronous with a 5-second timeout during the request that created the alert; failure sets `retry_due = 1`; retries happen on the next request that evaluates alerts (no worker), maximum 3 attempts.
- Message format (<= 160 characters, no internal amounts): `Lubie DEMO-P1: Activity R03 delayed 20 days. Open dashboard. Ref A1-42`.
- Adapters: `SandboxSmsGateway` writes to `sms_log` with provider `sandbox` (default, used in all tests); `HttpSmsGateway` sends an authenticated HTTPS request to the base URL and API key from environment (Arkesel-compatible). `sms_enabled` stays 0 until a real provider is configured and a test message is confirmed received. The system never reports "sent" for the sandbox.

## 12. Refresh design

- Page loads full data once. A timer calls `GET /changes?since=<last_change_at>` every `refresh_interval_seconds` while `document.visibilityState === 'visible'`.
- Server updates `projects.last_change_at` in the same transaction as any change to that project's data.
- If `changed = true`, the page fetches the dashboard DTO and redraws cards and charts without reload.
- The page shows two times: "Data last updated" (server `last_change_at`, i.e. last save) and "Checked" (last successful poll). On failure: exponential backoff (60, 120, 240 s, max 10 min) and a banner "Connection problem, showing data from <time>". On 503 from a sleeping host: "Waking up the server..." state with retry.

## 13. Offline design (FR-38, Must, Iteration 1)

- Installable web app: `manifest.webmanifest` and `sw.js` cache the app shell (HTML shells, CSS, JS, vendor libraries, icons) with a versioned cache; API GET responses for the user's assigned projects use network-first with cache fallback, marked "Offline, last synced <time>".
- Offline capture (SE and PM): progress entries, labour attendance, material receipts and usage, equipment status. Each entry gets a `client_uuid` (crypto.randomUUID) and goes to an IndexedDB outbox, encrypted with AES-GCM using a key derived per login session and held only in memory and session storage, so a logged-out device cannot read the queue.
- Sync: on `online` event or app start, the outbox is sent to `POST /sync` in batches of up to 50. The server checks session, CSRF, role, project scope and validation for each item exactly as for direct calls, stores the result in `sync_requests`, and returns per-item results. A replayed `client_uuid` returns the stored result without creating a duplicate (unique keys on `progress_records.client_uuid` and `resource_usage.client_uuid` as a second guard).
- Conflicts: if an activity was archived or the user unassigned while offline, the item is rejected with a clear reason and kept in a "Needs attention" list for the user to discard or copy.
- Not offline: approvals, budgets, expenditure, payments, admin functions (they need current server state and stronger controls). Logout clears caches and the outbox after warning about unsynced items.
- Login requires a connection. The offline app does not create sessions offline.

## 14. Reporting design

Same repositories, `KpiService` and DTO filters as dashboards. PDF via Dompdf with A4 portrait template, company header text "Lubie Construction Works" (no logo file assumed), report title, parameters, generation time and user, page numbers. CSV with UTF-8 BOM, DD/MM/YYYY dates, two-decimal amounts, formula guard. Both streamed with `Content-Disposition: attachment`; nothing written to disk. Every export writes an audit row.

## 15. Backup and recovery design

- Daily full logical backup: tables in dependency order, `INSERT` statements in chunks, gzip, then AES-256-GCM with `BACKUP_ENCRYPTION_KEY`; filename `lubie_<env>_<yyyymmdd_hhmm>.sql.gz.enc`; SHA-256 recorded in `backup_log` with row counts.
- Triggers: Administrator button (download), and `POST /backup/run` with a bearer token called by a free external scheduler which stores the file in company-controlled storage (second location). Retention 7 daily and 4 weekly is applied by the storage location; the app keeps only the log.
- Local XAMPP: documented `mysqldump` command and Windows Task Scheduler entry (Stage 3 guide).
- Restore: `php bin/console.php restore:verify <file>` decrypts, imports into a separate empty database, compares row counts and recomputes KPIs against the log; then the documented switch-over procedure (thesis s3.17.5 steps 1 to 6). FT-32 is a timed test of this.
- Honesty note: whether a free host meets RPO 24 h and RTO 4 h depends on the external scheduler running daily and on the database host; Stage 6 states the result for the chosen host. An untested backup is not a verified recovery.

## 16. Audit design

Actions logged: login, login_failed, logout, lockout, password_change, password_reset, create, update, archive, void, approve, return, reject, assign, unassign, role_change, threshold_change, export, backup, restore, settings_change, forbidden, sync. Old and new values are JSON of changed fields only, with password hashes, tokens and keys removed by an allow-list per entity. Admin-only viewer with filters and CSV export.

## 17. UI design

### 17.1 Principles

Mobile-first at 360 px; one primary action per screen; plain construction terms; dropdowns and pre-filled dates; status as colour plus text plus icon (green "On track", amber "Warning", red "Critical"); KPI cards with an (i) tooltip showing the formula; "Last updated" on every dashboard; dark/light toggle in the top bar; loading, empty, success and error states on every view.

### 17.2 Navigation map

```
/login ──► server redirect by role
  /admin     Users │ Settings (thresholds view, SMS, refresh) │ Audit │ Backups │ Portfolio counts
  /director  Portfolio comparison │ Project view (read) │ Approvals (budget revisions, threshold proposals) │ Global thresholds │ Reports
  /pm        My projects │ Project overview │ Activities and milestones │ Approvals queue │ Resources and requests │ Thresholds │ Team │ Reports
  /qs        My projects │ Cost dashboard │ Budget and revisions │ Expenditure │ Certificates and payments │ Threshold proposals │ Reports
  /site      My activities │ Update progress │ Labour │ Materials │ Equipment │ Requests │ Sync status
  /client    Project summary (one screen) │ Download summary PDF
All: Alerts bell │ Change password │ Theme toggle │ Log out
```

### 17.3 Wireframes (mobile, 360 px)

Login (all users):
```
┌──────────────────────────────┐
│  Lubie Construction Works    │
│  Project Monitoring          │
│  Username [______________]   │
│  Password [__________] (show)│
│  [        Log in         ]   │
│  Problems? Ask your admin.   │
└──────────────────────────────┘
```

Site Engineer, update progress (one screen per activity):
```
┌──────────────────────────────┐
│ ☰ My activities     🔔2  ◐   │
│ DEMO-P1 › R03 Culverts       │
│ Planned now 100% │ Approved 80%│
│ Status: ⚠ Delayed 20 days    │
│ Date      [21/10/2026 ▾]     │
│ % complete [ 85 ] ───●──     │
│ Delay reason [Material ▾]    │
│ Remarks  [________________]  │
│ [     Submit for approval  ] │
│ Last entry: Submitted ⏳      │
│ Offline: 0 waiting to sync   │
└──────────────────────────────┘
```

PM, project overview:
```
┌──────────────────────────────┐
│ DEMO-P1 Feeder road   ◐ 🔔5  │
│ Last updated 21/10 09:14     │
│ ┌Complete┐┌Budget ┐┌SPI ┐┌CPI┐│
│ │ 61.8% ⓘ││112% ⛔││0.91││0.55││
│ └────────┘└───────┘└────┘└───┘│
│ Planned vs earned  (line)    │
│ Budget vs spent    (bar)     │
│ Delayed activities (table)   │
│ Approvals waiting: 1  [Open] │
│ Milestones │ Alerts │ Reports │
└──────────────────────────────┘
```

QS, cost dashboard: KPI cards (Budget, Spent, Consumed %, CV, CPI), budget versus expenditure chart by category, revision status card, certificates table (certified, paid, outstanding, overdue flag), buttons Record expenditure, New certificate, Record payment.

Director, portfolio: one row per project with % complete, SV, CV, delayed count, next milestone, open critical alerts, colour plus text status; tap to open read-only project view; approvals badge.

Client, one screen:
```
┌──────────────────────────────┐
│ DEMO-P2 Office building      │
│ Last updated 21/10 09:14     │
│ Overall progress  ███████░ 55%│
│ Milestones                   │
│  ✔ Substructure 17/07        │
│  ○ Roof slab   due 10/11     │
│  ○ Weather-tight due 20/12   │
│ Payments                     │
│  Contract sum  GH₵1,950,000.00│
│  Certified     GH₵  910,000.00│
│  Paid          GH₵  480,000.00│
│  Outstanding   GH₵  430,000.00│
│ [ Download summary (PDF) ]   │
└──────────────────────────────┘
```

Admin: users table with role, status, last login, actions (edit, reset password, deactivate, unlock); settings form; audit filter list; backup button and log.

Tablet and desktop: same components in a 2 to 4 column grid with a left navigation rail.

## 18. Configuration (environment variables)

`APP_ENV`, `APP_URL`, `APP_KEY`, `APP_TIMEZONE=Africa/Accra`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_SSL_CA`, `DB_SSL_VERIFY`, `DB_CONNECT_RETRIES`, `SESSION_SECURE_COOKIE`, `SMS_PROVIDER` (sandbox or arkesel), `SMS_API_KEY`, `SMS_SENDER_ID`, `SMS_BASE_URL`, `BACKUP_TOKEN`, `BACKUP_ENCRYPTION_KEY`, `LOG_LEVEL`, `PORT` (container). Values live in `.env` (local, not committed) or the host's secret settings. `.env.example` holds placeholders only.

## 19. Test strategy

| Level | Scope | Tool | Data |
|---|---|---|---|
| Unit | KpiService (worked examples, PV = 0, AC = 0, BAC = 0, dates outside plan, 0% and 100%, missing data, duration fallback), each alert rule (trigger, no duplicate, clear, re-trigger), validators, permission matrix, SMS caps, payment balances, formatters | PHPUnit | In-memory fixtures, fixed `Clock` |
| Integration | Repositories and transactions, authentication, authorisation, assignment scope, approval workflows, budget supersession, payments, alerts with DB, audit logging, exports, backup and restore, sync idempotency | PHPUnit with a separate test database created and reset by script | `schema.sql` + `seed_production.sql` + test fixtures |
| Functional | FT-01 to FT-40 (one or more per requirement) | Manual scripts plus Playwright where practical | `seed_demo.sql` |
| Security | ST-01 to ST-16 mapped to OWASP | Scripted HTTP tests, manual review | Demo data |
| Role-based access | Every role against permitted and prohibited endpoints, unified login redirect, 403 logging | Data-driven PHPUnit using `config/permissions.php` | Demo users |
| Performance | PT-01 to PT-07: p95 timings, payload size, 20 concurrent users, throttled network, 360 px | k6 or `ab`, Chrome DevTools throttling, Lighthouse | Demo data |
| Compatibility | Chrome, Firefox, Edge, Safari (if available), 360 px, tablet, desktop | Manual plus Playwright | Demo data |
| Reference oracle | `tools/reference_kpi_check.py` cross-checks KPI and alert results | Python | Demo data |
| Evaluation (prepared only) | Tasks T1 to T11, TAM and D&M items, SUS, CP1 to CP3 | Stage 4 instruments | Demo data |

A feature is complete only when its acceptance criteria in the RTM are verified with evidence. Results are reported as pass, fail or not executed; nothing is claimed without running it.

## 20. Diagrams

| File | Pages |
|---|---|
| 01-database-erd.drawio | ERD part A (identity, projects, schedule, progress); part B (cost, resources); part C (alerts, audit, system) |
| 02-system-structure-chart.drawio | Top-level structure chart; detail charts |
| 03-data-flow-diagram.drawio | Context; Level 0; Level 1 progress and KPI; Level 1 cost and budget revision; Level 1 alerts and notification |
| 04-sequence-diagrams.drawio | Ten sequence diagrams (a) to (j) |
| 05-activity-diagrams.drawio | Six activity diagrams |
| 06-state-diagrams.drawio | Seven state machines |
| 07-component-diagrams.drawio | Logical three-tier; local deployment; production deployment |
| 08-use-case-diagram.drawio | Use case diagram; use case specifications are in `docs/USE_CASE_SPECIFICATIONS.md` |

Diagrams show the designed system. Elements planned for later iterations carry an iteration tag (for example "[It.2]").
