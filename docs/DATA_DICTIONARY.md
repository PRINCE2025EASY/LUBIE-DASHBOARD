# Data dictionary

Generated from `database/schema.sql` v0.2.0 by `tools/gen_data_dictionary.py`. Do not edit by hand.

31 tables. Thesis entities: 16 (all kept). Added tables are justified in the Purpose line.

## roles

Six system roles and their dashboard path (thesis entity).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | TINYINT UNSIGNED | N |  | PK |  |
| code | VARCHAR(20) | N |  | UQ uq_roles_code | admin, director, pm, qs, site, client |
| name | VARCHAR(60) | N |  |  |  |
| dashboard_path | VARCHAR(30) | N |  |  | /admin, /director, /pm, /qs, /site, /client |

## users

Accounts, SMS phone, password hash, lockout state (thesis entity).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | N |  | PK | Auto-increment. |
| role_id | TINYINT UNSIGNED | N |  | FK → roles.id |  |
| username | VARCHAR(50) | N |  | UQ uq_users_username |  |
| full_name | VARCHAR(120) | N |  |  |  |
| phone_e164 | VARCHAR(16) | Y |  |  | +233XXXXXXXXX, used for SMS |
| email | VARCHAR(190) | Y |  |  |  |
| password_hash | VARCHAR(255) | N |  |  | password_hash(); never plain text |
| must_change_password | TINYINT(1) | N | 1 |  |  |
| status | ENUM('active','locked','deactivated') | N | 'active' |  |  |
| failed_login_count | SMALLINT UNSIGNED | N | 0 |  |  |
| locked_until | DATETIME | Y |  |  |  |
| last_login_at | DATETIME | Y |  |  |  |
| theme_pref | ENUM('light','dark','system') | N | 'system' |  | FR-41 |
| sms_opt_in | TINYINT(1) | N | 1 |  |  |
| created_by | INT UNSIGNED | Y |  | FK → users.id |  |
| created_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |
| updated_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |

Checks: `ck_users_phone`: phone_e164 IS NULL OR phone_e164 LIKE '+233%'

## sessions

Database session store so the web tier is stateless (added: NFR-11).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | VARCHAR(128) | N |  | PK |  |
| user_id | INT UNSIGNED | Y |  | FK → users.id |  |
| data | MEDIUMBLOB | N |  |  |  |
| ip_address | VARCHAR(45) | Y |  |  |  |
| user_agent | VARCHAR(255) | Y |  |  |  |
| created_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |
| last_activity | DATETIME | N | CURRENT_TIMESTAMP |  |  |

## login_attempts

Throttling and lockout evidence (added: FR-29, ST-08).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | N |  | PK | Auto-increment. |
| username_hash | CHAR(64) | N |  |  | SHA-256 of lower-cased username |
| ip_address | VARCHAR(45) | N |  |  |  |
| success | TINYINT(1) | N |  |  |  |
| attempted_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |

## projects

Core project data, payment period, status, polling stamp (thesis entity).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | N |  | PK | Auto-increment. |
| code | VARCHAR(20) | N |  | UQ uq_projects_code |  |
| name | VARCHAR(150) | N |  |  |  |
| project_type | VARCHAR(60) | N |  |  | free list: road, building, etc. |
| location | VARCHAR(150) | N |  |  |  |
| client_name | VARCHAR(150) | N |  |  |  |
| contract_sum | DECIMAL(15,2) | N |  |  |  |
| planned_start | DATE | N |  |  |  |
| planned_finish | DATE | N |  |  |  |
| actual_start | DATE | Y |  |  |  |
| actual_finish | DATE | Y |  |  |  |
| payment_period_days | SMALLINT UNSIGNED | N | 30 |  | A9 parameter p |
| status | ENUM('planning','active','on_hold','completed','archived') | N | 'planning' |  |  |
| is_demo | TINYINT(1) | N | 0 |  | synthetic data flag |
| last_change_at | DATETIME | N | CURRENT_TIMESTAMP |  | polling stamp (FR-24) |
| change_seq | INT UNSIGNED | N | 0 |  | increments on every change (poll token) |
| last_alert_eval_on | DATE | Y |  |  | daily evaluation guard |
| created_by | INT UNSIGNED | Y |  | FK → users.id |  |
| created_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |
| updated_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |

Checks: `ck_projects_dates`: planned_finish >= planned_start; `ck_projects_actual`: actual_finish IS NULL OR actual_start IS NULL OR actual_finish >= actual_start; `ck_projects_sum`: contract_sum >= 0

## project_assignments

Resolves users x projects; unique pair (thesis entity).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | N |  | PK | Auto-increment. |
| project_id | INT UNSIGNED | N |  | FK → projects.id; UQ uq_assign_project_user |  |
| user_id | INT UNSIGNED | N |  | FK → users.id; UQ uq_assign_project_user |  |
| assigned_by | INT UNSIGNED | Y |  | FK → users.id |  |
| assigned_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |

## activities

Tasks with planned/actual dates and budgeted cost (thesis entity).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | N |  | PK | Auto-increment. |
| project_id | INT UNSIGNED | N |  | FK → projects.id; UQ uq_activities_code |  |
| code | VARCHAR(20) | N |  | UQ uq_activities_code |  |
| name | VARCHAR(150) | N |  |  |  |
| planned_start | DATE | N |  |  |  |
| planned_finish | DATE | N |  |  |  |
| actual_start | DATE | Y |  |  |  |
| actual_finish | DATE | Y |  |  |  |
| budgeted_cost | DECIMAL(15,2) | Y |  |  | set by PM only; hidden from SE (FR-08) |
| status | ENUM('active','archived') | N | 'active' |  |  |
| created_by | INT UNSIGNED | Y |  | FK → users.id |  |
| updated_by | INT UNSIGNED | Y |  | FK → users.id |  |
| created_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |
| updated_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |

Checks: `ck_act_dates`: planned_finish >= planned_start; `ck_act_actual`: actual_finish IS NULL OR actual_start IS NULL OR actual_finish >= actual_start; `ck_act_cost`: budgeted_cost IS NULL OR budgeted_cost >= 0

## activity_dependencies

Optional finish-to-start links (added: FR-08).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| activity_id | INT UNSIGNED | N |  | PK; FK → activities.id |  |
| predecessor_id | INT UNSIGNED | N |  | PK; FK → activities.id |  |

Checks: `ck_dep_self`: activity_id <> predecessor_id

## milestones

Key events with due/achieved dates (thesis entity).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | N |  | PK | Auto-increment. |
| project_id | INT UNSIGNED | N |  | FK → projects.id |  |
| activity_id | INT UNSIGNED | Y |  | FK → activities.id |  |
| name | VARCHAR(150) | N |  |  |  |
| due_date | DATE | N |  |  |  |
| achieved_date | DATE | Y |  |  |  |
| created_by | INT UNSIGNED | Y |  | FK → users.id |  |
| created_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |
| updated_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |

## delay_reasons

Standard delay-reason list; critical flag drives A15 (added: FR-32).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | SMALLINT UNSIGNED | N |  | PK | Auto-increment. |
| code | VARCHAR(30) | N |  | UQ uq_delay_code |  |
| label | VARCHAR(80) | N |  |  |  |
| raises_critical | TINYINT(1) | N | 0 |  | 1 = A15 (work stoppage, safety incident) |
| is_active | TINYINT(1) | N | 1 |  |  |
| sort_order | SMALLINT | N | 0 |  |  |

## progress_records

Dated % complete with approval workflow (thesis entity, extended: FR-30).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | N |  | PK | Auto-increment. |
| activity_id | INT UNSIGNED | N |  | FK → activities.id |  |
| record_date | DATE | N |  |  |  |
| percent_complete | DECIMAL(5,2) | N |  |  |  |
| remarks | VARCHAR(500) | Y |  |  |  |
| delay_reason_id | SMALLINT UNSIGNED | Y |  | FK → delay_reasons.id |  |
| status | ENUM('submitted','approved','returned') | N | 'submitted' |  |  |
| submitted_by | INT UNSIGNED | N |  | FK → users.id |  |
| submitted_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |
| reviewed_by | INT UNSIGNED | Y |  | FK → users.id |  |
| reviewed_at | DATETIME | Y |  |  |  |
| return_reason | VARCHAR(300) | Y |  |  |  |
| resubmission_of | INT UNSIGNED | Y |  | FK → progress_records.id | earlier returned record |
| client_uuid | CHAR(36) | Y |  | UQ uq_progress_client_uuid | offline idempotency key (FR-38) |

Checks: `ck_pr_percent`: percent_complete >= 0 AND percent_complete <= 100; `ck_pr_return`: status <> 'returned' OR return_reason IS NOT NULL; `ck_pr_review`: status = 'submitted' OR reviewed_by IS NOT NULL

## progress_photos

One optional compressed photo per record (added: FR-39, Could).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | N |  | PK | Auto-increment. |
| progress_record_id | INT UNSIGNED | N |  | FK → progress_records.id; UQ uq_photo_record |  |
| mime_type | VARCHAR(20) | N |  |  |  |
| byte_size | INT UNSIGNED | N |  |  |  |
| sha256 | CHAR(64) | N |  |  |  |
| content | MEDIUMBLOB | N |  |  |  |
| created_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |

Checks: `ck_photo_mime`: mime_type IN ('image/jpeg','image/png','image/webp'); `ck_photo_size`: byte_size <= 307200

## budgets

Budget versions with Director approval and supersession (thesis entity, extended: FR-31).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | N |  | PK | Auto-increment. |
| project_id | INT UNSIGNED | N |  | FK → projects.id; UQ uq_budget_version; UQ uq_budget_current |  |
| version_no | SMALLINT UNSIGNED | N |  | UQ uq_budget_version |  |
| amount | DECIMAL(15,2) | N |  |  |  |
| reason | VARCHAR(300) | Y |  |  |  |
| status | ENUM('draft','pending','approved','rejected','superseded') | N | 'draft' |  |  |
| is_current | TINYINT(1) | Y |  | UQ uq_budget_current |  |
| requested_by | INT UNSIGNED | N |  | FK → users.id |  |
| requested_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |
| decided_by | INT UNSIGNED | Y |  | FK → users.id |  |
| decided_at | DATETIME | Y |  |  |  |
| decision_note | VARCHAR(300) | Y |  |  |  |
| supersedes_id | INT UNSIGNED | Y |  | FK → budgets.id |  |

Checks: `ck_budget_amount`: amount >= 0; `ck_budget_current`: is_current IS NULL OR (is_current = 1 AND status = 'approved'); `ck_budget_duties`: decided_by IS NULL OR decided_by <> requested_by OR version_no = 1

## expenditure_categories

Standard expenditure categories (added: NFR-07 consistency).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | SMALLINT UNSIGNED | N |  | PK | Auto-increment. |
| code | VARCHAR(30) | N |  | UQ uq_expcat_code |  |
| label | VARCHAR(80) | N |  |  |  |
| is_active | TINYINT(1) | N | 1 |  |  |

## expenditures

Expenditure entries; void instead of delete (thesis entity).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | N |  | PK | Auto-increment. |
| project_id | INT UNSIGNED | N |  | FK → projects.id; UQ uq_exp_reference |  |
| activity_id | INT UNSIGNED | Y |  | FK → activities.id |  |
| category_id | SMALLINT UNSIGNED | N |  | FK → expenditure_categories.id |  |
| expense_date | DATE | N |  |  |  |
| amount | DECIMAL(15,2) | N |  |  |  |
| description | VARCHAR(300) | N |  |  |  |
| reference_no | VARCHAR(50) | Y |  | UQ uq_exp_reference | voucher or receipt number |
| supplier_name | VARCHAR(150) | Y |  |  | hidden from SE and Client |
| status | ENUM('active','voided') | N | 'active' |  |  |
| void_reason | VARCHAR(300) | Y |  |  |  |
| recorded_by | INT UNSIGNED | N |  | FK → users.id |  |
| voided_by | INT UNSIGNED | Y |  | FK → users.id |  |
| created_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |
| updated_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |

Checks: `ck_exp_amount`: amount > 0; `ck_exp_void`: status = 'active' OR void_reason IS NOT NULL

## payment_certificates

Valuations and certificates (thesis entity).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | N |  | PK | Auto-increment. |
| project_id | INT UNSIGNED | N |  | FK → projects.id; UQ uq_cert_no |  |
| cert_no | VARCHAR(20) | N |  | UQ uq_cert_no |  |
| valuation_amount | DECIMAL(15,2) | N |  |  |  |
| amount_certified | DECIMAL(15,2) | Y |  |  |  |
| submitted_date | DATE | N |  |  |  |
| certified_date | DATE | Y |  |  |  |
| status | ENUM('submitted','certified','part_paid','paid') | N | 'submitted' |  |  |
| remarks | VARCHAR(300) | Y |  |  |  |
| recorded_by | INT UNSIGNED | N |  | FK → users.id |  |
| created_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |
| updated_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |

Checks: `ck_cert_amounts`: valuation_amount >= 0 AND (amount_certified IS NULL OR amount_certified >= 0); `ck_cert_state`: status = 'submitted' OR (amount_certified IS NOT NULL AND certified_date IS NOT NULL); `ck_cert_dates`: certified_date IS NULL OR certified_date >= submitted_date

## payment_transactions

Partial and multiple payments per certificate (added: FR-28).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | N |  | PK | Auto-increment. |
| certificate_id | INT UNSIGNED | N |  | FK → payment_certificates.id; UQ uq_pay_reference |  |
| paid_date | DATE | N |  |  |  |
| amount | DECIMAL(15,2) | N |  |  |  |
| reference_no | VARCHAR(50) | N |  | UQ uq_pay_reference |  |
| recorded_by | INT UNSIGNED | N |  | FK → users.id |  |
| created_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |

Checks: `ck_pay_amount`: amount > 0

## resource_categories

Labour, material, equipment (thesis entity).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | TINYINT UNSIGNED | N |  | PK |  |
| code | VARCHAR(20) | N |  | UQ uq_rescat_code | labour, material, equipment |
| label | VARCHAR(60) | N |  |  |  |

## resources

Project resource items, reorder level, rates, equipment status (thesis entity).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | N |  | PK | Auto-increment. |
| project_id | INT UNSIGNED | N |  | FK → projects.id; UQ uq_res_name |  |
| category_id | TINYINT UNSIGNED | N |  | FK → resource_categories.id; UQ uq_res_name |  |
| name | VARCHAR(120) | N |  | UQ uq_res_name |  |
| unit | VARCHAR(20) | N |  |  | from standard unit list (NFR-12) |
| planned_qty | DECIMAL(12,2) | Y |  |  |  |
| reorder_level | DECIMAL(12,2) | Y |  |  | materials only (A10) |
| unit_rate | DECIMAL(15,2) | Y |  |  | supplier price / labour rate: hidden from SE, Client |
| equipment_status | ENUM('working','faulty','idle','off_site') | Y |  |  | equipment only (A14) |
| status | ENUM('active','archived') | N | 'active' |  |  |
| created_by | INT UNSIGNED | Y |  | FK → users.id |  |
| created_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |
| updated_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |

Checks: `ck_res_qty`: (planned_qty IS NULL OR planned_qty >= 0) AND (reorder_level IS NULL OR reorder_level >= 0) AND (unit_rate IS NULL OR unit_rate >= 0)

## resource_usage

Dated receipts, usage, attendance, hours (thesis entity).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | N |  | PK | Auto-increment. |
| resource_id | INT UNSIGNED | N |  | FK → resources.id |  |
| activity_id | INT UNSIGNED | Y |  | FK → activities.id |  |
| usage_date | DATE | N |  |  |  |
| movement | ENUM('received','used','attendance','hours') | N |  |  |  |
| quantity | DECIMAL(12,2) | N |  |  |  |
| remarks | VARCHAR(300) | Y |  |  |  |
| recorded_by | INT UNSIGNED | N |  | FK → users.id |  |
| client_uuid | CHAR(36) | Y |  | UQ uq_usage_client_uuid | offline idempotency key (FR-38) |
| created_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |

Checks: `ck_usage_qty`: quantity >= 0

## material_requests

Simple request tracker (added: FR-33).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | N |  | PK | Auto-increment. |
| project_id | INT UNSIGNED | N |  | FK → projects.id |  |
| resource_id | INT UNSIGNED | N |  | FK → resources.id |  |
| quantity | DECIMAL(12,2) | N |  |  |  |
| required_by | DATE | N |  |  |  |
| status | ENUM('requested','approved','delivered','cancelled') | N | 'requested' |  |  |
| remarks | VARCHAR(300) | Y |  |  |  |
| requested_by | INT UNSIGNED | N |  | FK → users.id |  |
| requested_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |
| decided_by | INT UNSIGNED | Y |  | FK → users.id |  |
| decided_at | DATETIME | Y |  |  |  |

Checks: `ck_mreq_qty`: quantity > 0

## alert_settings

Global limits and project thresholds (thesis entity, extended: FR-21).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | N |  | PK | Auto-increment. |
| project_id | INT UNSIGNED | Y |  | FK → projects.id |  |
| setting_key | VARCHAR(40) | N |  | UQ uq_alertset_scope_key | e.g. budget_warning_pct, cpi_min |
| value | DECIMAL(10,2) | N |  |  |  |
| min_allowed | DECIMAL(10,2) | Y |  |  | global rows: limits for project overrides |
| max_allowed | DECIMAL(10,2) | Y |  |  |  |
| set_by | INT UNSIGNED | Y |  | FK → users.id |  |
| set_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |
| scope_project_id | INT UNSIGNED | Y |  | UQ uq_alertset_scope_key | Generated (stored). |

Checks: `ck_alertset_limits`: min_allowed IS NULL OR max_allowed IS NULL OR min_allowed <= max_allowed

## threshold_proposals

QS threshold proposals for Director approval (added: FR-21).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | N |  | PK | Auto-increment. |
| project_id | INT UNSIGNED | Y |  | FK → projects.id |  |
| setting_key | VARCHAR(40) | N |  |  |  |
| proposed_value | DECIMAL(10,2) | N |  |  |  |
| justification | VARCHAR(300) | Y |  |  |  |
| status | ENUM('pending','approved','rejected') | N | 'pending' |  |  |
| proposed_by | INT UNSIGNED | N |  | FK → users.id |  |
| proposed_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |
| decided_by | INT UNSIGNED | Y |  | FK → users.id |  |
| decided_at | DATETIME | Y |  |  |  |

## notifications

Alert instances with de-duplication (thesis entity, redesigned).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | N |  | PK | Auto-increment. |
| rule_code | VARCHAR(4) | N |  |  | A1 .. A15, A6b |
| severity | ENUM('information','warning','medium','high','critical') | N |  |  |  |
| project_id | INT UNSIGNED | N |  | FK → projects.id |  |
| entity_type | VARCHAR(30) | N |  |  | activity, milestone, budget, certificate, ... |
| entity_id | INT UNSIGNED | Y |  |  |  |
| dedup_key | VARCHAR(120) | N |  |  | e.g. A1:activity:42 |
| title | VARCHAR(150) | N |  |  |  |
| description | VARCHAR(500) | N |  |  |  |
| is_internal | TINYINT(1) | N | 1 |  | 0 only for client-safe notices (A4) |
| status | ENUM('generated','resolved') | N | 'generated' |  |  |
| created_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |
| cleared_at | DATETIME | Y |  |  |  |
| open_dedup_key | VARCHAR(120) | Y |  | UQ uq_notif_open | Generated (stored). |

Checks: `ck_notif_clear`: status = 'generated' OR cleared_at IS NOT NULL

## notification_recipients

Per-recipient read/acknowledge and SMS sub-status (added: FR-20, FR-22).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | N |  | PK | Auto-increment. |
| notification_id | INT UNSIGNED | N |  | FK → notifications.id; UQ uq_notif_user |  |
| user_id | INT UNSIGNED | N |  | FK → users.id; UQ uq_notif_user |  |
| delivered_at | DATETIME | Y |  |  |  |
| read_at | DATETIME | Y |  |  |  |
| acknowledged_at | DATETIME | Y |  |  |  |
| sms_status | ENUM('none','queued','sent','failed','capped') | N | 'none' |  |  |

## sms_log

SMS attempts and cap accounting (added: FR-22).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | N |  | PK | Auto-increment. |
| recipient_id | INT UNSIGNED | N |  | FK → notification_recipients.id |  |
| phone_e164 | VARCHAR(16) | N |  |  |  |
| message | VARCHAR(320) | N |  |  |  |
| provider | VARCHAR(30) | N |  |  | sandbox, arkesel |
| status | ENUM('queued','sent','failed','capped') | N |  |  |  |
| provider_ref | VARCHAR(80) | Y |  |  |  |
| error_text | VARCHAR(300) | Y |  |  |  |
| attempts | TINYINT UNSIGNED | N | 1 |  |  |
| retry_due | TINYINT(1) | N | 0 |  |  |
| created_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |
| sent_at | DATETIME | Y |  |  |  |

## audit_logs

Append-only audit trail (thesis entity).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | N |  | PK | Auto-increment. |
| user_id | INT UNSIGNED | Y |  | FK → users.id | NULL for failed login with unknown username |
| username_attempted | VARCHAR(50) | Y |  |  |  |
| action | VARCHAR(40) | N |  |  | login, login_failed, logout, create, update, archive, approve, ... |
| entity_type | VARCHAR(40) | Y |  |  |  |
| entity_id | VARCHAR(40) | Y |  |  |  |
| old_values | TEXT | Y |  |  | JSON; secrets never stored |
| new_values | TEXT | Y |  |  | JSON |
| ip_address | VARCHAR(45) | Y |  |  |  |
| user_agent | VARCHAR(255) | Y |  |  |  |
| created_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |

## backup_log

Backups, restores and restore tests (added: FR-36).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | N |  | PK | Auto-increment. |
| kind | ENUM('manual','scheduled','restore_test','restore') | N |  |  |  |
| status | ENUM('started','succeeded','failed') | N | 'started' |  |  |
| file_name | VARCHAR(120) | Y |  |  |  |
| byte_size | BIGINT UNSIGNED | Y |  |  |  |
| sha256 | CHAR(64) | Y |  |  |  |
| retention | ENUM('daily','weekly') | Y |  |  |  |
| row_counts | TEXT | Y |  |  | JSON table -> count, for restore verification |
| triggered_by | INT UNSIGNED | Y |  | FK → users.id | NULL = external scheduler |
| notes | VARCHAR(300) | Y |  |  |  |
| started_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |
| finished_at | DATETIME | Y |  |  |  |

## system_settings

Configurable parameters (added: NFR-10).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| setting_key | VARCHAR(60) | N |  | PK |  |
| value | VARCHAR(255) | N |  |  |  |
| value_type | ENUM('int','decimal','bool','string') | N |  |  |  |
| description | VARCHAR(200) | N |  |  |  |
| updated_by | INT UNSIGNED | Y |  | FK → users.id |  |
| updated_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |

## sync_requests

Offline replay idempotency (added: FR-38).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| client_uuid | CHAR(36) | N |  | PK |  |
| user_id | INT UNSIGNED | N |  | FK → users.id |  |
| endpoint | VARCHAR(80) | N |  |  |  |
| result_code | SMALLINT UNSIGNED | N |  |  | HTTP status returned first time |
| entity_type | VARCHAR(40) | Y |  |  |  |
| entity_id | INT UNSIGNED | Y |  |  |  |
| received_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |

## schema_migrations

Applied migration versions (added: maintainability).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| version | VARCHAR(20) | N |  | PK |  |
| applied_at | DATETIME | N | CURRENT_TIMESTAMP |  |  |
