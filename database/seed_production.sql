-- =====================================================================
-- seed_production.sql  version 0.2.0
-- Reference data only. No users, no demo data, no passwords.
-- The first Administrator is created with the one-time CLI command
--   php bin/console.php setup:first-admin
-- which prompts for the password and forces a change at first login.
-- =====================================================================
SET NAMES utf8mb4;

INSERT INTO roles (id, code, name, dashboard_path) VALUES
 (1, 'admin',    'Administrator',            '/admin'),
 (2, 'director', 'Director',                 '/director'),
 (3, 'pm',       'Project Manager',          '/pm'),
 (4, 'qs',       'Quantity Surveyor',        '/qs'),
 (5, 'site',     'Site Engineer/Supervisor', '/site'),
 (6, 'client',   'Client',                   '/client');

INSERT INTO resource_categories (id, code, label) VALUES
 (1, 'labour',    'Labour'),
 (2, 'material',  'Material'),
 (3, 'equipment', 'Equipment');

INSERT INTO delay_reasons (code, label, raises_critical, sort_order) VALUES
 ('weather',          'Rain or flooding',                 0, 10),
 ('material_delay',   'Material not delivered',           0, 20),
 ('labour_shortage',  'Labour shortage',                  0, 30),
 ('equipment_fault',  'Equipment breakdown',              0, 40),
 ('awaiting_instr',   'Awaiting instruction or drawing',  0, 50),
 ('variation',        'Variation or design change',       0, 60),
 ('payment_delay',    'Payment delay',                    0, 70),
 ('site_access',      'Site access problem',              0, 80),
 ('work_stoppage',    'Work stoppage',                    1, 90),
 ('safety_incident',  'Safety incident',                  1, 100),
 ('other',            'Other (explain in remarks)',       0, 110);

INSERT INTO expenditure_categories (code, label) VALUES
 ('materials',     'Materials'),
 ('labour',        'Labour'),
 ('plant',         'Plant and equipment'),
 ('subcontract',   'Subcontract'),
 ('transport',     'Transport'),
 ('overheads',     'Site overheads'),
 ('other',         'Other');

-- Global thresholds (Director owns). min/max bound project-level overrides (PM).
INSERT INTO alert_settings (project_id, setting_key, value, min_allowed, max_allowed) VALUES
 (NULL, 'budget_warning_pct',     80.00, 50.00, 95.00),   -- A5
 (NULL, 'budget_overrun_sms_pct', 10.00,  0.00, 25.00),   -- A6b
 (NULL, 'cpi_min',                 0.90,  0.50,  1.00),   -- A7
 (NULL, 'spi_min',                 0.90,  0.50,  1.00),   -- A8
 (NULL, 'milestone_due_days',      7.00,  1.00, 30.00),   -- A3
 (NULL, 'stale_data_days',         7.00,  1.00, 30.00),   -- A11
 (NULL, 'delay_sms_days',         14.00,  1.00, 60.00);   -- A1 SMS level

INSERT INTO system_settings (setting_key, value, value_type, description) VALUES
 ('refresh_interval_seconds', '60',          'int',    'Dashboard polling interval (FR-24)'),
 ('session_idle_minutes',     '20',          'int',    'Idle timeout before re-login'),
 ('lockout_threshold',        '5',           'int',    'Failed logins before temporary lock'),
 ('lockout_minutes',          '15',          'int',    'Lock duration'),
 ('password_min_length',      '10',          'int',    'Minimum password length'),
 ('sms_enabled',              '0',           'bool',   'SMS off until a provider is configured and tested'),
 ('sms_provider',             'sandbox',     'string', 'sandbox (log only) or arkesel'),
 ('sms_daily_cap_per_user',   '5',           'int',    'Maximum SMS per user per day'),
 ('sms_monthly_cap_total',    '0',           'int',    'Company-wide monthly SMS cap; 0 = no cap'),
 ('backup_daily_keep',        '7',           'int',    'Daily backups retained'),
 ('backup_weekly_keep',       '4',           'int',    'Weekly backups retained'),
 ('offline_enabled',          '1',           'bool',   'Offline mode (FR-38, Iteration 1)'),
 ('app_timezone',             'Africa/Accra','string', 'Display and business-date time zone'),
 ('currency_code',            'GHS',         'string', 'Displayed as GH₵');
