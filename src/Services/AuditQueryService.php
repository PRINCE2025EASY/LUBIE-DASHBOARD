<?php

declare(strict_types=1);

namespace Lubie\Services;

use Lubie\Db\Database;

/** Administrator audit viewer with filters and pagination (FR-25). Read-only by design. */
final class AuditQueryService
{
    public function __construct(private Database $db)
    {
    }

    /** @param array<string, mixed> $f @return array{data:list<array<string, mixed>>, meta:array<string,int>} */
    public function search(array $f, int $page, int $perPage): array
    {
        $where = [];
        $args = [];
        if (!empty($f['user'])) {
            $where[] = '(u.username = ? OR a.username_attempted = ?)';
            $args[] = (string) $f['user'];
            $args[] = (string) $f['user'];
        }
        if (!empty($f['action'])) {
            $where[] = 'a.action = ?';
            $args[] = (string) $f['action'];
        }
        if (!empty($f['entity'])) {
            $where[] = 'a.entity_type = ?';
            $args[] = (string) $f['entity'];
        }
        if (!empty($f['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $f['from'])) {
            $where[] = 'a.created_at >= ?';
            $args[] = $f['from'] . ' 00:00:00';
        }
        if (!empty($f['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $f['to'])) {
            $where[] = 'a.created_at <= ?';
            $args[] = $f['to'] . ' 23:59:59';
        }
        $w = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $perPage = max(1, min(100, $perPage));
        $page = max(1, $page);
        $total = (int) $this->db->value('SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id' . $w, $args);
        $rows = $this->db->all('SELECT a.id, a.created_at, a.action, a.entity_type, a.entity_id, a.old_values, a.new_values, a.ip_address, a.username_attempted, u.username, u.full_name
                                  FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id' . $w . ' ORDER BY a.id DESC LIMIT ? OFFSET ?',
            array_merge($args, [$perPage, ($page - 1) * $perPage]));
        return ['data' => $rows, 'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total]];
    }
}
