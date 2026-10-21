<?php

declare(strict_types=1);

namespace Lubie\Services;

use Lubie\Audit\AuditLogger;
use Lubie\Db\Database;
use Lubie\Http\HttpException;
use Lubie\Repositories\ProjectRepository;
use Lubie\Repositories\ProjectSnapshotRepository;
use Lubie\Repositories\ScheduleRepository;
use Lubie\Security\AuthUser;
use Lubie\Security\Authorizer;
use Lubie\Support\Clock;
use Lubie\Support\Money;
use Lubie\Validation\Validator;

/**
 * Activities and milestones (FR-08, FR-13). PM and site engineers edit activities on
 * assigned projects; only the PM sets budgeted cost and milestones (decision 21/10/2026).
 */
final class ScheduleService
{
    public function __construct(
        private Database $db,
        private ScheduleRepository $repo,
        private ProjectRepository $projects,
        private ProjectSnapshotRepository $snapshots,
        private KpiService $kpi,
        private Authorizer $authz,
        private AuditLogger $audit,
        private ChangeTracker $changes,
        private AlertService $alerts,
        private Clock $clock,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function activities(AuthUser $u, int $projectId): array
    {
        $this->authz->requireProject($u, $projectId);
        $k = $this->kpi->compute($this->snapshots->load($projectId), $this->clock->today());
        $byId = array_column($k->activities, null, 'id');
        $deps = $this->repo->dependencies($projectId);
        return array_map(function (array $a) use ($u, $byId, $deps) {
            $dto = Presenter::activity($a, $u->role, $byId[(int) $a['id']] ?? null);
            $dto['predecessor_ids'] = $deps[(int) $a['id']] ?? [];
            return $dto;
        }, $this->repo->activities($projectId));
    }

    /** @param array<string, mixed> $in */
    public function createActivity(AuthUser $u, int $projectId, array $in): int
    {
        $this->authz->requireProject($u, $projectId);
        $this->assertEditable($projectId);
        $d = $this->validateActivity($u, $in);
        if ($this->repo->activityCodeExists($projectId, $d['code'])) {
            throw HttpException::validation(['code' => 'This activity code is already used in the project.']);
        }
        $id = $this->db->transaction(function () use ($u, $projectId, $d) {
            $id = $this->repo->createActivity($projectId, $d, $u->id);
            $this->audit->log($u->id, 'create', 'activity', (string) $id, null, $d);
            $this->changes->touch($projectId);
            return $id;
        });
        $this->alerts->evaluate($projectId);
        return $id;
    }

    /** @param array<string, mixed> $in */
    public function updateActivity(AuthUser $u, int $id, array $in): void
    {
        $old = $this->repo->activity($id) ?? throw HttpException::notFound();
        $pid = (int) $old['project_id'];
        $this->authz->requireProject($u, $pid);
        $this->assertEditable($pid);
        if ($old['status'] === 'archived') {
            throw HttpException::conflict('Archived activities are read-only.');
        }
        $merged = $in + ['code' => $old['code'], 'name' => $old['name'], 'planned_start' => $old['planned_start'], 'planned_finish' => $old['planned_finish'],
            'budgeted_cost' => $old['budgeted_cost']];
        $d = $this->validateActivity($u, $merged, $old);
        if ($this->repo->activityCodeExists($pid, $d['code'], $id)) {
            throw HttpException::validation(['code' => 'This activity code is already used in the project.']);
        }
        $extra = Validator::of($in)->date('actual_start', false)->date('actual_finish', false)
            ->after('actual_finish', 'actual_start', 'Actual finish cannot be before actual start.')->validated();
        foreach (['actual_start', 'actual_finish'] as $f) {
            if (array_key_exists($f, $in)) {
                $d[$f] = $extra[$f];
            }
        }
        $this->db->transaction(function () use ($u, $id, $pid, $old, $d) {
            $this->repo->updateActivity($id, $d, $u->id);
            $this->audit->logChange($u->id, 'update', 'activity', $id, $old, $d);
            $this->changes->touch($pid);
        });
        $this->alerts->evaluate($pid);
    }

    public function archiveActivity(AuthUser $u, int $id, string $reason): void
    {
        $a = $this->repo->activity($id) ?? throw HttpException::notFound();
        $pid = (int) $a['project_id'];
        $this->authz->requireProject($u, $pid);
        $this->db->transaction(function () use ($u, $id, $pid, $reason) {
            $this->repo->updateActivity($id, ['status' => 'archived'], $u->id);
            $this->audit->log($u->id, 'archive', 'activity', (string) $id, null, ['reason' => $reason]);
            $this->changes->touch($pid);
        });
        $this->alerts->evaluate($pid);
    }

    /** @param list<mixed> $predIds */
    public function setDependencies(AuthUser $u, int $id, array $predIds): void
    {
        $a = $this->repo->activity($id) ?? throw HttpException::notFound();
        $pid = (int) $a['project_id'];
        $this->authz->requireProject($u, $pid);
        $valid = array_column($this->repo->activities($pid), 'id');
        $preds = [];
        foreach ($predIds as $p) {
            $p = (int) $p;
            if ($p === $id || !in_array($p, array_map('intval', $valid), true)) {
                throw HttpException::validation(['predecessor_ids' => 'Choose other activities of the same project.']);
            }
            $preds[] = $p;
        }
        $graph = $this->repo->dependencies($pid);
        $graph[$id] = array_values(array_unique($preds));
        if (self::hasCycle($graph)) {
            throw HttpException::validation(['predecessor_ids' => 'These links would create a loop.']);
        }
        $this->db->transaction(function () use ($u, $id, $pid, $graph) {
            $this->repo->setDependencies($id, $graph[$id]);
            $this->audit->log($u->id, 'update', 'activity_dependencies', (string) $id, null, ['predecessors' => $graph[$id]]);
            $this->changes->touch($pid);
        });
    }

    /** @return list<array<string, mixed>> */
    public function milestones(AuthUser $u, int $projectId): array
    {
        $this->authz->requireProject($u, $projectId);
        $today = $this->clock->today();
        return array_map(static fn (array $m) => Presenter::milestone($m, $today) + ['activity_code' => $m['activity_code']], $this->repo->milestones($projectId));
    }

    /** @param array<string, mixed> $in */
    public function createMilestone(AuthUser $u, int $projectId, array $in): int
    {
        $this->authz->requireProject($u, $projectId);
        $this->assertEditable($projectId);
        $d = $this->validateMilestone($projectId, $in);
        $id = $this->db->transaction(function () use ($u, $projectId, $d) {
            $id = $this->repo->createMilestone($projectId, $d, $u->id);
            $this->audit->log($u->id, 'create', 'milestone', (string) $id, null, $d);
            $this->changes->touch($projectId);
            return $id;
        });
        $this->alerts->evaluate($projectId);
        return $id;
    }

    /** @param array<string, mixed> $in */
    public function updateMilestone(AuthUser $u, int $id, array $in): void
    {
        $old = $this->repo->milestone($id) ?? throw HttpException::notFound();
        $pid = (int) $old['project_id'];
        $this->authz->requireProject($u, $pid);
        $d = $this->validateMilestone($pid, $in + $old);
        $this->db->transaction(function () use ($u, $id, $pid, $old, $d) {
            $this->repo->updateMilestone($id, $d);
            $this->audit->logChange($u->id, 'update', 'milestone', $id, $old, $d);
            $this->changes->touch($pid);
        });
        $this->alerts->evaluate($pid);
    }

    /** @param array<string, mixed> $in @return array<string, mixed> */
    private function validateActivity(AuthUser $u, array $in, ?array $old = null): array
    {
        $v = Validator::of($in)->string('code', 20)->string('name', 150, true, 2)->date('planned_start')->date('planned_finish')
            ->after('planned_finish', 'planned_start', 'Planned finish cannot be before planned start.');
        if ($u->is('pm')) {
            $v->money('budgeted_cost', false);
        }
        $d = $v->validated();
        if ($u->is('pm')) {
            $d['budgeted_cost'] = Money::toString($d['budgeted_cost']);
        } else {
            unset($d['budgeted_cost']); // site engineers cannot set or see cost (hard rule)
            if (array_key_exists('budgeted_cost', $in) && $in['budgeted_cost'] !== null && $in['budgeted_cost'] !== '' && ($old === null || (string) $in['budgeted_cost'] !== (string) $old['budgeted_cost'])) {
                $this->audit->log($u->id, 'ignored_field', 'activity', $old ? (string) $old['id'] : null, null, ['field' => 'budgeted_cost']);
            }
        }
        return $d;
    }

    /** @param array<string, mixed> $in @return array<string, mixed> */
    private function validateMilestone(int $projectId, array $in): array
    {
        $d = Validator::of($in)->string('name', 150, true, 2)->date('due_date')->date('achieved_date', false, $this->clock->today())
            ->int('activity_id', 1, PHP_INT_MAX, false)->validated();
        if ($d['activity_id'] !== null) {
            $a = $this->repo->activity($d['activity_id']);
            if ($a === null || (int) $a['project_id'] !== $projectId) {
                throw HttpException::validation(['activity_id' => 'Choose an activity of this project.']);
            }
        }
        return $d;
    }

    private function assertEditable(int $projectId): void
    {
        $p = $this->projects->find($projectId);
        if ($p === null || in_array($p['status'], ['archived', 'completed'], true)) {
            throw HttpException::conflict('This project is closed for changes.');
        }
    }

    /** @param array<int, list<int>> $g */
    public static function hasCycle(array $g): bool
    {
        $state = [];
        $visit = function (int $n) use (&$visit, &$state, $g): bool {
            $state[$n] = 1;
            foreach ($g[$n] ?? [] as $m) {
                if (($state[$m] ?? 0) === 1 || (($state[$m] ?? 0) === 0 && $visit($m))) {
                    return true;
                }
            }
            $state[$n] = 2;
            return false;
        };
        foreach (array_keys($g) as $n) {
            if (($state[$n] ?? 0) === 0 && $visit($n)) {
                return true;
            }
        }
        return false;
    }
}
