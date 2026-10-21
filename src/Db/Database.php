<?php

declare(strict_types=1);

namespace Lubie\Db;

use Lubie\Support\Env;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Thin PDO wrapper: prepared statements only, retry while a sleeping cloud DB wakes,
 * optional TLS, nested-safe transactions. All SQL lives in repositories.
 */
final class Database
{
    private ?PDO $pdo = null;
    private int $txDepth = 0;

    /** @param array<string, string|int|bool|null> $cfg */
    public function __construct(private array $cfg)
    {
    }

    public static function fromEnv(?string $nameOverride = null): self
    {
        return new self([
            'host' => Env::get('DB_HOST', '127.0.0.1'),
            'port' => Env::int('DB_PORT', 3306),
            'name' => $nameOverride ?? Env::get('DB_NAME', 'lubie_dashboard'),
            'user' => Env::get('DB_USER', 'lubie_app'),
            'pass' => Env::get('DB_PASSWORD', ''),
            'ssl_ca' => Env::get('DB_SSL_CA'),
            'ssl_verify' => Env::bool('DB_SSL_VERIFY', true),
            'retries' => Env::int('DB_CONNECT_RETRIES', 3),
        ]);
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $this->cfg['host'], (int) $this->cfg['port'], $this->cfg['name']);
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_TIMEOUT => 10,
        ];
        if (!empty($this->cfg['ssl_ca'])) {
            $opts[PDO::MYSQL_ATTR_SSL_CA] = (string) $this->cfg['ssl_ca'];
            $opts[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = (bool) $this->cfg['ssl_verify'];
        }
        $attempts = max(1, (int) $this->cfg['retries']);
        for ($i = 1; ; $i++) {
            try {
                $this->pdo = new PDO($dsn, (string) $this->cfg['user'], (string) $this->cfg['pass'], $opts);
                $this->pdo->exec("SET time_zone = '+00:00'");
                return $this->pdo;
            } catch (PDOException $e) {
                if ($i >= $attempts) {
                    throw new DatabaseUnavailable('The database is not responding. Please try again in a minute.', 0, $e);
                }
                usleep(500000 * $i);
            }
        }
    }

    /** @param array<int|string, mixed> $params */
    public function run(string $sql, array $params = []): PDOStatement
    {
        $st = $this->pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $type = match (true) {
                is_int($v) => PDO::PARAM_INT,
                is_bool($v) => PDO::PARAM_BOOL,
                $v === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $st->bindValue(is_int($k) ? $k + 1 : (str_starts_with($k, ':') ? $k : ':' . $k), $v, $type);
        }
        $st->execute();
        return $st;
    }

    /** @return array<string, mixed>|null */
    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function value(string $sql, array $params = []): mixed
    {
        $v = $this->run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public function insert(string $sql, array $params = []): int
    {
        $this->run($sql, $params);
        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * UPDATE one row with whitelisted columns. $table must be a literal from repository code.
     * @param array<string, mixed> $fields
     * @param list<string> $allowed
     */
    public function updateById(string $table, int $id, array $fields, array $allowed, array $extraSets = []): int
    {
        $sets = [];
        $vals = [];
        foreach ($extraSets as $col => $v) {
            $sets[] = "{$col} = ?";
            $vals[] = $v;
        }
        foreach ($fields as $k => $v) {
            if (!in_array($k, $allowed, true)) {
                throw new \LogicException("Column not allowed: {$table}.{$k}");
            }
            $sets[] = "{$k} = ?";
            $vals[] = $v;
        }
        if (!$sets) {
            return 0;
        }
        $vals[] = $id;
        return $this->run("UPDATE {$table} SET " . implode(', ', $sets) . ' WHERE id = ?', $vals)->rowCount();
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public function transaction(callable $fn): mixed
    {
        $pdo = $this->pdo();
        if ($this->txDepth === 0) {
            $pdo->beginTransaction();
        }
        $this->txDepth++;
        try {
            $result = $fn();
            $this->txDepth--;
            if ($this->txDepth === 0) {
                $pdo->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            $this->txDepth--;
            if ($this->txDepth === 0 && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
