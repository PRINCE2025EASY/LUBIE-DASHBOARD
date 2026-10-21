-- Least-privilege application account (run as DB owner). Replace the placeholders
-- yourself; never commit a real password. The app account cannot alter schema and
-- cannot UPDATE or DELETE audit_logs (FR-25, ST-11).
-- Usage example (XAMPP shell):  mysql -u root -p < privileges_template.sql
CREATE USER IF NOT EXISTS 'lubie_app'@'localhost' IDENTIFIED BY '<<SET_IN_ENV_NOT_HERE>>';
-- No schema-wide grant: a schema-level GRANT cannot be narrowed per table by REVOKE.
-- Next run privileges_generated.sql, which grants per table: SELECT, INSERT, UPDATE, DELETE
-- on every table except audit_logs (SELECT, INSERT) and schema_migrations (SELECT).
FLUSH PRIVILEGES;
