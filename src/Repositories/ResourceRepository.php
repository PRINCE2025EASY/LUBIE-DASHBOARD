<?php

declare(strict_types=1);

namespace Lubie\Repositories;

use Lubie\Db\Database;

/** Labour, materials and equipment per project, their movements, and material requests. */
final class ResourceRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function categories(): array
    {
        return $this->db->all('SELECT id, code, label FROM resource_categories ORDER BY id');
    }

    public function categoryId(string $code): ?int
    {
        $v = $this->db->value('SELECT id FROM resource_categories WHERE code = ?', [$code]);
        return $v === null ? null : (int) $v;
    }

    /**
     * Resources of a project with their totals. Balance = received - used (materials).
     * @return list<array<string, mixed>>
     */
    public function forProject(int $projectId, string $today, bool $includeArchived = false): array
    {
        $since = (new \DateTimeImmutable($today))->modify('-6 days')->format('Y-m-d');
        return $this->db->all(
            "SELECT r.id, r.project_id, rc.code AS category, r.name, r.unit, r.planned_qty, r.reorder_level, r.unit_rate,
                    r.equipment_status, r.status, r.updated_at,
                    COALESCE(SUM(CASE WHEN u.movement = 'received' THEN u.quantity END), 0) AS received,
                    COALESCE(SUM(CASE WHEN u.movement = 'used' THEN u.quantity END), 0) AS used,
                    COALESCE(SUM(CASE WHEN u.movement = 'attendance' AND u.usage_date = ? THEN u.quantity END), 0) AS today_qty,
                    COALESCE(SUM(CASE WHEN u.movement = 'attendance' AND u.usage_date >= ? THEN u.quantity END), 0) AS week_qty,
                    COALESCE(SUM(CASE WHEN u.movement = 'attendance' THEN u.quantity END), 0) AS person_days,
                    COALESCE(SUM(CASE WHEN u.movement = 'hours' THEN u.quantity END), 0) AS hours,
                    MAX(u.usage_date) AS last_entry
               FROM resources r
               JOIN resource_categories rc ON rc.id = r.category_id
               LEFT JOIN resource_usage u ON u.resource_id = r.id
              WHERE r.project_id = ? " . ($includeArchived ? '' : "AND r.status = 'active' ") . "
              GROUP BY r.id, r.project_id, rc.code, r.name, r.unit, r.planned_qty, r.reorder_level, r.unit_rate, r.equipment_status, r.status, r.updated_at, rc.id
              ORDER BY rc.id, r.name",
            [$today, $since, $projectId]
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->one(
            'SELECT r.*, rc.code AS category FROM resources r JOIN resource_categories rc ON rc.id = r.category_id WHERE r.id = ?', [$id]);
    }

    public function balance(int $resourceId): float
    {
        return (float) $this->db->value(
            "SELECT COALESCE(SUM(CASE movement WHEN 'received' THEN quantity WHEN 'used' THEN -quantity ELSE 0 END), 0)
               FROM resource_usage WHERE resource_id = ?", [$resourceId]);
    }

    public function nameExists(int $projectId, int $categoryId, string $name, ?int $exceptId = null): bool
    {
        return (bool) $this->db->value('SELECT 1 FROM resources WHERE project_id = ? AND category_id = ? AND name = ?' . ($exceptId ? ' AND id <> ?' : ''),
            $exceptId ? [$projectId, $categoryId, $name, $exceptId] : [$projectId, $categoryId, $name]);
    }

    /** @param array<string, mixed> $d */
    public function insert(int $projectId, int $categoryId, array $d, int $by): int
    {
        return $this->db->insert(
            'INSERT INTO resources (project_id, category_id, name, unit, planned_qty, reorder_level, unit_rate, equipment_status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$projectId, $categoryId, $d['name'], $d['unit'], $d['planned_qty'], $d['reorder_level'], $d['unit_rate'], $d['equipment_status'], $by]);
    }

    /** @param array<string, mixed> $fields */
    public function update(int $id, array $fields): int
    {
        return $this->db->updateById('resources', $id, $fields, ['name', 'unit', 'planned_qty', 'reorder_level', 'unit_rate', 'equipment_status', 'status']);
    }

    /** @param array<string, mixed> $d */
    public function insertUsage(int $resourceId, array $d, int $by): int
    {
        return $this->db->insert(
            'INSERT INTO resource_usage (resource_id, activity_id, usage_date, movement, quantity, remarks, recorded_by, client_uuid)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$resourceId, $d['activity_id'], $d['usage_date'], $d['movement'], $d['quantity'], $d['remarks'], $by, $d['client_uuid']]);
    }

    /** @return array<string, mixed>|null */
    public function usageByClientUuid(string $uuid): ?array
    {
        return $this->db->one('SELECT * FROM resource_usage WHERE client_uuid = ?', [$uuid]);
    }

    /** @return list<array<string, mixed>> */
    public function recentUsage(int $projectId, int $limit = 30, ?int $resourceId = null): array
    {
        $limit = max(1, min(200, $limit));
        return $this->db->all(
            "SELECT u.id, u.resource_id, r.name AS resource_name, r.unit, rc.code AS category, u.usage_date, u.movement, u.quantity, u.remarks,
                    u.activity_id, a.code AS activity_code, us.full_name AS recorded_by_name, u.recorded_by, u.created_at
               FROM resource_usage u
               JOIN resources r ON r.id = u.resource_id
               JOIN resource_categories rc ON rc.id = r.category_id
               JOIN users us ON us.id = u.recorded_by
               LEFT JOIN activities a ON a.id = u.activity_id
              WHERE r.project_id = ?" . ($resourceId ? ' AND r.id = ?' : '') . "
              ORDER BY u.usage_date DESC, u.id DESC LIMIT {$limit}",
            $resourceId ? [$projectId, $resourceId] : [$projectId]);
    }

    /** @return list<array<string, mixed>> */
    public function requests(int $projectId): array
    {
        return $this->db->all(
            "SELECT m.id, m.project_id, m.resource_id, r.name AS resource_name, r.unit, m.quantity, m.required_by, m.status, m.remarks,
                    m.requested_by, rq.full_name AS requested_by_name, m.requested_at, m.decided_by, dc.full_name AS decided_by_name, m.decided_at
               FROM material_requests m
               JOIN resources r ON r.id = m.resource_id
               JOIN users rq ON rq.id = m.requested_by
               LEFT JOIN users dc ON dc.id = m.decided_by
              WHERE m.project_id = ?
              ORDER BY FIELD(m.status, 'requested', 'approved', 'delivered', 'cancelled'), m.required_by, m.id DESC",
            [$projectId]);
    }

    /** @return array<string, mixed>|null */
    public function request(int $id): ?array
    {
        return $this->db->one('SELECT * FROM material_requests WHERE id = ?', [$id]);
    }

    /** @param array<string, mixed> $d */
    public function insertRequest(int $projectId, array $d, int $by, string $at): int
    {
        return $this->db->insert(
            'INSERT INTO material_requests (project_id, resource_id, quantity, required_by, remarks, requested_by, requested_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$projectId, $d['resource_id'], $d['quantity'], $d['required_by'], $d['remarks'], $by, $at]);
    }

    /** Status change guarded by the expected current status (no lost updates). */
    public function setRequestStatus(int $id, string $from, string $to, ?int $by, ?string $at, bool $recordDecider): int
    {
        if ($recordDecider) {
            return $this->db->run('UPDATE material_requests SET status = ?, decided_by = ?, decided_at = ? WHERE id = ? AND status = ?', [$to, $by, $at, $id, $from])->rowCount();
        }
        return $this->db->run('UPDATE material_requests SET status = ? WHERE id = ? AND status = ?', [$to, $id, $from])->rowCount();
    }

    public function openRequestCount(int $projectId): int
    {
        return (int) $this->db->value("SELECT COUNT(*) FROM material_requests WHERE project_id = ? AND status IN ('requested', 'approved')", [$projectId]);
    }
}
