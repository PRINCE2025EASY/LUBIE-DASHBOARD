-- =====================================================================
-- seed_demo.sql  version 0.2.0   *** SYNTHETIC DEMONSTRATION DATA ONLY ***
-- No real people, projects, amounts or phone numbers. Every project has
-- is_demo = 1 and every username ends in _demo. Remove with remove_demo.sql.
-- Load AFTER schema.sql and seed_production.sql, into a DEV or TEST database only.
--
-- Demo password for every demo account (local demonstration only, never production):
--   Demo-Lubie-2026!
-- Dates are relative to the day of import (@t = CURDATE()) so that every alert
-- rule A1 to A15 has a triggering condition whenever the seed is loaded.
-- =====================================================================
SET NAMES utf8mb4;
SET @t  = CURDATE();
SET @pw = '$2y$12$ws5ArxtWAWp4AzbZzZ8R0.LGya953Zk84Bw8Lnd3JvlcLxHhcl6vy';

-- ---------------- Users: at least one per role ----------------------
INSERT INTO users (id, role_id, username, full_name, phone_e164, password_hash, must_change_password, status) VALUES
 ( 1, 1, 'admin_demo',    'Demo Administrator',       '+233200000001', @pw, 0, 'active'),
 ( 2, 2, 'director_demo', 'Demo Director (Kwame D.)', '+233200000002', @pw, 0, 'active'),
 ( 3, 3, 'pm1_demo',      'Demo PM Abena A.',         '+233200000003', @pw, 0, 'active'),
 ( 4, 3, 'pm2_demo',      'Demo PM Yaw B.',           '+233200000004', @pw, 0, 'active'),
 ( 5, 4, 'qs1_demo',      'Demo QS Esi C.',           '+233200000005', @pw, 0, 'active'),
 ( 6, 4, 'qs2_demo',      'Demo QS Kojo E.',          '+233200000006', @pw, 0, 'active'),
 ( 7, 5, 'se1_demo',      'Demo SE Fuseini F.',       '+233200000007', @pw, 0, 'active'),
 ( 8, 5, 'se2_demo',      'Demo SE Mariama G.',       '+233200000008', @pw, 0, 'active'),
 ( 9, 5, 'se3_demo',      'Demo Supervisor Issah H.', '+233200000009', @pw, 0, 'active'),
 (10, 6, 'client1_demo',  'Demo Client (Assembly)',   '+233200000010', @pw, 0, 'active'),
 (11, 6, 'client2_demo',  'Demo Client (Co-op)',      '+233200000011', @pw, 0, 'active'),
 (12, 6, 'client3_demo',  'Demo Client (Education)',  '+233200000012', @pw, 0, 'active'),
 (13, 5, 'se4_demo',      'Demo SE Deactivated',      '+233200000013', @pw, 0, 'deactivated');

-- ---------------- Projects mirroring P1, P2, P3 in type and size -----
INSERT INTO projects (id, code, name, project_type, location, client_name, contract_sum,
                      planned_start, planned_finish, actual_start, actual_finish, payment_period_days, status, is_demo, created_by) VALUES
 (1, 'DEMO-P1', 'DEMO Feeder road and drainage (ongoing)', 'Road and drainage', 'Demo site, Wa Municipal', 'Demo Municipal Assembly', 2650000.00,
     @t - INTERVAL 240 DAY, @t + INTERVAL 120 DAY, @t - INTERVAL 235 DAY, NULL, 30, 'active', 1, 3),
 (2, 'DEMO-P2', 'DEMO Two-storey office building (ongoing)', 'Building', 'Demo site, Jirapa', 'Demo Farmers Co-operative Union', 1950000.00,
     @t - INTERVAL 150 DAY, @t + INTERVAL 150 DAY, @t - INTERVAL 148 DAY, NULL, 30, 'active', 1, 4),
 (3, 'DEMO-P3', 'DEMO Six-unit classroom block (completed)', 'Building (classroom)', 'Demo site, Nadowli', 'Demo District Education Office', 700000.00,
     @t - INTERVAL 600 DAY, @t - INTERVAL 330 DAY, @t - INTERVAL 598 DAY, @t - INTERVAL 310 DAY, 30, 'completed', 1, 3);

INSERT INTO project_assignments (project_id, user_id, assigned_by) VALUES
 (1, 3, 1), (1, 5, 1), (1, 7, 3), (1, 9, 3), (1, 10, 3),
 (2, 4, 1), (2, 5, 1), (2, 8, 4), (2, 11, 4), (2, 13, 4),
 (3, 3, 1), (3, 6, 1), (3, 9, 3), (3, 12, 3);

-- ---------------- Activities (BAC P1 2,400,000; P2 1,800,000; P3 650,000) ----
INSERT INTO activities (id, project_id, code, name, planned_start, planned_finish, actual_start, actual_finish, budgeted_cost, created_by) VALUES
 ( 1, 1, 'R01', 'Site clearance and setting out', @t - INTERVAL 240 DAY, @t - INTERVAL 210 DAY, @t - INTERVAL 235 DAY, @t - INTERVAL 205 DAY, 120000.00, 3),
 ( 2, 1, 'R02', 'Earthworks and formation',       @t - INTERVAL 210 DAY, @t - INTERVAL 120 DAY, @t - INTERVAL 205 DAY, @t - INTERVAL 115 DAY, 600000.00, 3),
 ( 3, 1, 'R03', 'Culverts and side drains',       @t - INTERVAL 150 DAY, @t - INTERVAL  20 DAY, @t - INTERVAL 148 DAY, NULL,                 480000.00, 3),
 ( 4, 1, 'R04', 'Sub-base and base course',       @t - INTERVAL  90 DAY, @t + INTERVAL  30 DAY, @t - INTERVAL  88 DAY, NULL,                 540000.00, 3),
 ( 5, 1, 'R05', 'Bituminous surface dressing',    @t - INTERVAL   5 DAY, @t + INTERVAL 100 DAY, NULL,                  NULL,                 510000.00, 3),
 ( 6, 1, 'R06', 'Road furniture and markings',    @t + INTERVAL  60 DAY, @t + INTERVAL 120 DAY, NULL,                  NULL,                 150000.00, 9),
 ( 7, 2, 'B01', 'Substructure',                   @t - INTERVAL 150 DAY, @t - INTERVAL 100 DAY, @t - INTERVAL 148 DAY, @t - INTERVAL  95 DAY, 360000.00, 4),
 ( 8, 2, 'B02', 'Reinforced concrete frame',      @t - INTERVAL 100 DAY, @t + INTERVAL  20 DAY, @t - INTERVAL  96 DAY, NULL,                 720000.00, 4),
 ( 9, 2, 'B03', 'Blockwork',                      @t - INTERVAL  60 DAY, @t + INTERVAL  40 DAY, @t - INTERVAL  58 DAY, NULL,                 300000.00, 4),
 (10, 2, 'B04', 'Roofing',                        @t + INTERVAL  10 DAY, @t + INTERVAL  60 DAY, NULL,                  NULL,                 240000.00, 4),
 (11, 2, 'B05', 'Finishes and services',          @t + INTERVAL  40 DAY, @t + INTERVAL 150 DAY, NULL,                  NULL,                 180000.00, 8),
 (12, 3, 'C01', 'Foundations',                    @t - INTERVAL 600 DAY, @t - INTERVAL 540 DAY, @t - INTERVAL 598 DAY, @t - INTERVAL 538 DAY, 140000.00, 3),
 (13, 3, 'C02', 'Superstructure',                 @t - INTERVAL 540 DAY, @t - INTERVAL 430 DAY, @t - INTERVAL 538 DAY, @t - INTERVAL 425 DAY, 260000.00, 3),
 (14, 3, 'C03', 'Roofing',                        @t - INTERVAL 430 DAY, @t - INTERVAL 380 DAY, @t - INTERVAL 425 DAY, @t - INTERVAL 370 DAY, 120000.00, 3),
 (15, 3, 'C04', 'Finishes and external works',    @t - INTERVAL 380 DAY, @t - INTERVAL 330 DAY, @t - INTERVAL 370 DAY, @t - INTERVAL 310 DAY, 130000.00, 3);

INSERT INTO activity_dependencies (activity_id, predecessor_id) VALUES
 (2, 1), (4, 2), (5, 4), (6, 5), (8, 7), (9, 8), (10, 8), (11, 10), (13, 12), (14, 13), (15, 14);

INSERT INTO milestones (project_id, activity_id, name, due_date, achieved_date, created_by) VALUES
 (1, 2, 'Earthworks complete',            @t - INTERVAL 120 DAY, @t - INTERVAL 115 DAY, 3),
 (1, 3, 'Drainage works complete',        @t - INTERVAL  10 DAY, NULL,                  3),  -- A4 overdue
 (1, 4, 'Base course ready for surfacing',@t + INTERVAL   3 DAY, NULL,                  3),  -- A3 due soon
 (2, 7, 'Substructure complete',          @t - INTERVAL 100 DAY, @t - INTERVAL  95 DAY, 4),
 (2, 8, 'Roof slab cast',                 @t + INTERVAL  20 DAY, NULL,                  4),
 (2, 10,'Building weather-tight',         @t + INTERVAL  60 DAY, NULL,                  4),
 (3, 13,'Superstructure complete',        @t - INTERVAL 430 DAY, @t - INTERVAL 425 DAY, 3),
 (3, 15,'Practical completion',           @t - INTERVAL 330 DAY, @t - INTERVAL 310 DAY, 3);

-- ---------------- Progress: every status; only approved counts --------------
INSERT INTO progress_records (id, activity_id, record_date, percent_complete, remarks, delay_reason_id, status,
                              submitted_by, submitted_at, reviewed_by, reviewed_at, return_reason, resubmission_of) VALUES
 ( 1, 1, @t - INTERVAL 205 DAY, 100.00, 'DEMO: clearance done',            NULL, 'approved', 7, @t - INTERVAL 205 DAY, 3, @t - INTERVAL 204 DAY, NULL, NULL),
 ( 2, 2, @t - INTERVAL 160 DAY,  50.00, 'DEMO: cut to fill ongoing',       NULL, 'approved', 7, @t - INTERVAL 160 DAY, 3, @t - INTERVAL 159 DAY, NULL, NULL),
 ( 3, 2, @t - INTERVAL 115 DAY, 100.00, 'DEMO: formation complete',        (SELECT id FROM delay_reasons WHERE code='weather'), 'approved', 7, @t - INTERVAL 115 DAY, 3, @t - INTERVAL 114 DAY, NULL, NULL),
 ( 4, 3, @t - INTERVAL  25 DAY,  80.00, 'DEMO: 8 of 10 culverts placed',   (SELECT id FROM delay_reasons WHERE code='material_delay'), 'approved', 9, @t - INTERVAL 25 DAY, 3, @t - INTERVAL 24 DAY, NULL, NULL),
 ( 5, 4, @t - INTERVAL   6 DAY,  65.00, 'DEMO: base course laid',          NULL, 'returned', 7, @t - INTERVAL 6 DAY, 3, @t - INTERVAL 5 DAY, 'DEMO: chainage not stated; please measure and resubmit', NULL),
 ( 6, 4, @t - INTERVAL   3 DAY,  70.00, 'DEMO: ch 0+000 to 2+800 complete',NULL, 'approved', 7, @t - INTERVAL 3 DAY, 3, @t - INTERVAL 3 DAY, NULL, 5),
 ( 7, 3, @t - INTERVAL   1 DAY,  85.00, 'DEMO: trench side unstable; work stopped', (SELECT id FROM delay_reasons WHERE code='work_stoppage'), 'submitted', 9, @t - INTERVAL 1 DAY, NULL, NULL, NULL, NULL),  -- A13, A15
 ( 8, 7, @t - INTERVAL  95 DAY, 100.00, 'DEMO: substructure complete',     NULL, 'approved', 8, @t - INTERVAL 95 DAY, 4, @t - INTERVAL 94 DAY, NULL, NULL),
 ( 9, 9, @t - INTERVAL  12 DAY,  40.00, 'DEMO: ground floor blockwork',    NULL, 'approved', 8, @t - INTERVAL 12 DAY, 4, @t - INTERVAL 11 DAY, NULL, NULL),
 (10, 8, @t - INTERVAL  10 DAY,  70.00, 'DEMO: first floor slab cast',     (SELECT id FROM delay_reasons WHERE code='labour_shortage'), 'approved', 8, @t - INTERVAL 10 DAY, 4, @t - INTERVAL 10 DAY, NULL, NULL),
 (11, 8, @t - INTERVAL   1 DAY,  78.00, 'DEMO: columns to roof level',     NULL, 'submitted', 8, @t - INTERVAL 1 DAY, NULL, NULL, NULL, NULL),   -- A13; P2 stale (A11)
 (12,12, @t - INTERVAL 538 DAY, 100.00, 'DEMO',                            NULL, 'approved', 9, @t - INTERVAL 538 DAY, 3, @t - INTERVAL 537 DAY, NULL, NULL),
 (13,13, @t - INTERVAL 425 DAY, 100.00, 'DEMO',                            NULL, 'approved', 9, @t - INTERVAL 425 DAY, 3, @t - INTERVAL 424 DAY, NULL, NULL),
 (14,14, @t - INTERVAL 370 DAY, 100.00, 'DEMO',                            NULL, 'approved', 9, @t - INTERVAL 370 DAY, 3, @t - INTERVAL 369 DAY, NULL, NULL),
 (15,15, @t - INTERVAL 310 DAY, 100.00, 'DEMO: completed late',            (SELECT id FROM delay_reasons WHERE code='payment_delay'), 'approved', 9, @t - INTERVAL 310 DAY, 3, @t - INTERVAL 309 DAY, NULL, NULL);
-- R05 (activity 5) has no progress and no actual start: A2 late start.
-- R03 (activity 3): planned finish 20 days ago, 80% approved: A1 delayed, SMS level (> 14 days).

-- ---------------- Budget versions: every status -------------------------------
INSERT INTO budgets (project_id, version_no, amount, reason, status, is_current, requested_by, requested_at, decided_by, decided_at, decision_note, supersedes_id) VALUES
 (1, 1, 2300000.00, 'DEMO: baseline from BoQ',            'superseded', NULL, 5, @t - INTERVAL 245 DAY, 2, @t - INTERVAL 244 DAY, 'DEMO approved', NULL),
 (1, 2, 2400000.00, 'DEMO: drainage quantities revised',  'approved',   1,    5, @t - INTERVAL 140 DAY, 2, @t - INTERVAL 138 DAY, 'DEMO approved', 1),
 (1, 3, 2900000.00, 'DEMO: request for extra haulage',    'rejected',   NULL, 3, @t - INTERVAL  40 DAY, 2, @t - INTERVAL  38 DAY, 'DEMO: insufficient justification', NULL),
 (2, 1, 1800000.00, 'DEMO: baseline from BoQ',            'approved',   1,    5, @t - INTERVAL 155 DAY, 2, @t - INTERVAL 154 DAY, 'DEMO approved', NULL),
 (2, 2, 1950000.00, 'DEMO: variation for extra storey slab thickness', 'pending', NULL, 5, @t - INTERVAL 4 DAY, NULL, NULL, NULL, NULL),   -- A12
 (2, 3, 1990000.00, 'DEMO: draft, not yet submitted',     'draft',      NULL, 4, @t - INTERVAL   2 DAY, NULL, NULL, NULL, NULL),
 (3, 1,  650000.00, 'DEMO: baseline from BoQ',            'approved',   1,    6, @t - INTERVAL 605 DAY, 2, @t - INTERVAL 604 DAY, 'DEMO approved', NULL);

-- ---------------- Expenditure (AC): P1 2,688,000 = 112% (A5, A6, A6b, A7);
--                  P2 1,530,000 = 85% (A5, A7); P3 630,500 = 97% (completed, not evaluated)
INSERT INTO expenditures (project_id, activity_id, category_id, expense_date, amount, description, reference_no, supplier_name, status, void_reason, recorded_by, voided_by) VALUES
 (1, 2, (SELECT id FROM expenditure_categories WHERE code='materials'),   @t - INTERVAL 200 DAY, 950000.00, 'DEMO: gravel and laterite',  'DEMO-P1-V001', 'Demo Quarry Ltd',   'active', NULL, 5, NULL),
 (1, 2, (SELECT id FROM expenditure_categories WHERE code='plant'),       @t - INTERVAL 170 DAY, 620000.00, 'DEMO: plant hire',            'DEMO-P1-V002', 'Demo Plant Hire',   'active', NULL, 5, NULL),
 (1, NULL,(SELECT id FROM expenditure_categories WHERE code='labour'),    @t - INTERVAL 120 DAY, 410000.00, 'DEMO: casual labour wages',   'DEMO-P1-V003', NULL,                'active', NULL, 5, NULL),
 (1, 3, (SELECT id FROM expenditure_categories WHERE code='materials'),   @t - INTERVAL  60 DAY, 380000.00, 'DEMO: culvert rings',         'DEMO-P1-V004', 'Demo Concrete Works','active', NULL, 5, NULL),
 (1, 3, (SELECT id FROM expenditure_categories WHERE code='subcontract'), @t - INTERVAL  30 DAY, 240000.00, 'DEMO: drain lining subcontract','DEMO-P1-V005', 'Demo Builders',   'active', NULL, 5, NULL),
 (1, NULL,(SELECT id FROM expenditure_categories WHERE code='transport'), @t - INTERVAL  10 DAY,  88000.00, 'DEMO: haulage',               'DEMO-P1-V006', 'Demo Haulage',      'active', NULL, 5, NULL),
 (1, NULL,(SELECT id FROM expenditure_categories WHERE code='other'),     @t - INTERVAL   8 DAY,  15000.00, 'DEMO: duplicate entry',       'DEMO-P1-V007', NULL,                'voided', 'DEMO: duplicate of V006', 5, 5),
 (2, 7, (SELECT id FROM expenditure_categories WHERE code='materials'),   @t - INTERVAL 140 DAY, 520000.00, 'DEMO: cement, sand, rebar',   'DEMO-P2-V001', 'Demo Hardware',     'active', NULL, 5, NULL),
 (2, NULL,(SELECT id FROM expenditure_categories WHERE code='labour'),    @t - INTERVAL 110 DAY, 260000.00, 'DEMO: labour wages',          'DEMO-P2-V002', NULL,                'active', NULL, 5, NULL),
 (2, 8, (SELECT id FROM expenditure_categories WHERE code='materials'),   @t - INTERVAL  70 DAY, 430000.00, 'DEMO: frame materials',       'DEMO-P2-V003', 'Demo Hardware',     'active', NULL, 5, NULL),
 (2, NULL,(SELECT id FROM expenditure_categories WHERE code='plant'),     @t - INTERVAL  40 DAY, 150000.00, 'DEMO: mixer and scaffold hire','DEMO-P2-V004', 'Demo Plant Hire',  'active', NULL, 5, NULL),
 (2, 9, (SELECT id FROM expenditure_categories WHERE code='subcontract'), @t - INTERVAL  15 DAY, 170000.00, 'DEMO: blockwork gang',        'DEMO-P2-V005', 'Demo Masons',       'active', NULL, 5, NULL),
 (3, 12,(SELECT id FROM expenditure_categories WHERE code='materials'),   @t - INTERVAL 560 DAY, 300000.00, 'DEMO: materials',             'DEMO-P3-V001', 'Demo Hardware',     'active', NULL, 6, NULL),
 (3, NULL,(SELECT id FROM expenditure_categories WHERE code='labour'),    @t - INTERVAL 480 DAY, 180000.00, 'DEMO: labour',                'DEMO-P3-V002', NULL,                'active', NULL, 6, NULL),
 (3, 14,(SELECT id FROM expenditure_categories WHERE code='subcontract'), @t - INTERVAL 400 DAY, 120500.00, 'DEMO: roofing subcontract',   'DEMO-P3-V003', 'Demo Roofers',      'active', NULL, 6, NULL),
 (3, NULL,(SELECT id FROM expenditure_categories WHERE code='overheads'), @t - INTERVAL 340 DAY,  30000.00, 'DEMO: site overheads',        'DEMO-P3-V004', NULL,                'active', NULL, 6, NULL);

-- ---------------- Payment certificates: submitted, certified, part-paid (overdue), paid
INSERT INTO payment_certificates (id, project_id, cert_no, valuation_amount, amount_certified, submitted_date, certified_date, status, recorded_by) VALUES
 (1, 1, 'IPC-01', 700000.00, 680000.00, @t - INTERVAL 150 DAY, @t - INTERVAL 140 DAY, 'paid',      5),
 (2, 1, 'IPC-02', 900000.00, 850000.00, @t - INTERVAL  60 DAY, @t - INTERVAL  50 DAY, 'part_paid', 5),  -- A9 overdue
 (3, 1, 'IPC-03', 300000.00, NULL,      @t - INTERVAL   5 DAY, NULL,                  'submitted', 5),
 (4, 2, 'IPC-01', 500000.00, 480000.00, @t - INTERVAL  90 DAY, @t - INTERVAL  80 DAY, 'paid',      5),
 (5, 2, 'IPC-02', 450000.00, 430000.00, @t - INTERVAL  20 DAY, @t - INTERVAL  12 DAY, 'certified', 5),
 (6, 3, 'IPC-01', 250000.00, 245000.00, @t - INTERVAL 500 DAY, @t - INTERVAL 495 DAY, 'paid',      6),
 (7, 3, 'IPC-02', 455000.00, 455000.00, @t - INTERVAL 320 DAY, @t - INTERVAL 315 DAY, 'paid',      6);

INSERT INTO payment_transactions (certificate_id, paid_date, amount, reference_no, recorded_by) VALUES
 (1, @t - INTERVAL 120 DAY, 680000.00, 'DEMO-TRF-1001', 5),
 (2, @t - INTERVAL  35 DAY, 400000.00, 'DEMO-TRF-1002', 5),
 (4, @t - INTERVAL  70 DAY, 300000.00, 'DEMO-TRF-2001', 5),
 (4, @t - INTERVAL  62 DAY, 180000.00, 'DEMO-TRF-2002', 5),
 (6, @t - INTERVAL 470 DAY, 245000.00, 'DEMO-TRF-3001', 6),
 (7, @t - INTERVAL 290 DAY, 455000.00, 'DEMO-TRF-3002', 6);

-- ---------------- Resources, usage, material requests -----------------------
INSERT INTO resources (id, project_id, category_id, name, unit, planned_qty, reorder_level, unit_rate, equipment_status, created_by) VALUES
 ( 1, 1, 1, 'General labourers',        'persons', NULL,   NULL,   60.00, NULL,      7),
 ( 2, 1, 1, 'Masons',                   'persons', NULL,   NULL,  120.00, NULL,      7),
 ( 3, 1, 2, 'Cement (50 kg bag)',       'bag',     2000.00, 200.00, 95.00, NULL,      7),   -- balance 120: A10
 ( 4, 1, 2, 'Chippings 14 mm',          't',        600.00,  50.00,210.00, NULL,      7),
 ( 5, 1, 3, 'Excavator (hired)',        'hr',      NULL,   NULL,  450.00, 'faulty',  7),   -- A14
 ( 6, 1, 3, 'Vibrating roller',         'hr',      NULL,   NULL,  300.00, 'working', 7),
 ( 7, 2, 1, 'Carpenters',               'persons', NULL,   NULL,  110.00, NULL,      8),
 ( 8, 2, 2, 'Cement (50 kg bag)',       'bag',     1200.00, 150.00, 95.00, NULL,      8),
 ( 9, 2, 2, 'Sandcrete blocks 150 mm',  'pcs',    15000.00,1000.00,  6.50, NULL,      8),
 (10, 2, 3, 'Concrete mixer',           'hr',      NULL,   NULL,   80.00, 'working', 8);

INSERT INTO resource_usage (resource_id, activity_id, usage_date, movement, quantity, remarks, recorded_by) VALUES
 (1, 4, @t - INTERVAL 2 DAY, 'attendance',   24.00, 'DEMO', 7),
 (2, 3, @t - INTERVAL 2 DAY, 'attendance',    6.00, 'DEMO', 9),
 (3, NULL, @t - INTERVAL 90 DAY, 'received', 1500.00, 'DEMO delivery note 001', 7),
 (3, 3, @t - INTERVAL 20 DAY, 'used',     1380.00, 'DEMO', 9),
 (4, NULL, @t - INTERVAL 60 DAY, 'received',  400.00, 'DEMO', 7),
 (4, 4, @t - INTERVAL  5 DAY, 'used',      250.00, 'DEMO', 7),
 (5, 2, @t - INTERVAL 120 DAY,'hours',     310.00, 'DEMO', 7),
 (7, 8, @t - INTERVAL 1 DAY, 'attendance',    8.00, 'DEMO', 8),
 (8, NULL, @t - INTERVAL 50 DAY, 'received',  900.00, 'DEMO', 8),
 (8, 8, @t - INTERVAL 10 DAY, 'used',      600.00, 'DEMO', 8),
 (9, NULL, @t - INTERVAL 45 DAY, 'received',12000.00, 'DEMO', 8),
 (9, 9, @t - INTERVAL 12 DAY, 'used',     9500.00, 'DEMO', 8);

INSERT INTO material_requests (project_id, resource_id, quantity, required_by, status, remarks, requested_by, requested_at, decided_by, decided_at) VALUES
 (1, 3,  500.00, @t + INTERVAL 3 DAY,  'requested', 'DEMO: cement for drain lining', 7, @t - INTERVAL 1 DAY, NULL, NULL),
 (1, 4,  100.00, @t - INTERVAL 50 DAY, 'delivered', 'DEMO', 7, @t - INTERVAL 70 DAY, 3, @t - INTERVAL 69 DAY),
 (2, 9, 3000.00, @t + INTERVAL 10 DAY, 'approved',  'DEMO: first floor walls', 8, @t - INTERVAL 3 DAY, 4, @t - INTERVAL 2 DAY),
 (2, 8,  200.00, @t - INTERVAL 5 DAY,  'cancelled', 'DEMO: ordered twice', 8, @t - INTERVAL 20 DAY, 4, @t - INTERVAL 19 DAY);

-- ---------------- Project-level threshold and a QS proposal (FR-21) ----------
INSERT INTO alert_settings (project_id, setting_key, value, set_by) VALUES
 (2, 'budget_warning_pct', 75.00, 4);
INSERT INTO threshold_proposals (project_id, setting_key, proposed_value, justification, status, proposed_by) VALUES
 (1, 'budget_overrun_sms_pct', 5.00, 'DEMO: tighter SMS limit for the road project', 'pending', 5);
