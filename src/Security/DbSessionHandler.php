<?php

declare(strict_types=1);

namespace Lubie\Security;

use Lubie\Db\Database;
use Lubie\Support\Clock;
use SessionHandlerInterface;

/**
 * Database session storage so the web tier is stateless (container restarts and
 * free-tier spin-down keep users logged in). Implements PHP's SessionHandlerInterface;
 * the Session class drives it directly so the kernel stays testable without globals.
 */
final class DbSessionHandler implements SessionHandlerInterface
{
    private ?int $userId = null;
    private string $ip = '';
    private string $agent = '';

    public function __construct(private Database $db, private Clock $clock)
    {
    }

    public function setContext(?int $userId, string $ip, string $agent): void
    {
        $this->userId = $userId;
        $this->ip = $ip;
        $this->agent = $agent;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $v = $this->db->value('SELECT data FROM sessions WHERE id = ?', [$id]);
        return $v === null ? '' : (string) $v;
    }

    /** @return array{data:string, created_at:string, last_activity:string, user_id:?int}|null */
    public function readRow(string $id): ?array
    {
        $row = $this->db->one('SELECT data, created_at, last_activity, user_id FROM sessions WHERE id = ?', [$id]);
        if ($row === null) {
            return null;
        }
        $row['user_id'] = $row['user_id'] === null ? null : (int) $row['user_id'];
        return $row;
    }

    public function write(string $id, string $data): bool
    {
        $now = $this->clock->nowSql();
        $this->db->run(
            'INSERT INTO sessions (id, user_id, data, ip_address, user_agent, created_at, last_activity)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), data = VALUES(data), ip_address = VALUES(ip_address),
                                     user_agent = VALUES(user_agent), last_activity = VALUES(last_activity)',
            [$id, $this->userId, $data, $this->ip, $this->agent, $now, $now]
        );
        return true;
    }

    public function destroy(string $id): bool
    {
        $this->db->run('DELETE FROM sessions WHERE id = ?', [$id]);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        $cut = $this->clock->now()->modify("-{$max_lifetime} seconds")->format('Y-m-d H:i:s');
        return $this->db->run('DELETE FROM sessions WHERE last_activity < ?', [$cut])->rowCount();
    }

    public function destroyAllForUser(int $userId): void
    {
        $this->db->run('DELETE FROM sessions WHERE user_id = ?', [$userId]);
    }
}
