#!/bin/sh
# Container start: listen on the host's PORT, check settings, optionally create the schema.
set -e
PORT="${PORT:-10000}"
export PORT
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
cd /var/www/lubie
# Refuse to start without the essential secrets (they are set in the host dashboard, never in the image).
for v in APP_KEY DB_HOST DB_NAME DB_USER DB_PASSWORD; do
  eval "val=\${$v:-}"
  if [ -z "$val" ]; then echo "lubie: missing environment variable $v" >&2; exit 1; fi
done
# One-off schema creation or migration with a separate owner account (optional, then remove these variables).
if [ -n "${MIGRATE_DB_USER:-}" ] && [ -n "${MIGRATE_DB_PASSWORD:-}" ]; then
  echo "lubie: running migrations as the owner account"
  DB_USER="$MIGRATE_DB_USER" DB_PASSWORD="$MIGRATE_DB_PASSWORD" php bin/console.php migrate
fi
php bin/console.php doctor | grep -v '^INFO' || true
exec "$@"
