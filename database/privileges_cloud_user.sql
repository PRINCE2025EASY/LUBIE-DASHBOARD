-- Managed MySQL (Aiven): create the least-privilege application account. TLS is required.
-- Replace the placeholder in your SQL client before running; never save a real password here.
CREATE USER IF NOT EXISTS 'lubie_app'@'%' IDENTIFIED BY '<<SET_IN_HOST_SETTINGS_NOT_HERE>>' REQUIRE SSL;
-- Then run privileges_cloud.sql.
