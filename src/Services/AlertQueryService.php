<?php

declare(strict_types=1);

namespace Lubie\Services;

use Lubie\Audit\AuditLogger;
use Lubie\Db\Database;
use Lubie\Http\HttpException;
use Lubie\Security\AuthUser;
use Lubie\Support\Clock;

/** The current user's alert panel: list, read, acknowledge (FR-20). Clients see only client-safe notices. */
final class AlertQueryService
{
    public function __construct(private Database $db, private AuditLogger $audit, private Clock $clock)
    {
    }

    /** @return list<array<string, mixed>> */
    public function list(AuthUser $u, string $status = 'open', ?int $projectId = null): array
    {
        $sql = 'SELECT r.id AS recipient_id, r.read_at, r.acknowledged_at, r.sms_status, n.*, p.code AS project_code
                  FROM notification_recipients r JOIN notifications n ON n.id = r.notification_id JOIN projects p ON p.id = n.project_id
                 WHERE r.user_id = ?';
        $args = [$u->id];
        if ($u->is('client')) {
            $sql .= ' AND n.is_internal = 0';
        }
        if ($status === 'open') {
            $sql .= ' AND n.cleared_at IS NULL';
        }
        if ($projectId !== null) {
            $sql .= ' AND n.project_id = ?';
            $args[] = $projectId;
        }
        $sql .= " ORDER BY n.cleared_at IS NOT NULL, FIELD(n.severity, 'critical','high','warning','medium','information'), n.created_at DESC LIMIT 200";
        return array_map(static fn (array $n) => Presenter::alert($n), $this->db->all($sql, $args));
    }

    public function mark(AuthUser $u, int $recipientId, string $what): void
    {
        $col = $what === 'acknowledge' ? 'acknowledged_at' : 'read_at';
        $row = $this->db->one('SELECT id, read_at FROM notification_recipients WHERE id = ? AND user_id = ?', [$recipientId, $u->id]);
        if ($row === null) {
            throw HttpException::notFound('Alert not found.');
        }
        $now = $this->clock->nowSql();
        $this->db->run("UPDATE notification_recipients SET {$col} = COALESCE({$col}, ?), read_at = COALESCE(read_at, ?), delivered_at = COALESCE(delivered_at, ?) WHERE id = ?",
            [$now, $now, $now, $recipientId]);
        if ($what === 'acknowledge') {
            $this->audit->log($u->id, 'acknowledge', 'alert_recipient', (string) $recipientId);
        }
    }
}
