
ALTER TABLE projects ADD COLUMN change_seq INT UNSIGNED NOT NULL DEFAULT 0 AFTER last_change_at;
INSERT INTO schema_migrations (version) VALUES ('0.3.0');
