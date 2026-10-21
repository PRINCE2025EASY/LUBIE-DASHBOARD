# Install on XAMPP (Windows)

This guide runs the dashboard at http://localhost/lubie-dashboard on your own computer.
You type every password yourself. Never paste a password or key into chat or email.

Tested in the cloud workspace on PHP 8.3 with Apache 2.4 and MariaDB 10.11, with the same
.htaccess files and the /lubie-dashboard base path. Your XAMPP has PHP 8.2.12. Your
XAMPP readme lists MariaDB 10.4.32; step 2 confirms it.

## 1. Files

The project is already in `C:\xampp\htdocs\lubie-dashboard`, including the `vendor` folder
(Dompdf for PDF export). Composer is not needed to run the app.

## 2. Check versions

Open the XAMPP Control Panel, start Apache and MySQL, click Shell, and run:

    php -v
    mysql -u root -p -e "SELECT VERSION();"

PHP must be 8.2 or newer. MariaDB must be 10.4 or newer.

## 3. php.ini changes (your decision)

The app runs without these, but two features need one change. Edit
`C:\xampp\php\php.ini` only after you make a backup copy (for example `php.ini.bak-2026-10-21`).

| Line now | Change to | Why |
|---|---|---|
| `;extension=openssl` | `extension=openssl` | Encrypted backups (Administrator > Backups) |
| `expose_php=On` | `expose_php=Off` | Hides the PHP version from visitors |
| `display_errors=On` | `display_errors=Off` | Errors go to the log, not the screen |

Optional: `;extension=zip`, `;extension=gd`, `;extension=intl`, `;extension=sodium` stay off;
nothing in Iteration 1 needs them. `date.timezone` can stay as it is: the app sets
Africa/Accra itself. Restart Apache after any change. Record the change in
`docs/ENVIRONMENT_CHANGES.md`.

## 4. Database

1. Open http://localhost/phpmyadmin.
2. New database: `lubie_dashboard`, collation `utf8mb4_unicode_ci`.
3. Select it, Import, and import these files in order:
   `database/schema.sql`, then `database/seed_production.sql`.
4. Demo only (synthetic data, never real company data): also import `database/seed_demo.sql`.
   The shared demo password is written in the comment at the top of that file.
5. Application account (least privilege). In the SQL tab, run the contents of
   `database/privileges_template.sql`, but first replace `<<SET_IN_ENV_NOT_HERE>>` in the
   editor with a new strong password of your own. Do not save that edit to the file.
6. Then run `database/privileges_generated.sql` in the same SQL tab.

The app account can read and write data but cannot change the schema or alter the audit trail.

## 5. Configuration (.env)

1. Copy `.env.example` to `.env` in the project folder.
2. Fill in, yourself:
   - `APP_KEY`: run `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"` and paste the result.
   - `DB_PASSWORD`: the password you chose in step 4.5.
   - `BACKUP_ENCRYPTION_KEY`: a second random value made the same way. Keep a copy offline.
     Without it, backups cannot be opened.
   - Leave `SMS_PROVIDER=sandbox`. Nothing is sent to phones in sandbox mode.
3. Keep `APP_BASE_PATH=/lubie-dashboard` and `SESSION_SECURE_COOKIE=0` for localhost.

## 6. First Administrator (skip if you loaded the demo data)

In the XAMPP Shell:

    cd C:\xampp\htdocs\lubie-dashboard
    php bin/console.php setup:first-admin

It asks for a username, full name and a temporary password. The Windows shell cannot hide
typing, so make sure nobody is watching. You must change the password at first login. The
command refuses to run once an Administrator exists.

## 7. Open the app

Go to http://localhost/lubie-dashboard. One login page serves every role; the server sends
each user to their own dashboard.

Check Administrator > Summary > Set-up checks. Any "Action needed" row tells you what is missing.

You can also run a read-only check in the XAMPP Shell. It never shows passwords or keys:

    php bin/console.php doctor

If it says the database refused the connection settings, check DB_NAME, DB_USER and DB_PASSWORD
in .env. If the password is right, try `DB_HOST=localhost` instead of `127.0.0.1`.

## 8. Useful commands

    php bin/console.php migrate              apply new database migrations (run with an owner account)
    php bin/console.php alerts:run           evaluate alerts for all active projects
    php bin/console.php backup:create --out=D:\Backups\lubie.sql.gz.enc
    php bin/console.php doctor               read-only set-up check
    php bin/lint.php                         syntax check of all PHP files

`migrate` needs a database account that can create tables. The app account cannot, by design.

## 9. Remove the demo data later

Import `database/remove_demo.sql` in phpMyAdmin. It deletes only rows marked as demo.

## 10. Undo everything

Delete `C:\xampp\htdocs\lubie-dashboard`, drop the `lubie_dashboard` database and the
`lubie_app` account in phpMyAdmin, and restore your `php.ini` backup if you made changes.
