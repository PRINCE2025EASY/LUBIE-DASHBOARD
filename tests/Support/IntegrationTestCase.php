<?php

declare(strict_types=1);

namespace Lubie\Tests\Support;

use Lubie\Db\Database;
use Lubie\Db\Migrator;
use Lubie\Support\Env;
use PHPUnit\Framework\TestCase;

/**
 * Base for integration tests: rebuilds the *_test database (schema + reference + synthetic
 * demo data) once per test class. Skips cleanly when no test database is configured.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected static ?Database $db = null;
    protected static string $pw = '';

    public static function setUpBeforeClass(): void
    {
        $name = (string) Env::get('DB_TEST_NAME', '');
        if (!preg_match('/_test$/', $name)) {
            self::markTestSkipped('Set DB_TEST_NAME (ending in _test) to run integration tests.');
        }
        self::$db = Database::fromEnv($name);
        try {
            self::$db->pdo();
        } catch (\Throwable $e) {
            self::markTestSkipped('Test database not reachable: ' . $e->getMessage());
        }
        (new Migrator(self::$db, dirname(__DIR__, 2)))->resetTestDatabase();
        // The demo password is documented only in the seed file comment; read it from there.
        preg_match('/^--\s+(Demo-\S+)\s*$/m', (string) file_get_contents(dirname(__DIR__, 2) . '/database/seed_demo.sql'), $m);
        self::$pw = $m[1] ?? '';
    }

    protected function client(?string $ip = null): ApiClient
    {
        return new ApiClient(self::$db, $ip ?? '10.' . random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(1, 254));
    }

    protected function as(string $user, ?string $ip = null): ApiClient
    {
        $c = $this->client($ip);
        $r = $c->login($user . '_demo', self::$pw);
        $this->assertSame(200, $r['status'], "Login {$user}: " . $r['body']);
        return $c;
    }

    /** @return array<string, mixed> */
    protected function data(array $res): mixed
    {
        return $res['json']['data'] ?? $res['json'];
    }

    /** Flatten all keys of a JSON structure, for field-leak checks. @return list<string> */
    protected static function allKeys(mixed $v): array
    {
        $out = [];
        if (is_array($v)) {
            foreach ($v as $k => $x) {
                if (is_string($k)) {
                    $out[] = $k;
                }
                array_push($out, ...self::allKeys($x));
            }
        }
        return array_values(array_unique($out));
    }

    protected function value(string $sql, array $args = []): mixed
    {
        return self::$db->value($sql, $args);
    }
}
