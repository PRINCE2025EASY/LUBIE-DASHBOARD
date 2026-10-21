<?php

declare(strict_types=1);

namespace Lubie\Repositories;

use Lubie\Db\Database;

/** Projects and project assignments. */
final class ProjectRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM projects WHERE id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public function listAll(bool $includeArchived = false): array
    {
        return $this->db->all('SELECT * FROM projects' . ($includeArchived ? '' : " WHERE status <> 'archived'") . ' ORDER BY status = \'completed\', code');
    }

    /** @return list<array<string, mixed>> */
    public function listAssigned(int $userId, bool $includeArchived = false): array
    {
        return $this->db->all('SELECT p.* FROM projects p JOIN project_assignments a ON a.project_id = p.id WHERE a.user_id = ?'
            . ($includeArchived ? '' : " AND p.status <> 'archived'") . ' ORDER BY p.code', [$userId]);
    }

    /** @param array<string, mixed> $p */
    public function create(array $p, int $userId): int
    {
        return $this->db->insert(
            'INSERT INTO projects (code, name, project_type, location, client_name, contract_sum, planned_start, planned_finish, payment_period_days, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$p['code'], $p['name'], $p['project_type'], $p['location'], $p['client_name'], $p['contract_sum'], $p['planned_start'], $p['planned_finish'],
             $p['payment_period_days'], $p['status'] ?? 'planning', $userId]
        );
    }

    /** @param array<string, mixed> $fields */
    public function update(int $id, array $fields): void
    {
        $this->db->updateById('projects', $id, $fields, ['code', 'name', 'project_type', 'location', 'client_name', 'contract_sum', 'planned_start',
            'planned_finish', 'actual_start', 'actual_finish', 'payment_period_days', 'status']);
    }

    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        return (bool) $this->db->value('SELECT 1 FROM projects WHERE code = ? AND id <> ?', [$code, $exceptId ?? 0]);
    }

    /** @return list<array<string, mixed>> */
    public function assignments(int $projectId): array
    {
        return $this->db->all('SELECT a.user_id, a.assigned_at, u.full_name, u.username, r.code AS role_code, r.name AS role_name
                                 FROM project_assignments a JOIN users u ON u.id = a.user_id JOIN roles r ON r.id = u.role_id
                                WHERE a.project_id = ? ORDER BY r.id, u.full_name', [$projectId]);
    }

    public function assign(int $projectId, int $userId, int $by): void
    {
        $this->db->run('INSERT INTO project_assignments (project_id, user_id, assigned_by) VALUES (?, ?, ?)', [$projectId, $userId, $by]);
    }

    public function unassign(int $projectId, int $userId): int
    {
        return $this->db->run('DELETE FROM project_assignments WHERE project_id = ? AND user_id = ?', [$projectId, $userId])->rowCount();
    }

    public function isAssigned(int $projectId, int $userId): bool
    {
        return (bool) $this->db->value('SELECT 1 FROM project_assignments WHERE project_id = ? AND user_id = ?', [$projectId, $userId]);
    }

    /** @return array{seq:int, at:string}|null */
    public function changeStamp(int $projectId): ?array
    {
        $r = $this->db->one('SELECT change_seq, last_change_at FROM projects WHERE id = ?', [$projectId]);
        return $r === null ? null : ['seq' => (int) $r['change_seq'], 'at' => (string) $r['last_change_at']];
    }

    /** Active users who are not yet on the project. Admin and Director are never assigned (they see all). @return list<array<string, mixed>> */
    public function assignableUsers(int $projectId): array
    {
        return $this->db->all("SELECT u.id, u.full_name, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id
                                WHERE u.status = 'active' AND r.code IN ('pm','qs','site','client')
                                  AND u.id NOT IN (SELECT user_id FROM project_assignments WHERE project_id = ?) ORDER BY r.id, u.full_name", [$projectId]);
    }
}
