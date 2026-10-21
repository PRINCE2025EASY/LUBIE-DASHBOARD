<?php

declare(strict_types=1);

namespace Lubie\Audit;

use Lubie\Db\Database;
use Lubie\Support\Clock;

/**
 * Append-only audit trail (FR-25). Removes secrets before writing. The application's
 * database account has INSERT and SELECT only on audit_logs, so rows cannot be changed.
 */
final class AuditLogger
{
    private const SECRET_KEYS = ['password', 'password_hash', 'new_password', 'current_password', 'token', 'csrf', 'api_key', 'secret', 'data'];

    private string $ip = '';
    private string $agent = '';

    public function __construct(private Database $db, private Clock $clock)
    {
    }

    public function setRequestContext(string $ip, string $agent): void
    {
        $this->ip = $ip;
        $this->agent = $agent;
    }

    /**
     * @param array<string, mixed>|null $old
     * @param array<string, mixed>|null $new
     */
    public function log(?int $userId, string $action, ?string $entityType = null, ?string $entityId = null,
                        ?array $old = null, ?array $new = null, ?string $usernameAttempted = null): void
    {
        $this->db->run(
            'INSERT INTO audit_logs (user_id, username_attempted, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $usernameAttempted !== null ? mb_substr($usernameAttempted, 0, 50) : null,
                $action,
                $entityType,
                $entityId,
                self::encode($old),
                self::encode($new),
                $this->ip ?: null,
                $this->agent ?: null,
                $this->clock->nowSql(),
            ]
        );
    }

    /** Only changed fields are kept when both versions are given. */
    public function logChange(int $userId, string $action, string $entityType, int $entityId, array $old, array $new): void
    {
        $changedOld = [];
        $changedNew = [];
        foreach ($new as $k => $v) {
            if (!array_key_exists($k, $old) || (string) $old[$k] !== (string) $v) {
                $changedOld[$k] = $old[$k] ?? null;
                $changedNew[$k] = $v;
            }
        }
        $this->log($userId, $action, $entityType, (string) $entityId, $changedOld ?: null, $changedNew ?: null);
    }

    private static function encode(?array $values): ?string
    {
        if ($values === null) {
            return null;
        }
        foreach (array_keys($values) as $k) {
            if (in_array(strtolower((string) $k), self::SECRET_KEYS, true)) {
                $values[$k] = '[removed]';
            }
        }
        return json_encode($values, JSON_UNESCAPED_UNICODE) ?: null;
    }
}
