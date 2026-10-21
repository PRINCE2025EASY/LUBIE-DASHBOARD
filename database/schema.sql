-- =====================================================================
-- Lubie Construction Works - Project Monitoring Dashboard
-- schema.sql  version 0.2.0  (Stage 2 design baseline, 21/10/2026)
-- Target: MariaDB 10.4+ (XAMPP) and MySQL 8.0.16+ (production).
-- Conventions: utf8mb4; money DECIMAL(15,2) GH₵; percentages DECIMAL(5,2);
-- dates stored ISO (DATE); timestamps DATETIME in Africa/Accra (= UTC, no DST).
-- Requirement IDs in comments refer to docs/stage1/05_Master_Requirements.md.
-- Matches docs/diagrams/01-database-erd.drawio (generated from this file).
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. Identity, access and sessions (FR-01 to FR-05, FR-29, NFR-01)
-- ---------------------------------------------------------------------

CREATE TABLE roles (
  id            TINYINT UNSIGNED NOT NULL,
  code          VARCHAR(20)  NOT NULL,          -- admin, director, pm, qs, site, client
  name          VARCHAR(60)  NOT NULL,
  dashboard_path VARCHAR(30) NOT NULL,          -- /admin, /director, /pm, /qs, /site, /client
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_id              TINYINT UNSIGNED NOT NULL,
  username             VARCHAR(50)  NOT NULL,
  full_name            VARCHAR(120) NOT NULL,
  phone_e164           VARCHAR(16)  NULL,       -- +233XXXXXXXXX, used for SMS
  email                VARCHAR(190) NULL,
  password_hash        VARCHAR(255) NOT NULL,   -- password_hash(); never plain text
  must_change_password TINYINT(1)   NOT NULL DEFAULT 1,
  status               ENUM('active','locked','deactivated') NOT NULL DEFAULT 'active',
  failed_login_count   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until         DATETIME     NULL,
  last_login_at        DATETIME     NULL,
  theme_pref           ENUM('light','dark','system') NOT NULL DEFAULT 'system',  -- FR-41
  sms_opt_in           TINYINT(1)   NOT NULL DEFAULT 1,
  created_by           INT UNSIGNED NULL,
  created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  KEY ix_users_role (role_id),
  CONSTRAINT fk_users_role    FOREIGN KEY (role_id)    REFERENCES roles (id),
  CONSTRAINT fk_users_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT ck_users_phone   CHECK (phone_e164 IS NULL OR phone_e164 LIKE '+233%')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Database session store (stateless web tier, NFR-11)
CREATE TABLE sessions (
  id            VARCHAR(128) NOT NULL,
  user_id       INT UNSIGNED NULL,
  data          MEDIUMBLOB   NOT NULL,
  ip_address    VARCHAR(45)  NULL,
  user_agent    VARCHAR(255) NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_activity DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_sessions_user (user_id),
  KEY ix_sessions_activity (last_activity),
  CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Login throttling by username hash and IP (FR-29, ST-08)
CREATE TABLE login_attempts (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username_hash CHAR(64)     NOT NULL,          -- SHA-256 of lower-cased username
  ip_address    VARCHAR(45)  NOT NULL,
  success       TINYINT(1)   NOT NULL,
  attempted_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_login_user_time (username_hash, attempted_at),
  KEY ix_login_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. Projects, assignments, activities, milestones (FR-04, FR-06 to FR-08, FR-13)
-- ---------------------------------------------------------------------

CREATE TABLE projects (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code                VARCHAR(20)  NOT NULL,
  name                VARCHAR(150) NOT NULL,
  project_type        VARCHAR(60)  NOT NULL,     -- free list: road, building, etc.
  location            VARCHAR(150) NOT NULL,
  client_name         VARCHAR(150) NOT NULL,
  contract_sum        DECIMAL(15,2) NOT NULL,
  planned_start       DATE NOT NULL,
  planned_finish      DATE NOT NULL,
  actual_start        DATE NULL,
  actual_finish       DATE NULL,
  payment_period_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,   -- A9 parameter p
  status              ENUM('planning','active','on_hold','completed','archived') NOT NULL DEFAULT 'planning',
  is_demo             TINYINT(1) NOT NULL DEFAULT 0,             -- synthetic data flag
  last_change_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,  -- polling stamp (FR-24)
  last_alert_eval_on  DATE NULL,                                  -- daily evaluation guard
  created_by          INT UNSIGNED NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_projects_code (code),
  KEY ix_projects_status (status),
  CONSTRAINT fk_projects_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT ck_projects_dates   CHECK (planned_finish >= planned_start),
  CONSTRAINT ck_projects_actual  CHECK (actual_finish IS NULL OR actual_start IS NULL OR actual_finish >= actual_start),
  CONSTRAINT ck_projects_sum     CHECK (contract_sum >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_assignments (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id  INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  assigned_by INT UNSIGNED NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_assign_project_user (project_id, user_id),
  KEY ix_assign_user (user_id),
  CONSTRAINT fk_assign_project FOREIGN KEY (project_id)  REFERENCES projects (id),
  CONSTRAINT fk_assign_user    FOREIGN KEY (user_id)     REFERENCES users (id),
  CONSTRAINT fk_assign_by      FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activities (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id     INT UNSIGNED NOT NULL,
  code           VARCHAR(20)  NOT NULL,
  name           VARCHAR(150) NOT NULL,
  planned_start  DATE NOT NULL,
  planned_finish DATE NOT NULL,
  actual_start   DATE NULL,
  actual_finish  DATE NULL,
  budgeted_cost  DECIMAL(15,2) NULL,              -- set by PM only; hidden from SE (FR-08)
  status         ENUM('active','archived') NOT NULL DEFAULT 'active',
  created_by     INT UNSIGNED NULL,
  updated_by     INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_activities_code (project_id, code),
  KEY ix_activities_dates (project_id, planned_finish),
  CONSTRAINT fk_act_project FOREIGN KEY (project_id) REFERENCES projects (id),
  CONSTRAINT fk_act_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_act_updater FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT ck_act_dates   CHECK (planned_finish >= planned_start),
  CONSTRAINT ck_act_actual  CHECK (actual_finish IS NULL OR actual_start IS NULL OR actual_finish >= actual_start),
  CONSTRAINT ck_act_cost    CHECK (budgeted_cost IS NULL OR budgeted_cost >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional finish-to-start dependencies (FR-08). No critical-path calculation.
CREATE TABLE activity_dependencies (
  activity_id    INT UNSIGNED NOT NULL,
  predecessor_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (activity_id, predecessor_id),
  KEY ix_dep_pred (predecessor_id),
  CONSTRAINT fk_dep_activity FOREIGN KEY (activity_id)    REFERENCES activities (id),
  CONSTRAINT fk_dep_pred     FOREIGN KEY (predecessor_id) REFERENCES activities (id),
  CONSTRAINT ck_dep_self     CHECK (activity_id <> predecessor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE milestones (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id    INT UNSIGNED NOT NULL,
  activity_id   INT UNSIGNED NULL,
  name          VARCHAR(150) NOT NULL,
  due_date      DATE NOT NULL,
  achieved_date DATE NULL,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_ms_project_due (project_id, due_date),
  CONSTRAINT fk_ms_project  FOREIGN KEY (project_id)  REFERENCES projects (id),
  CONSTRAINT fk_ms_activity FOREIGN KEY (activity_id) REFERENCES activities (id) ON DELETE SET NULL,
  CONSTRAINT fk_ms_creator  FOREIGN KEY (created_by)  REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. Progress and approval (FR-09, FR-30, FR-32, FR-39)
-- ---------------------------------------------------------------------

CREATE TABLE delay_reasons (
  id              SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code            VARCHAR(30) NOT NULL,
  label           VARCHAR(80) NOT NULL,
  raises_critical TINYINT(1)  NOT NULL DEFAULT 0,   -- 1 = A15 (work stoppage, safety incident)
  is_active       TINYINT(1)  NOT NULL DEFAULT 1,
  sort_order      SMALLINT    NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_delay_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE progress_records (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  activity_id      INT UNSIGNED NOT NULL,
  record_date      DATE NOT NULL,
  percent_complete DECIMAL(5,2) NOT NULL,
  remarks          VARCHAR(500) NULL,
  delay_reason_id  SMALLINT UNSIGNED NULL,
  status           ENUM('submitted','approved','returned') NOT NULL DEFAULT 'submitted',
  submitted_by     INT UNSIGNED NOT NULL,
  submitted_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_by      INT UNSIGNED NULL,
  reviewed_at      DATETIME NULL,
  return_reason    VARCHAR(300) NULL,
  resubmission_of  INT UNSIGNED NULL,               -- earlier returned record
  client_uuid      CHAR(36) NULL,                   -- offline idempotency key (FR-38)
  PRIMARY KEY (id),
  UNIQUE KEY uq_progress_client_uuid (client_uuid),
  KEY ix_progress_activity (activity_id, status, record_date),
  KEY ix_progress_status (status),
  CONSTRAINT fk_pr_activity FOREIGN KEY (activity_id)     REFERENCES activities (id),
  CONSTRAINT fk_pr_reason   FOREIGN KEY (delay_reason_id) REFERENCES delay_reasons (id),
  CONSTRAINT fk_pr_submit   FOREIGN KEY (submitted_by)    REFERENCES users (id),
  CONSTRAINT fk_pr_review   FOREIGN KEY (reviewed_by)     REFERENCES users (id),
  CONSTRAINT fk_pr_resub    FOREIGN KEY (resubmission_of) REFERENCES progress_records (id),
  CONSTRAINT ck_pr_percent  CHECK (percent_complete >= 0 AND percent_complete <= 100),
  CONSTRAINT ck_pr_return   CHECK (status <> 'returned' OR return_reason IS NOT NULL),
  CONSTRAINT ck_pr_review   CHECK (status = 'submitted' OR reviewed_by IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One optional photo per progress record (FR-39, Could). Stored in DB, never on web disk.
CREATE TABLE progress_photos (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  progress_record_id INT UNSIGNED NOT NULL,
  mime_type          VARCHAR(20) NOT NULL,
  byte_size          INT UNSIGNED NOT NULL,
  sha256             CHAR(64) NOT NULL,
  content            MEDIUMBLOB NOT NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_photo_record (progress_record_id),
  CONSTRAINT fk_photo_record FOREIGN KEY (progress_record_id) REFERENCES progress_records (id),
  CONSTRAINT ck_photo_mime   CHECK (mime_type IN ('image/jpeg','image/png','image/webp')),
  CONSTRAINT ck_photo_size   CHECK (byte_size <= 307200)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. Cost and payment (FR-14 to FR-16, FR-28, FR-31)
-- ---------------------------------------------------------------------

-- Budget versions. Exactly one current approved version per project:
-- is_current is 1 or NULL, and (project_id, is_current) is unique.
CREATE TABLE budgets (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id     INT UNSIGNED NOT NULL,
  version_no     SMALLINT UNSIGNED NOT NULL,
  amount         DECIMAL(15,2) NOT NULL,
  reason         VARCHAR(300) NULL,
  status         ENUM('draft','pending','approved','rejected','superseded') NOT NULL DEFAULT 'draft',
  is_current     TINYINT(1) NULL,
  requested_by   INT UNSIGNED NOT NULL,
  requested_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  decided_by     INT UNSIGNED NULL,
  decided_at     DATETIME NULL,
  decision_note  VARCHAR(300) NULL,
  supersedes_id  INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_budget_version (project_id, version_no),
  UNIQUE KEY uq_budget_current (project_id, is_current),
  KEY ix_budget_status (status),
  CONSTRAINT fk_budget_project   FOREIGN KEY (project_id)    REFERENCES projects (id),
  CONSTRAINT fk_budget_requester FOREIGN KEY (requested_by)  REFERENCES users (id),
  CONSTRAINT fk_budget_decider   FOREIGN KEY (decided_by)    REFERENCES users (id),
  CONSTRAINT fk_budget_supersede FOREIGN KEY (supersedes_id) REFERENCES budgets (id),
  CONSTRAINT ck_budget_amount    CHECK (amount >= 0),
  CONSTRAINT ck_budget_current   CHECK (is_current IS NULL OR (is_current = 1 AND status = 'approved')),
  CONSTRAINT ck_budget_duties    CHECK (decided_by IS NULL OR decided_by <> requested_by OR version_no = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE expenditure_categories (
  id         SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code       VARCHAR(30) NOT NULL,
  label      VARCHAR(80) NOT NULL,
  is_active  TINYINT(1)  NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_expcat_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE expenditures (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id    INT UNSIGNED NOT NULL,
  activity_id   INT UNSIGNED NULL,
  category_id   SMALLINT UNSIGNED NOT NULL,
  expense_date  DATE NOT NULL,
  amount        DECIMAL(15,2) NOT NULL,
  description   VARCHAR(300) NOT NULL,
  reference_no  VARCHAR(50)  NULL,              -- voucher or receipt number
  supplier_name VARCHAR(150) NULL,              -- hidden from SE and Client
  status        ENUM('active','voided') NOT NULL DEFAULT 'active',
  void_reason   VARCHAR(300) NULL,
  recorded_by   INT UNSIGNED NOT NULL,
  voided_by     INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_exp_reference (project_id, reference_no),
  KEY ix_exp_project_date (project_id, status, expense_date),
  CONSTRAINT fk_exp_project  FOREIGN KEY (project_id)  REFERENCES projects (id),
  CONSTRAINT fk_exp_activity FOREIGN KEY (activity_id) REFERENCES activities (id),
  CONSTRAINT fk_exp_category FOREIGN KEY (category_id) REFERENCES expenditure_categories (id),
  CONSTRAINT fk_exp_recorder FOREIGN KEY (recorded_by) REFERENCES users (id),
  CONSTRAINT fk_exp_voider   FOREIGN KEY (voided_by)   REFERENCES users (id),
  CONSTRAINT ck_exp_amount   CHECK (amount > 0),
  CONSTRAINT ck_exp_void     CHECK (status = 'active' OR void_reason IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Interim valuations / payment certificates. 'overdue' is derived, not stored (3NF):
-- not fully paid AND today > submitted_date + projects.payment_period_days.
CREATE TABLE payment_certificates (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id       INT UNSIGNED NOT NULL,
  cert_no          VARCHAR(20) NOT NULL,
  valuation_amount DECIMAL(15,2) NOT NULL,
  amount_certified DECIMAL(15,2) NULL,
  submitted_date   DATE NOT NULL,
  certified_date   DATE NULL,
  status           ENUM('submitted','certified','part_paid','paid') NOT NULL DEFAULT 'submitted',
  remarks          VARCHAR(300) NULL,
  recorded_by      INT UNSIGNED NOT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cert_no (project_id, cert_no),
  KEY ix_cert_status (project_id, status),
  CONSTRAINT fk_cert_project  FOREIGN KEY (project_id)  REFERENCES projects (id),
  CONSTRAINT fk_cert_recorder FOREIGN KEY (recorded_by) REFERENCES users (id),
  CONSTRAINT ck_cert_amounts  CHECK (valuation_amount >= 0 AND (amount_certified IS NULL OR amount_certified >= 0)),
  CONSTRAINT ck_cert_state    CHECK (status = 'submitted' OR (amount_certified IS NOT NULL AND certified_date IS NOT NULL)),
  CONSTRAINT ck_cert_dates    CHECK (certified_date IS NULL OR certified_date >= submitted_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_transactions (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  certificate_id INT UNSIGNED NOT NULL,
  paid_date      DATE NOT NULL,
  amount         DECIMAL(15,2) NOT NULL,
  reference_no   VARCHAR(50) NOT NULL,
  recorded_by    INT UNSIGNED NOT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pay_reference (certificate_id, reference_no),
  CONSTRAINT fk_pay_cert     FOREIGN KEY (certificate_id) REFERENCES payment_certificates (id),
  CONSTRAINT fk_pay_recorder FOREIGN KEY (recorded_by)    REFERENCES users (id),
  CONSTRAINT ck_pay_amount   CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. Resources (FR-18, FR-19, FR-33, FR-34)
-- ---------------------------------------------------------------------

CREATE TABLE resource_categories (
  id    TINYINT UNSIGNED NOT NULL,
  code  VARCHAR(20) NOT NULL,           -- labour, material, equipment
  label VARCHAR(60) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rescat_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE resources (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id       INT UNSIGNED NOT NULL,
  category_id      TINYINT UNSIGNED NOT NULL,
  name             VARCHAR(120) NOT NULL,
  unit             VARCHAR(20)  NOT NULL,          -- from standard unit list (NFR-12)
  planned_qty      DECIMAL(12,2) NULL,
  reorder_level    DECIMAL(12,2) NULL,             -- materials only (A10)
  unit_rate        DECIMAL(15,2) NULL,             -- supplier price / labour rate: hidden from SE, Client
  equipment_status ENUM('working','faulty','idle','off_site') NULL,   -- equipment only (A14)
  status           ENUM('active','archived') NOT NULL DEFAULT 'active',
  created_by       INT UNSIGNED NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_res_name (project_id, category_id, name),
  CONSTRAINT fk_res_project  FOREIGN KEY (project_id)  REFERENCES projects (id),
  CONSTRAINT fk_res_category FOREIGN KEY (category_id) REFERENCES resource_categories (id),
  CONSTRAINT fk_res_creator  FOREIGN KEY (created_by)  REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT ck_res_qty      CHECK ((planned_qty IS NULL OR planned_qty >= 0) AND (reorder_level IS NULL OR reorder_level >= 0) AND (unit_rate IS NULL OR unit_rate >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dated movements. received/used for materials; attendance (head count) for labour;
-- hours for equipment. Balance = sum(received) - sum(used).
CREATE TABLE resource_usage (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  resource_id INT UNSIGNED NOT NULL,
  activity_id INT UNSIGNED NULL,
  usage_date  DATE NOT NULL,
  movement    ENUM('received','used','attendance','hours') NOT NULL,
  quantity    DECIMAL(12,2) NOT NULL,
  remarks     VARCHAR(300) NULL,
  recorded_by INT UNSIGNED NOT NULL,
  client_uuid CHAR(36) NULL,                      -- offline idempotency key (FR-38)
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usage_client_uuid (client_uuid),
  KEY ix_usage_resource_date (resource_id, usage_date),
  CONSTRAINT fk_usage_resource FOREIGN KEY (resource_id) REFERENCES resources (id),
  CONSTRAINT fk_usage_activity FOREIGN KEY (activity_id) REFERENCES activities (id),
  CONSTRAINT fk_usage_recorder FOREIGN KEY (recorded_by) REFERENCES users (id),
  CONSTRAINT ck_usage_qty      CHECK (quantity >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE material_requests (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id   INT UNSIGNED NOT NULL,
  resource_id  INT UNSIGNED NOT NULL,
  quantity     DECIMAL(12,2) NOT NULL,
  required_by  DATE NOT NULL,
  status       ENUM('requested','approved','delivered','cancelled') NOT NULL DEFAULT 'requested',
  remarks      VARCHAR(300) NULL,
  requested_by INT UNSIGNED NOT NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  decided_by   INT UNSIGNED NULL,
  decided_at   DATETIME NULL,
  PRIMARY KEY (id),
  KEY ix_mreq_project_status (project_id, status),
  CONSTRAINT fk_mreq_project   FOREIGN KEY (project_id)   REFERENCES projects (id),
  CONSTRAINT fk_mreq_resource  FOREIGN KEY (resource_id)  REFERENCES resources (id),
  CONSTRAINT fk_mreq_requester FOREIGN KEY (requested_by) REFERENCES users (id),
  CONSTRAINT fk_mreq_decider   FOREIGN KEY (decided_by)   REFERENCES users (id),
  CONSTRAINT ck_mreq_qty       CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. Alerts, thresholds and SMS (FR-20 to FR-22)
-- ---------------------------------------------------------------------

-- Thresholds: scope 'global' (Director limits, project_id NULL) or 'project' (PM, within limits).
CREATE TABLE alert_settings (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id       INT UNSIGNED NULL,
  setting_key      VARCHAR(40) NOT NULL,        -- e.g. budget_warning_pct, cpi_min
  value            DECIMAL(10,2) NOT NULL,
  min_allowed      DECIMAL(10,2) NULL,          -- global rows: limits for project overrides
  max_allowed      DECIMAL(10,2) NULL,
  set_by           INT UNSIGNED NULL,
  set_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  scope_project_id INT UNSIGNED AS (COALESCE(project_id, 0)) STORED,
  PRIMARY KEY (id),
  UNIQUE KEY uq_alertset_scope_key (scope_project_id, setting_key),
  CONSTRAINT fk_alertset_project FOREIGN KEY (project_id) REFERENCES projects (id),
  CONSTRAINT fk_alertset_user    FOREIGN KEY (set_by)     REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT ck_alertset_limits  CHECK (min_allowed IS NULL OR max_allowed IS NULL OR min_allowed <= max_allowed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- QS proposals for cost thresholds, approved by the Director (FR-21)
CREATE TABLE threshold_proposals (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id     INT UNSIGNED NULL,
  setting_key    VARCHAR(40) NOT NULL,
  proposed_value DECIMAL(10,2) NOT NULL,
  justification  VARCHAR(300) NULL,
  status         ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  proposed_by    INT UNSIGNED NOT NULL,
  proposed_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  decided_by     INT UNSIGNED NULL,
  decided_at     DATETIME NULL,
  PRIMARY KEY (id),
  KEY ix_tp_status (status),
  CONSTRAINT fk_tp_project  FOREIGN KEY (project_id)  REFERENCES projects (id),
  CONSTRAINT fk_tp_proposer FOREIGN KEY (proposed_by) REFERENCES users (id),
  CONSTRAINT fk_tp_decider  FOREIGN KEY (decided_by)  REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- An alert instance (thesis entity "notifications"). De-duplication: only one open
-- alert per dedup_key (rule + record); open_dedup_key is NULL once cleared.
CREATE TABLE notifications (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  rule_code      VARCHAR(4)  NOT NULL,         -- A1 .. A15, A6b
  severity       ENUM('information','warning','medium','high','critical') NOT NULL,
  project_id     INT UNSIGNED NOT NULL,
  entity_type    VARCHAR(30) NOT NULL,         -- activity, milestone, budget, certificate, ...
  entity_id      INT UNSIGNED NULL,
  dedup_key      VARCHAR(120) NOT NULL,        -- e.g. A1:activity:42
  title          VARCHAR(150) NOT NULL,
  description    VARCHAR(500) NOT NULL,
  is_internal    TINYINT(1)  NOT NULL DEFAULT 1,   -- 0 only for client-safe notices (A4)
  status         ENUM('generated','resolved') NOT NULL DEFAULT 'generated',
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cleared_at     DATETIME NULL,
  open_dedup_key VARCHAR(120) AS (CASE WHEN cleared_at IS NULL THEN dedup_key ELSE NULL END) STORED,
  PRIMARY KEY (id),
  UNIQUE KEY uq_notif_open (open_dedup_key),
  KEY ix_notif_project (project_id, status, created_at),
  CONSTRAINT fk_notif_project FOREIGN KEY (project_id) REFERENCES projects (id),
  CONSTRAINT ck_notif_clear   CHECK (status = 'generated' OR cleared_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-recipient state: delivered (in-app), read, acknowledged; SMS sub-status.
CREATE TABLE notification_recipients (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  notification_id INT UNSIGNED NOT NULL,
  user_id         INT UNSIGNED NOT NULL,
  delivered_at    DATETIME NULL,
  read_at         DATETIME NULL,
  acknowledged_at DATETIME NULL,
  sms_status      ENUM('none','queued','sent','failed','capped') NOT NULL DEFAULT 'none',
  PRIMARY KEY (id),
  UNIQUE KEY uq_notif_user (notification_id, user_id),
  KEY ix_nr_user_unread (user_id, read_at),
  CONSTRAINT fk_nr_notif FOREIGN KEY (notification_id) REFERENCES notifications (id),
  CONSTRAINT fk_nr_user  FOREIGN KEY (user_id)         REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SMS attempts and cap accounting (FR-22). Credentials are never stored here.
CREATE TABLE sms_log (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  recipient_id INT UNSIGNED NOT NULL,
  phone_e164   VARCHAR(16) NOT NULL,
  message      VARCHAR(320) NOT NULL,
  provider     VARCHAR(30) NOT NULL,          -- sandbox, arkesel
  status       ENUM('queued','sent','failed','capped') NOT NULL,
  provider_ref VARCHAR(80) NULL,
  error_text   VARCHAR(300) NULL,
  attempts     TINYINT UNSIGNED NOT NULL DEFAULT 1,
  retry_due    TINYINT(1) NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at      DATETIME NULL,
  PRIMARY KEY (id),
  KEY ix_sms_recipient (recipient_id),
  KEY ix_sms_phone_day (phone_e164, created_at),
  CONSTRAINT fk_sms_recipient FOREIGN KEY (recipient_id) REFERENCES notification_recipients (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. Audit, backup, settings, offline sync (FR-25, FR-36, FR-38, NFR-10)
-- ---------------------------------------------------------------------

-- Append-only. The application DB account has INSERT and SELECT only on this table.
CREATE TABLE audit_logs (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id            INT UNSIGNED NULL,          -- NULL for failed login with unknown username
  username_attempted VARCHAR(50) NULL,
  action             VARCHAR(40) NOT NULL,       -- login, login_failed, logout, create, update, archive, approve, ...
  entity_type        VARCHAR(40) NULL,
  entity_id          VARCHAR(40) NULL,
  old_values         TEXT NULL,                  -- JSON; secrets never stored
  new_values         TEXT NULL,                  -- JSON
  ip_address         VARCHAR(45) NULL,
  user_agent         VARCHAR(255) NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_audit_user_time (user_id, created_at),
  KEY ix_audit_entity (entity_type, entity_id),
  KEY ix_audit_action_time (action, created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE backup_log (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kind         ENUM('manual','scheduled','restore_test','restore') NOT NULL,
  status       ENUM('started','succeeded','failed') NOT NULL DEFAULT 'started',
  file_name    VARCHAR(120) NULL,
  byte_size    BIGINT UNSIGNED NULL,
  sha256       CHAR(64) NULL,
  retention    ENUM('daily','weekly') NULL,
  row_counts   TEXT NULL,                        -- JSON table -> count, for restore verification
  triggered_by INT UNSIGNED NULL,                -- NULL = external scheduler
  notes        VARCHAR(300) NULL,
  started_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at  DATETIME NULL,
  PRIMARY KEY (id),
  KEY ix_backup_time (started_at),
  CONSTRAINT fk_backup_user FOREIGN KEY (triggered_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE system_settings (
  setting_key VARCHAR(60) NOT NULL,
  value       VARCHAR(255) NOT NULL,
  value_type  ENUM('int','decimal','bool','string') NOT NULL,
  description VARCHAR(200) NOT NULL,
  updated_by  INT UNSIGNED NULL,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key),
  CONSTRAINT fk_sysset_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Offline sync idempotency (FR-38): one row per client-generated mutation.
CREATE TABLE sync_requests (
  client_uuid  CHAR(36) NOT NULL,
  user_id      INT UNSIGNED NOT NULL,
  endpoint     VARCHAR(80) NOT NULL,
  result_code  SMALLINT UNSIGNED NOT NULL,       -- HTTP status returned first time
  entity_type  VARCHAR(40) NULL,
  entity_id    INT UNSIGNED NULL,
  received_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (client_uuid),
  KEY ix_sync_user (user_id, received_at),
  CONSTRAINT fk_sync_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE schema_migrations (
  version    VARCHAR(20) NOT NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (version) VALUES ('0.2.0');

SET FOREIGN_KEY_CHECKS = 1;
