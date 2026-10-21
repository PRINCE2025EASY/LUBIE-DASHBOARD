<?php

declare(strict_types=1);

namespace Lubie\Services;

use Lubie\Audit\AuditLogger;
use Lubie\Db\Database;
use Lubie\Http\HttpException;
use Lubie\Repositories\ProjectRepository;
use Lubie\Repositories\UserRepository;
use Lubie\Security\AuthUser;
use Lubie\Security\Authorizer;
use Lubie\Support\Clock;
use Lubie\Support\Money;
use Lubie\Validation\Validator;

/** Projects and assignments (FR-04, FR-06, FR-07). */
final class ProjectService
{
    private const TRANSITIONS = [
        'planning' => ['active', 'archived'],
        'active' => ['on_hold', 'completed'],
        'on_hold' => ['active', 'archived'],
        'completed' => ['archived', 'active'],
        'archived' => [],
    ];

    public function __construct(
        private Database $db,
        private ProjectRepository $projects,
        private UserRepository $users,
        private Authorizer $authz,
        private AuditLogger $audit,
        private ChangeTracker $changes,
        private Clock $clock,
    ) {
    }

    /** @return list<array<string, mixed>> projects the user may see (scope applied here, never in the browser) */
    public function listFor(AuthUser $u, bool $includeArchived = false): array
    {
        $rows = $u->is('director', 'admin') ? $this->projects->listAll($includeArchived) : $this->projects->listAssigned($u->id, $includeArchived);
        return array_map(fn (array $p) => Presenter::project($p, $u->role), $rows);
    }

    /** @param array<string, mixed> $in */
    public function create(AuthUser $u, array $in): int
    {
        $d = $this->validate($in);
        if ($this->projects->codeExists($d['code'])) {
            throw HttpException::validation(['code' => 'This project code is already used.']);
        }
        return $this->db->transaction(function () use ($u, $d) {
            $id = $this->projects->create($d, $u->id);
            if ($u->is('pm')) {
                $this->projects->assign($id, $u->id, $u->id);
            }
            $this->audit->log($u->id, 'create', 'project', (string) $id, null, $d);
            $this->changes->touch($id);
            return $id;
        });
    }

    /** @param array<string, mixed> $in */
    public function update(AuthUser $u, int $id, array $in): void
    {
        $this->authz->requireProject($u, $id, true);
        $old = $this->projects->find($id) ?? throw HttpException::notFound();
        if ($old['status'] === 'archived') {
            throw HttpException::conflict('Archived projects are read-only.');
        }
        $d = $this->validate($in + $old, $id);
        unset($d['status']);
        if (array_key_exists('actual_start', $in) || array_key_exists('actual_finish', $in)) {
            $v = Validator::of($in)->date('actual_start', false)->date('actual_finish', false)->after('actual_finish', 'actual_start', 'Actual finish cannot be before actual start.')->validated();
            $d += $v;
        }
        $this->db->transaction(function () use ($u, $id, $old, $d) {
            $this->projects->update($id, $d);
            $this->audit->logChange($u->id, 'update', 'project', $id, $old, $d);
            $this->changes->touch($id);
        });
    }

    public function changeStatus(AuthUser $u, int $id, string $to, ?string $reason): void
    {
        $this->authz->requireProject($u, $id, true);
        $p = $this->projects->find($id) ?? throw HttpException::notFound();
        if (!in_array($to, self::TRANSITIONS[$p['status']] ?? [], true)) {
            throw HttpException::conflict("A project cannot move from {$p['status']} to {$to}.");
        }
        $fields = ['status' => $to];
        if ($to === 'active' && $p['actual_start'] === null) {
            $fields['actual_start'] = $this->clock->today();
        }
        if ($to === 'completed' && $p['actual_finish'] === null) {
            $fields['actual_finish'] = $this->clock->today();
        }
        $this->db->transaction(function () use ($u, $id, $p, $fields, $reason) {
            $this->projects->update($id, $fields);
            $this->audit->log($u->id, $fields['status'] === 'archived' ? 'archive' : 'status_change', 'project', (string) $id, ['status' => $p['status']], $fields + ['reason' => $reason]);
            $this->changes->touch($id);
        });
    }

    /** @return list<array<string, mixed>> */
    public function assignments(AuthUser $u, int $projectId): array
    {
        $this->authz->requireProject($u, $projectId, true);
        return $this->projects->assignments($projectId);
    }

    /** @return list<array<string, mixed>> active staff and clients who could join the project */
    public function assignable(AuthUser $u, int $projectId): array
    {
        $this->authz->requireProject($u, $projectId, true);
        return $this->projects->assignableUsers($projectId);
    }

    public function assign(AuthUser $u, int $projectId, int $userId): void
    {
        $this->authz->requireProject($u, $projectId, true);
        $target = $this->users->find($userId);
        if ($target === null || $target['status'] === 'deactivated') {
            throw HttpException::validation(['user_id' => 'Choose an active user.']);
        }
        if (in_array($target['role_code'], ['admin', 'director'], true)) {
            throw HttpException::validation(['user_id' => 'Administrators and Directors are not assigned to projects.']);
        }
        if ($this->projects->isAssigned($projectId, $userId)) {
            throw HttpException::conflict('This user is already assigned to the project.');
        }
        $this->projects->assign($projectId, $userId, $u->id);
        $this->audit->log($u->id, 'assign', 'project', (string) $projectId, null, ['user_id' => $userId]);
    }

    public function unassign(AuthUser $u, int $projectId, int $userId): void
    {
        $this->authz->requireProject($u, $projectId, true);
        if ($this->projects->unassign($projectId, $userId) === 0) {
            throw HttpException::notFound('Assignment not found.');
        }
        $this->audit->log($u->id, 'unassign', 'project', (string) $projectId, ['user_id' => $userId], null);
    }

    /** @param array<string, mixed> $in @return array<string, mixed> */
    private function validate(array $in, ?int $id = null): array
    {
        $v = Validator::of($in)
            ->string('code', 20)->string('name', 150, true, 3)->string('project_type', 60)->string('location', 150)->string('client_name', 150)
            ->money('contract_sum')->date('planned_start')->date('planned_finish')
            ->after('planned_finish', 'planned_start', 'Planned finish cannot be before planned start.')
            ->int('payment_period_days', 0, 365)->validated();
        if ($id !== null && $this->projects->codeExists($v['code'], $id)) {
            throw HttpException::validation(['code' => 'This project code is already used.']);
        }
        $v['contract_sum'] = Money::toString($v['contract_sum']);
        return $v;
    }
}
