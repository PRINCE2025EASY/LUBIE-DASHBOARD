# Operations guide: backup, recovery, deployment, rollback, security

Installation: `docs/INSTALL_XAMPP.md`. First online deployment: `docs/DEPLOY.md`.

## 1. Backup

| Layer | What | How often | Where |
|---|---|---|---|
| Provider | Aiven backups of the MySQL service | Included in the free plan (schedule set by Aiven) | Aiven |
| Application | Encrypted dump (AES-256-GCM, file starts with LUBIEBK1, SHA-256 shown) | Daily once the repository is private; manual before every migration | GitHub Actions artifact (14 days), plus a copy you download |
| Manual | Administrator > Backups > Create and download | Before big changes | Your own storage, off the server |
| Local (XAMPP) | `php bin/console.php backup:create --out=D:\Backups\lubie.sql.gz.enc` | When needed | Outside `htdocs` |

Targets (NFR-06): at most 24 hours of lost data (RPO), service back within 4 hours (RTO).
Sessions are never in backups. The key `BACKUP_ENCRYPTION_KEY` lives only in the host settings
and in your offline record. A backup without its key cannot be read by anyone.

## 2. Restore

Test restore (monthly, and after each release):

    php bin/console.php restore:verify --file=<backup file> --target=<empty database>

It decrypts, imports into the empty database and compares the row count of every table and the
KPI values of every project. PASS or FAIL is printed; a damaged or tampered file is refused.

Real restore after data loss:

1. Put the site in a safe state: Render > Suspend, or stop Apache on XAMPP.
2. Create an empty database (Aiven: new database in the same service; XAMPP: phpMyAdmin).
3. Run `restore:verify` into it with the owner account settings. It must say PASS.
4. Give the app account its grants on the restored database:
   `php bin/console.php setup:app-account --owner=<owner> --host=% --require-ssl` with `DB_NAME` set to it.
5. Point `DB_NAME` at the restored database (Render > Environment), resume the service.
6. Log in as Administrator, check Summary and the latest audit entries, then tell users which
   period of entries to re-enter (anything after the backup time).
7. Record the event and the times in `docs/ENVIRONMENT_CHANGES.md` or the incident log.

## 3. Deployment

- Every push to `main` runs CI (lint, 103 tests on PHP 8.2 + MariaDB 10.4.32, container smoke
  test, dependency audit). Render deploys `main` automatically after the push.
- Release steps:
  1. Merge to `main` only when CI is green.
  2. If the release has a migration: take a manual backup first, then run
     `php bin/console.php migrate` with the owner account (XAMPP Shell with Aiven settings, as
     in DEPLOY.md step 2), or set `MIGRATE_DB_USER` and `MIGRATE_DB_PASSWORD` in Render for one
     deploy and remove them afterwards.
  3. Tag the release: `git tag -a v0.x.y -m "..."` and `git push origin v0.x.y`.
  4. Check `/health`, log in as each role, read Administrator > Set-up checks.

## 4. Rollback

- Code: Render > Deploys > choose the last good deploy > Rollback. Or `git revert` the bad
  commit and push; CI and Render redeploy.
- Database: migrations only add. Old code keeps working with an added column. If a migration
  must be undone, restore the backup taken before it (section 2).
- XAMPP: `git checkout v0.x.y` in `C:\xampp\htdocs\lubie-dashboard`.

## 5. Security checklist

Before go-live and every three months:

- [ ] Repository private; no `.env`, keys, dumps or backups in history (CI and `git log` check).
- [ ] `.env.example` has no values for passwords or keys.
- [ ] Demo data removed (`database/remove_demo.sql`) and demo passwords gone.
- [ ] App connects as `lubie_app` (least privilege, `REQUIRE SSL` online); doctor shows
      "audit_logs is append-only".
- [ ] `SESSION_SECURE_COOKIE=1`, HTTPS only, HSTS header present.
- [ ] `TRUSTED_PROXY_HOPS` correct: login rows in the audit trail show real client IPs.
- [ ] `APP_DEBUG=0`, `display_errors=Off`, `expose_php=Off`.
- [ ] Administrator accounts: at least one, at most two; strong unique passwords.
- [ ] Users who left: deactivated. Locked accounts reviewed.
- [ ] Dependabot and `composer audit`: no open advisories.
- [ ] Restore drill passed in the last month.
- [ ] Audit trail reviewed for refused access (`forbidden`) and failed logins.
- [ ] SMS caps set; provider key rotated if staff with access left.
- [ ] Before real data: Act 843 review, Data Protection Commission registration, provider data
      processing terms signed.

## 6. Monitoring

- `/health` returns database status (no details). Render shows it on the service page.
- Render logs: PHP errors go to the log with a request ID; users see only the ID.
- Administrator > Summary shows the last backup, locked accounts and set-up checks.
