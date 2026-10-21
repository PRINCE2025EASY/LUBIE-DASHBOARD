-- remove_demo.sql: deletes every row created by seed_demo.sql (is_demo projects, *_demo users).
-- Run in DEV or TEST only. Audit rows written while using demo accounts are kept for
-- integrity only if you skip the last two statements; by default they are removed too.
CREATE TEMPORARY TABLE demo_p AS SELECT id FROM projects WHERE is_demo = 1;
CREATE TEMPORARY TABLE demo_u AS SELECT id FROM users WHERE username LIKE '%\_demo';

DELETE s FROM sms_log s JOIN notification_recipients r ON r.id = s.recipient_id JOIN notifications n ON n.id = r.notification_id WHERE n.project_id IN (SELECT id FROM demo_p);
DELETE r FROM notification_recipients r JOIN notifications n ON n.id = r.notification_id WHERE n.project_id IN (SELECT id FROM demo_p);
DELETE FROM notifications        WHERE project_id IN (SELECT id FROM demo_p);
DELETE FROM threshold_proposals  WHERE project_id IN (SELECT id FROM demo_p);
DELETE FROM alert_settings       WHERE project_id IN (SELECT id FROM demo_p);
DELETE FROM material_requests    WHERE project_id IN (SELECT id FROM demo_p);
DELETE u FROM resource_usage u JOIN resources r ON r.id = u.resource_id WHERE r.project_id IN (SELECT id FROM demo_p);
DELETE FROM resources            WHERE project_id IN (SELECT id FROM demo_p);
DELETE t FROM payment_transactions t JOIN payment_certificates c ON c.id = t.certificate_id WHERE c.project_id IN (SELECT id FROM demo_p);
DELETE FROM payment_certificates WHERE project_id IN (SELECT id FROM demo_p);
DELETE FROM expenditures         WHERE project_id IN (SELECT id FROM demo_p);
UPDATE budgets SET supersedes_id = NULL WHERE project_id IN (SELECT id FROM demo_p);
DELETE FROM budgets              WHERE project_id IN (SELECT id FROM demo_p);
DELETE ph FROM progress_photos ph JOIN progress_records pr ON pr.id = ph.progress_record_id JOIN activities a ON a.id = pr.activity_id WHERE a.project_id IN (SELECT id FROM demo_p);
UPDATE progress_records pr JOIN activities a ON a.id = pr.activity_id SET pr.resubmission_of = NULL WHERE a.project_id IN (SELECT id FROM demo_p);
DELETE pr FROM progress_records pr JOIN activities a ON a.id = pr.activity_id WHERE a.project_id IN (SELECT id FROM demo_p);
DELETE FROM milestones           WHERE project_id IN (SELECT id FROM demo_p);
DELETE d FROM activity_dependencies d JOIN activities a ON a.id = d.activity_id WHERE a.project_id IN (SELECT id FROM demo_p);
DELETE FROM activities           WHERE project_id IN (SELECT id FROM demo_p);
DELETE FROM project_assignments  WHERE project_id IN (SELECT id FROM demo_p) OR user_id IN (SELECT id FROM demo_u);
DELETE FROM projects             WHERE id IN (SELECT id FROM demo_p);
DELETE FROM sync_requests        WHERE user_id IN (SELECT id FROM demo_u);
DELETE FROM sessions             WHERE user_id IN (SELECT id FROM demo_u);
DELETE FROM audit_logs           WHERE user_id IN (SELECT id FROM demo_u);   -- run as DB owner (app user cannot delete audit rows)
DELETE FROM users                WHERE id IN (SELECT id FROM demo_u);
DROP TEMPORARY TABLE demo_p; DROP TEMPORARY TABLE demo_u;
