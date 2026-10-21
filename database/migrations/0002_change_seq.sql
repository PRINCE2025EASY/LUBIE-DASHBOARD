-- 0002: poll token for near-real-time refresh (second-resolution timestamps can miss
-- two changes in the same second). Version 0.3.0.
ALTER TABLE projects ADD COLUMN change_seq INT UNSIGNED NOT NULL DEFAULT 0 AFTER last_change_at;
INSERT INTO schema_migrations (version) VALUES ('0.3.0');
