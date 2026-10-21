<?php

declare(strict_types=1);

namespace Lubie\Security;

use Lubie\Audit\AuditLogger;
use Lubie\Db\Database;
use Lubie\Http\HttpException;

/**
 * Complete mediation: role permission (matrix in config/permissions.php) plus project
 * scope on every ID. Deny by default; every refusal is audit-logged.
 */
final class Authorizer
{
    /** @var array<string, list<string>> */
    private array $matrix;
    /** @var array<string, bool> */
    private array $assignCache = [];

    public function __construct(private Database $db, private AuditLogger $audit)
    {
        $this->matrix = require dirname(__DIR__, 2) . '/config/permissions.php';
    }

    /** @return array<string, list<string>> */
    public function matrix(): array
    {
        return $this->matrix;
    }

    public function can(AuthUser $u, string $permission): bool
    {
        return in_array($u->role, $this->matrix[$permission] ?? [], true);
    }

    public function require(AuthUser $u, string $permission): void
    {
        if (!$this->can($u, $permission)) {
            $this->audit->log($u->id, 'forbidden', 'permission', $permission);
            throw HttpException::forbidden();
        }
    }

    public function isAssigned(int $userId, int $projectId): bool
    {
        $k = $userId . ':' . $projectId;
        return $this->assignCache[$k] ??= (bool) $this->db->value(
            'SELECT 1 FROM project_assignments WHERE user_id = ? AND project_id = ?', [$userId, $projectId]
        );
    }

    /**
     * Project scope. Director reads every project; Administrator only for set-up purposes
     * (project details and assignments, $adminSetup = true); everyone else must be assigned.
     * Unknown or out-of-scope IDs return 404 so existence does not leak.
     */
    public function requireProject(AuthUser $u, int $projectId, bool $adminSetup = false): void
    {
        $exists = (bool) $this->db->value('SELECT 1 FROM projects WHERE id = ?', [$projectId]);
        $ok = $exists && (
            $u->role === 'director'
            || ($u->role === 'admin' && $adminSetup)
            || ($u->role !== 'admin' && $this->isAssigned($u->id, $projectId))
        );
        if (!$ok) {
            $this->audit->log($u->id, 'forbidden', 'project', (string) $projectId);
            throw HttpException::notFound('Project not found.');
        }
    }

    /** Director may only read; write operations must use a role permission that excludes Director. */
    public function projectIdFor(string $table, int $id): int
    {
        $sql = match ($table) {
            'activities', 'milestones', 'budgets', 'expenditures', 'payment_certificates', 'resources', 'material_requests'
                => "SELECT project_id FROM {$table} WHERE id = ?",
            'progress_records' => 'SELECT a.project_id FROM progress_records p JOIN activities a ON a.id = p.activity_id WHERE p.id = ?',
            default => throw new \LogicException('Unknown table ' . $table),
        };
        $pid = $this->db->value($sql, [$id]);
        if ($pid === null) {
            throw HttpException::notFound();
        }
        return (int) $pid;
    }
}
