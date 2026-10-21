<?php

declare(strict_types=1);

namespace Lubie\Db;

use RuntimeException;

/** Schema creation, migrations and test-database rebuild (used by bin/console.php and tests). */
final class Migrator
{
    public function __construct(private Database $db, private string $root)
    {
    }

    /** @return list<string> messages */
    public function migrate(): array
    {
        $tables = (int) $this->db->value('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()');
        if ($tables === 0) {
            $n = SqlScript::runFile($this->db, $this->root . '/database/schema.sql');
            $n += SqlScript::runFile($this->db, $this->root . '/database/seed_production.sql');
            return ["Created schema and reference data ({$n} statements)."];
        }
        $has = (int) $this->db->value("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'schema_migrations'");
        if ($has === 0) {
            throw new RuntimeException('This database has tables but no schema_migrations table. It is not a Lubie database; nothing was changed.');
        }
        $done = array_column($this->db->all('SELECT version FROM schema_migrations'), 'version');
        $msgs = [];
        foreach (glob($this->root . '/database/migrations/*.sql') ?: [] as $file) {
            if (!preg_match("/INSERT INTO schema_migrations \\(version\\) VALUES \\('([0-9.]+)'\\)/", (string) file_get_contents($file), $m)) {
                throw new RuntimeException(basename($file) . ' does not record its version.');
            }
            if (in_array($m[1], $done, true)) {
                continue;
            }
            SqlScript::runFile($this->db, $file);
            $msgs[] = 'Applied ' . basename($file) . " (version {$m[1]}).";
        }
        $msgs[] = $msgs ? count($msgs) . ' migration(s) applied.' : 'Database is up to date.';
        return $msgs;
    }

    /** Drop every table in a *_test database, then rebuild schema, reference data and demo data. */
    public function resetTestDatabase(): void
    {
        if (!preg_match('/_test$/', (string) $this->db->value('SELECT DATABASE()'))) {
            throw new RuntimeException('Refusing: not a _test database.');
        }
        $pdo = $this->db->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (array_column($this->db->all('SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE()'), 't') as $t) {
            $pdo->exec('DROP TABLE `' . str_replace('`', '', (string) $t) . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        foreach (['schema.sql', 'seed_production.sql', 'seed_demo.sql'] as $f) {
            SqlScript::runFile($this->db, $this->root . '/database/' . $f);
        }
    }
}
