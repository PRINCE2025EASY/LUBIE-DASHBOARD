<?php

declare(strict_types=1);

/*
 * Lubie dashboard command line. Run from the project folder:
 *   php bin/console.php <command> [options]
 *
 * Commands
 *   migrate                 Create the schema in an empty database, or apply new migrations.
 *   setup:first-admin       Create the first Administrator (prompts; refuses if one exists).
 *   seed:demo --yes         Load synthetic demo data (refused when APP_ENV=production).
 *   test-db:reset --yes     Empty and rebuild the test database named in DB_TEST_NAME (must end in _test).
 *   backup:create --out=F   Write an encrypted backup file to F (outside public/).
 *   restore:verify --file=F --target=DB   Import F into the EMPTY database DB and compare counts and KPIs.
 *   alerts:run              Evaluate alert rules for every active project (for a daily scheduled task).
 *   sms:retry               Retry SMS messages that are due for another attempt.
 *   setup:app-account --owner=root [--user=lubie_app] [--host=localhost] [--require-ssl]
 *                           Uses the database OWNER account (password asked): create the least-privilege app account with
 *                           per-table grants (audit_logs append-only). Prompts for the new password.
 *   doctor                  Read-only check of PHP, extensions, .env, database and schema. Shows no secrets.
 *
 * Secrets are never passed on the command line; they come from .env or the host settings.
 */

use Lubie\Db\Database;
use Lubie\Db\Migrator;
use Lubie\Db\SqlScript;
use Lubie\Repositories\UserRepository;
use Lubie\Services\AlertService;
use Lubie\Services\AuthService;
use Lubie\Services\BackupService;
use Lubie\Services\KpiService;
use Lubie\Sms\SmsDispatcher;
use Lubie\Support\Clock;
use Lubie\Support\Env;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/config/bootstrap.php';

$root = dirname(__DIR__);
$args = array_slice($argv, 1);
$cmd = $args[0] ?? 'help';
$opt = static function (string $name) use ($args): ?string {
    foreach ($args as $a) {
        if ($a === "--{$name}") {
            return '1';
        }
        if (str_starts_with($a, "--{$name}=")) {
            return substr($a, strlen($name) + 3);
        }
    }
    return null;
};
$say = static fn (string $m) => fwrite(STDOUT, $m . PHP_EOL);
$fail = static function (string $m): never {
    fwrite(STDERR, 'Error: ' . $m . PHP_EOL);
    exit(1);
};

function lubie_prompt(string $label, bool $hidden = false): string
{
    fwrite(STDOUT, $label . ': ');
    $sttyOk = $hidden && DIRECTORY_SEPARATOR === '/' && @shell_exec('stty -g 2>/dev/null') !== null;
    if ($sttyOk) {
        $state = trim((string) shell_exec('stty -g'));
        shell_exec('stty -echo');
        $v = fgets(STDIN);
        shell_exec('stty ' . escapeshellarg($state));
        fwrite(STDOUT, PHP_EOL);
    } else {
        if ($hidden) {
            fwrite(STDOUT, PHP_EOL . '(This terminal cannot hide typing. Make sure nobody is watching.)' . PHP_EOL . $label . ': ');
        }
        $v = fgets(STDIN);
    }
    return trim((string) $v);
}

try {
    switch ($cmd) {
        case 'migrate':
            foreach ((new Migrator(Database::fromEnv(), $root))->migrate() as $m) {
                $say($m);
            }
            break;

        case 'setup:first-admin':
            $c = lubie_container();
            $users = $c->get(UserRepository::class);
            if ($users->countAdmins() > 0) {
                $fail('An Administrator already exists. Use the Users page to add more.');
            }
            $username = strtolower(lubie_prompt('Username (lower case)'));
            if (!preg_match('/^[a-z0-9._-]{3,50}$/', $username)) {
                $fail('Use 3 to 50 lower-case letters, numbers, dot, dash or underscore.');
            }
            $name = lubie_prompt('Full name');
            if (mb_strlen($name) < 2) {
                $fail('Enter the full name.');
            }
            $pw = lubie_prompt('Temporary password', true);
            if ($pw !== lubie_prompt('Repeat password', true)) {
                $fail('The passwords do not match.');
            }
            $c->get(AuthService::class)->assertStrong($pw, $username);
            $id = $users->create(['role_id' => $users->roleId('admin'), 'username' => $username, 'full_name' => $name, 'phone' => null, 'email' => null],
                password_hash($pw, PASSWORD_DEFAULT, AuthService::HASH_OPTIONS), null);
            $c->get(\Lubie\Audit\AuditLogger::class)->log(null, 'create', 'user', (string) $id, null, ['username' => $username, 'role' => 'admin', 'via' => 'cli']);
            $say("Administrator '{$username}' created. You must choose a new password at first login.");
            break;

        case 'seed:demo':
            if (Env::get('APP_ENV') === 'production') {
                $fail('Demo data is never loaded when APP_ENV=production.');
            }
            if ($opt('yes') === null) {
                $fail('Add --yes to confirm loading synthetic demo data into ' . Env::get('DB_NAME', 'lubie_dashboard') . '.');
            }
            $db = Database::fromEnv();
            if ((int) $db->value('SELECT COUNT(*) FROM users') > 0) {
                $fail('This database already has users. Demo data loads only into a fresh database (run migrate on an empty one first).');
            }
            $n = SqlScript::runFile($db, $root . '/database/seed_demo.sql');
            $say("Demo data loaded ({$n} statements). Demo password is in the comment at the top of database/seed_demo.sql.");
            break;

        case 'test-db:reset':
            $name = (string) Env::get('DB_TEST_NAME', '');
            if (!preg_match('/^[a-z0-9_]+_test$/', $name)) {
                $fail('Set DB_TEST_NAME to a database name that ends in _test.');
            }
            if ($opt('yes') === null) {
                $fail("Add --yes to confirm emptying the test database {$name}.");
            }
            $db = Database::fromEnv($name);
            (new Migrator($db, $root))->resetTestDatabase();
            $say("Test database {$name} rebuilt with demo data.");
            break;

        case 'backup:create':
            $out = $opt('out');
            if ($out === null || $out === '1') {
                $fail('Give --out=path\\to\\file.sql.gz.enc');
            }
            $real = realpath(dirname($out));
            if ($real === false) {
                $fail('The folder for --out does not exist.');
            }
            if (str_starts_with($real, realpath($root . '/public') ?: "\0")) {
                $fail('Never write backups inside public/.');
            }
            $b = lubie_container()->get(BackupService::class)->create(null, 'manual');
            file_put_contents($out, $b['bytes']);
            $say("Backup written: {$out} (" . strlen($b['bytes']) . " bytes, SHA-256 {$b['sha256']}).");
            break;

        case 'restore:verify':
            $file = $opt('file');
            $target = (string) $opt('target');
            if ($file === null || !is_file($file)) {
                $fail('Give --file=path to an existing backup file.');
            }
            if (!preg_match('/^[a-z0-9_]+$/', $target) || $target === Env::get('DB_NAME')) {
                $fail('Give --target=<empty database name>, different from DB_NAME.');
            }
            $tdb = Database::fromEnv($target);
            if ((int) $tdb->value('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()') > 0) {
                $fail("Database {$target} is not empty. Restore verification needs an empty database.");
            }
            $clock = new Clock(Env::get('APP_FIXED_NOW'));
            $res = BackupService::restoreVerify((string) file_get_contents($file), $tdb, new KpiService(), $clock);
            foreach ($res['counts'] as $t => $c) {
                if ($c['expected'] !== $c['actual']) {
                    $say("  MISMATCH {$t}: expected {$c['expected']}, found {$c['actual']}");
                }
            }
            $say(sprintf('Tables checked: %d. Projects KPI-checked: %d. Time: %.2f s. Result: %s',
                count($res['counts']), count($res['kpis']), $res['seconds'], $res['ok'] ? 'PASS' : 'FAIL'));
            exit($res['ok'] ? 0 : 2);

        case 'alerts:run':
            $c = lubie_container();
            $ids = array_column($c->get(Database::class)->all("SELECT id FROM projects WHERE status IN ('planning','active','on_hold')"), 'id');
            foreach ($ids as $pid) {
                $c->get(AlertService::class)->evaluate((int) $pid);
            }
            $say('Alerts evaluated for ' . count($ids) . ' project(s).');
            break;

        case 'sms:retry':
            lubie_container()->get(SmsDispatcher::class)->retryDue();
            $say('SMS retry pass finished.');
            break;

        case 'setup:app-account':
            $owner = $opt('owner');
            if ($owner === null || $owner === '1') {
                $fail('Give the owner account: --owner=root (XAMPP) or --owner=avnadmin (Aiven). You are asked for its password.');
            }
            $db = Database::fromEnvAs($owner, lubie_prompt("Password of {$owner} (press Enter if it has none)", true));
            $user = (string) ($opt('user') ?? 'lubie_app');
            $host = (string) ($opt('host') ?? 'localhost');
            if (!preg_match('/^[a-z0-9_]{3,32}$/', $user) || !preg_match('/^[a-zA-Z0-9.%_-]{1,60}$/', $host)) {
                $fail('Use a simple user name and host (for example --user=lubie_app --host=%).');
            }
            if ($user === $owner) {
                $fail('The owner and the app account must be different.');
            }
            $tables = array_column($db->all("SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' ORDER BY table_name"), 't');
            if (count($tables) < 31) {
                $fail('Create the schema first (php bin/console.php migrate).');
            }
            $pw = lubie_prompt("New password for {$user}@{$host} (12+ characters)", true);
            if (strlen($pw) < 12 || $pw !== lubie_prompt('Repeat password', true)) {
                $fail('Passwords must match and be at least 12 characters.');
            }
            $pdo = $db->pdo();
            $schema = (string) $db->value('SELECT DATABASE()');
            $acct = $pdo->quote($user) . '@' . $pdo->quote($host);
            // No read of mysql.user: managed hosts (Aiven) may not allow it.
            $ssl = $opt('require-ssl') !== null ? ' REQUIRE SSL' : '';
            $pdo->exec("CREATE USER IF NOT EXISTS {$acct} IDENTIFIED BY " . $pdo->quote($pw) . $ssl);
            $pdo->exec("ALTER USER {$acct} IDENTIFIED BY " . $pdo->quote($pw) . $ssl);
            $exists = false;
            foreach ($tables as $t) {
                $privs = match ((string) $t) { 'audit_logs' => 'SELECT, INSERT', 'schema_migrations' => 'SELECT', default => 'SELECT, INSERT, UPDATE, DELETE' };
                $pdo->exec("GRANT {$privs} ON `{$schema}`.`{$t}` TO {$acct}");
            }
            $say("Account {$user}@{$host} ready with grants on " . count($tables) . " tables in {$schema}. Put the same password in DB_PASSWORD (in .env or the host settings).");
            break;

        case 'doctor':
            $ok = static fn (bool $b) => $b ? 'OK  ' : 'FIX ';
            $say('Lubie dashboard check (read-only; no secret values shown)');
            $say($ok(PHP_VERSION_ID >= 80200) . 'PHP ' . PHP_VERSION . ' (' . PHP_OS_FAMILY . ', ' . (PHP_INT_SIZE * 8) . '-bit)');
            foreach (['pdo_mysql' => true, 'mbstring' => true, 'json' => true, 'dom' => true, 'zlib' => true, 'openssl' => false, 'curl' => false, 'fileinfo' => false] as $ext => $required) {
                $say((extension_loaded($ext) ? 'OK  ' : ($required ? 'FIX ' : 'WARN')) . "extension {$ext}" . (extension_loaded($ext) ? '' : ($required ? ' (required)' : ' (needed for ' . ['openssl' => 'backups', 'curl' => 'real SMS', 'fileinfo' => 'photo uploads later'][$ext] . ')')));
            }
            $say($ok(class_exists(\Dompdf\Dompdf::class)) . 'Dompdf (PDF export)' . (class_exists(\Dompdf\Dompdf::class) ? '' : ': vendor folder missing, print view used'));
            if (is_file($root . '/.env')) {
                $say('OK  .env file found');
            } elseif (getenv('APP_KEY') !== false) {
                $say('OK  settings come from the host environment (no .env file)');
            } else {
                $say('FIX .env file missing: copy .env.example to .env');
            }
            foreach (['APP_KEY' => 32, 'BACKUP_ENCRYPTION_KEY' => 32, 'DB_PASSWORD' => 1] as $k => $min) {
                $set = strlen((string) Env::get($k, '')) >= $min;
                $say(($set ? 'OK  ' : 'WARN') . "{$k} " . ($set ? 'is set' : ($min > 1 ? "is missing or shorter than {$min} characters" : 'is empty')));
            }
            $say('INFO APP_BASE_PATH=' . (Env::get('APP_BASE_PATH', '') ?: '(root)') . '  APP_ENV=' . Env::get('APP_ENV', 'local') . '  DB_USER=' . Env::get('DB_USER', '') . '  DB_NAME=' . Env::get('DB_NAME', ''));
            if (Env::get('DB_USER') === 'root') {
                $say('WARN the app connects as root. Use the least-privilege lubie_app account (INSTALL_XAMPP.md step 4).');
            }
            try {
                $db = Database::fromEnv();
                $ver = (string) $db->value('SELECT VERSION()');
            } catch (\Throwable $e) {
                $say('FIX database: ' . $e->getMessage());
                if ($e->getPrevious() !== null) {
                    // Server's own reason, e.g. "Access denied for user 'x'@'localhost' (using password: YES)". Never contains the password.
                    $say('     Server said: ' . preg_replace('/^SQLSTATE\[[^\]]+\]\s*\[\d+\]\s*/', '', $e->getPrevious()->getMessage()));
                }
                $say('     Check that MySQL is running in the XAMPP Control Panel, the database exists, and DB_* in .env match the account you created.');
                exit(1);
            }
            $isMaria = stripos($ver, 'mariadb') !== false;
            $num = (string) preg_replace('/^(\d+\.\d+\.\d+).*/', '$1', $ver);
            $say($ok($isMaria ? version_compare($num, '10.4.0', '>=') : version_compare($num, '8.0.16', '>=')) . "Database server {$ver}");
            $say('INFO sql_mode=' . $db->value('SELECT @@SESSION.sql_mode'));
            $tables = (int) $db->value('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()');
            $say($ok($tables >= 31) . "Tables in " . $db->value('SELECT DATABASE()') . ": {$tables} (31 expected)");
            if ($tables > 0) {
                $versions = array_column($db->all('SELECT version FROM schema_migrations ORDER BY version'), 'version');
                $say($ok(in_array('0.3.0', $versions, true)) . 'Schema versions: ' . implode(', ', $versions) . (in_array('0.3.0', $versions, true) ? '' : ' (run migrate with an owner account)'));
                $gen = (int) $db->value("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND extra IN ('STORED GENERATED', 'VIRTUAL GENERATED', 'PERSISTENT GENERATED')");
                $say($ok($gen >= 2) . "Generated columns: {$gen} (2 expected)");
                $checks = (int) $db->value("SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND constraint_type = 'CHECK'");
                $say($ok($checks >= 27) . "CHECK constraints: {$checks} (27 expected)");
                $say('INFO users: ' . $db->value('SELECT COUNT(*) FROM users') . ', projects: ' . $db->value('SELECT COUNT(*) FROM projects') . ', administrators: ' . $db->value("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'admin' AND u.status = 'active'"));
                try {
                    $db->pdo()->exec('UPDATE audit_logs SET action = action WHERE 1 = 0');
                    $say('WARN app account can UPDATE audit_logs (fine for root, not for lubie_app)');
                } catch (\PDOException) {
                    $say('OK  audit_logs is append-only for this account');
                }
            }
            break;

        default:
            $say("Commands: doctor, migrate, setup:first-admin, seed:demo --yes, test-db:reset --yes, backup:create --out=F,\n"
                . '          restore:verify --file=F --target=DB, alerts:run, sms:retry');
    }
} catch (\Lubie\Http\HttpException $e) {
    $fail($e->getMessage() . ($e->fields ? ' ' . implode(' ', $e->fields) : ''));
} catch (\Throwable $e) {
    $fail($e->getMessage());
}
