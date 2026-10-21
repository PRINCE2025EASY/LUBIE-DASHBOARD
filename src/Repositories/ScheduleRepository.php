<?php

declare(strict_types=1);

namespace Lubie\Repositories;

use Lubie\Db\Database;

/** Activities, dependencies and milestones. */
final class ScheduleRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function activity(int $id): ?array
    {
        return $this->db->one('SELECT * FROM activities WHERE id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public function activities(int $projectId, bool $includeArchived = false): array
    {
        return $this->db->all('SELECT * FROM activities WHERE project_id = ?' . ($includeArchived ? '' : " AND status = 'active'") . ' ORDER BY planned_start, code', [$projectId]);
    }

    /** @param array<string, mixed> $a */
    public function createActivity(int $projectId, array $a, int $userId): int
    {
        return $this->db->insert('INSERT INTO activities (project_id, code, name, planned_start, planned_finish, budgeted_cost, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$projectId, $a['code'], $a['name'], $a['planned_start'], $a['planned_finish'], $a['budgeted_cost'] ?? null, $userId, $userId]);
    }

    /** @param array<string, mixed> $fields */
    public function updateActivity(int $id, array $fields, int $userId): void
    {
        $this->db->updateById('activities', $id, $fields, ['code', 'name', 'planned_start', 'planned_finish', 'actual_start', 'actual_finish', 'budgeted_cost', 'status'],
            ['updated_by' => $userId]);
    }

    public function activityCodeExists(int $projectId, string $code, int $exceptId = 0): bool
    {
        return (bool) $this->db->value('SELECT 1 FROM activities WHERE project_id = ? AND code = ? AND id <> ?', [$projectId, $code, $exceptId]);
    }

    /** @return array<int, list<int>> activity_id => predecessor ids for a project */
    public function dependencies(int $projectId): array
    {
        $out = [];
        foreach ($this->db->all('SELECT d.activity_id, d.predecessor_id FROM activity_dependencies d JOIN activities a ON a.id = d.activity_id WHERE a.project_id = ?', [$projectId]) as $r) {
            $out[(int) $r['activity_id']][] = (int) $r['predecessor_id'];
        }
        return $out;
    }

    /** @param list<int> $preds */
    public function setDependencies(int $activityId, array $preds): void
    {
        $this->db->run('DELETE FROM activity_dependencies WHERE activity_id = ?', [$activityId]);
        foreach ($preds as $p) {
            $this->db->run('INSERT INTO activity_dependencies (activity_id, predecessor_id) VALUES (?, ?)', [$activityId, $p]);
        }
    }

    /** @return array<string, mixed>|null */
    public function milestone(int $id): ?array
    {
        return $this->db->one('SELECT * FROM milestones WHERE id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public function milestones(int $projectId): array
    {
        return $this->db->all('SELECT m.*, a.code AS activity_code FROM milestones m LEFT JOIN activities a ON a.id = m.activity_id WHERE m.project_id = ? ORDER BY m.due_date', [$projectId]);
    }

    /** @param array<string, mixed> $m */
    public function createMilestone(int $projectId, array $m, int $userId): int
    {
        return $this->db->insert('INSERT INTO milestones (project_id, activity_id, name, due_date, achieved_date, created_by) VALUES (?, ?, ?, ?, ?, ?)',
            [$projectId, $m['activity_id'] ?? null, $m['name'], $m['due_date'], $m['achieved_date'] ?? null, $userId]);
    }

    /** @param array<string, mixed> $fields */
    public function updateMilestone(int $id, array $fields): void
    {
        $this->db->updateById('milestones', $id, $fields, ['name', 'due_date', 'achieved_date', 'activity_id']);
    }
}
