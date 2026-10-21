<?php

declare(strict_types=1);

namespace Lubie\Services;

use Lubie\Audit\AuditLogger;
use Lubie\Db\Database;
use Lubie\Http\HttpException;
use Lubie\Repositories\ProgressRepository;
use Lubie\Repositories\ProjectRepository;
use Lubie\Repositories\ScheduleRepository;
use Lubie\Security\AuthUser;
use Lubie\Security\Authorizer;
use Lubie\Support\Clock;
use Lubie\Validation\Validator;

/**
 * Progress entry and PM approval (FR-09, FR-30, FR-32). Only approved records count in
 * KPIs. A client_uuid makes offline replays idempotent (FR-38).
 */
final class ProgressService
{
    public function __construct(
        private Database $db,
        private ProgressRepository $repo,
        private ScheduleRepository $schedule,
        private ProjectRepository $projects,
        private Authorizer $authz,
        private AuditLogger $audit,
        private ChangeTracker $changes,
        private AlertService $alerts,
        private Clock $clock,
    ) {
    }

    /**
     * @param array<string, mixed> $in
     * @return array{id:int, status:string, replayed:bool, critical?:bool}
     */
    public function submit(AuthUser $u, int $activityId, array $in): array
    {
        $a = $this->schedule->activity($activityId) ?? throw HttpException::notFound('Activity not found.');
        $pid = (int) $a['project_id'];
        $this->authz->requireProject($u, $pid);
        $d = Validator::of($in)->date('record_date', true, $this->clock->today())->percent('percent_complete')
            ->string('remarks', 500, false)->int('delay_reason_id', 1, 65535, false)->uuid('client_uuid')
            ->int('resubmission_of', 1, PHP_INT_MAX, false)->validated();
        if ($d['client_uuid'] !== null && ($existing = $this->repo->findByClientUuid($d['client_uuid'])) !== null) {
            $row = $this->repo->find($existing);
            return ['id' => $existing, 'status' => (string) $row['status'], 'replayed' => true];
        }
        if ($a['status'] !== 'active') {
            throw HttpException::conflict('This activity is archived.');
        }
        $p = $this->projects->find($pid);
        if (!in_array($p['status'], ['active', 'on_hold', 'planning'], true)) {
            throw HttpException::conflict('This project is closed for progress entries.');
        }
        $critical = false;
        if ($d['delay_reason_id'] !== null) {
            $reason = $this->repo->delayReason($d['delay_reason_id']) ?? throw HttpException::validation(['delay_reason_id' => 'Choose a reason from the list.']);
            $critical = (bool) $reason['raises_critical'];
        }
        if ($d['resubmission_of'] !== null) {
            $prev = $this->repo->find($d['resubmission_of']);
            if ($prev === null || (int) $prev['activity_id'] !== $activityId || $prev['status'] !== 'returned') {
                throw HttpException::validation(['resubmission_of' => 'Only a returned entry of this activity can be resubmitted.']);
            }
        }
        $id = $this->db->transaction(function () use ($u, $activityId, $pid, $d) {
            $id = $this->repo->insert($d + ['activity_id' => $activityId, 'submitted_by' => $u->id, 'submitted_at' => $this->clock->nowSql()]);
            $this->audit->log($u->id, 'create', 'progress', (string) $id, null, ['activity_id' => $activityId, 'pct' => $d['percent_complete'], 'date' => $d['record_date']]);
            $this->changes->touch($pid);
            return $id;
        });
        $this->alerts->evaluate($pid);
        return ['id' => $id, 'status' => 'submitted', 'replayed' => false, 'critical' => $critical];
    }

    /** @return list<array<string, mixed>> */
    public function history(AuthUser $u, int $activityId): array
    {
        $a = $this->schedule->activity($activityId) ?? throw HttpException::notFound();
        $this->authz->requireProject($u, (int) $a['project_id']);
        return $this->repo->forActivity($activityId);
    }

    /** @return list<array<string, mixed>> */
    public function queue(AuthUser $u, int $projectId): array
    {
        $this->authz->requireProject($u, $projectId);
        return $this->repo->queue($projectId);
    }

    /** @return list<array<string, mixed>> */
    public function mine(AuthUser $u, int $projectId): array
    {
        $this->authz->requireProject($u, $projectId);
        return $this->repo->mySubmissions($u->id, $projectId);
    }

    public function approve(AuthUser $u, int $id): void
    {
        $this->decide($u, $id, 'approved', null);
    }

    public function returnEntry(AuthUser $u, int $id, string $reason): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 300) {
            throw HttpException::validation(['return_reason' => 'Give a short reason (3 to 300 characters).']);
        }
        $this->decide($u, $id, 'returned', $reason);
    }

    private function decide(AuthUser $u, int $id, string $status, ?string $reason): void
    {
        $r = $this->repo->find($id) ?? throw HttpException::notFound();
        $pid = (int) $r['project_id'];
        $this->authz->requireProject($u, $pid);
        if ($r['status'] !== 'submitted') {
            throw HttpException::conflict('This entry has already been ' . $r['status'] . '.');
        }
        $this->db->transaction(function () use ($u, $id, $status, $reason, $r, $pid) {
            if ($this->repo->decide($id, $status, $u->id, $this->clock->nowSql(), $reason) !== 1) {
                throw HttpException::conflict('This entry was decided by someone else.');
            }
            if ($status === 'approved') {
                $this->updateActualDates((int) $r['activity_id'], (string) $r['record_date'], (float) $r['percent_complete'], $u->id);
            }
            $this->audit->log($u->id, $status === 'approved' ? 'approve' : 'return', 'progress', (string) $id, ['status' => 'submitted'], ['status' => $status, 'reason' => $reason]);
            $this->changes->touch($pid);
        });
        $this->alerts->evaluate($pid);
    }

    /** Actual start = first approved entry above 0 %; actual finish = approved 100 % entry. */
    private function updateActualDates(int $activityId, string $date, float $pct, int $userId): void
    {
        $a = $this->schedule->activity($activityId);
        $f = [];
        if ($pct > 0 && $a['actual_start'] === null) {
            $f['actual_start'] = $date;
        }
        if ($pct >= 100 && $a['actual_finish'] === null) {
            $f['actual_finish'] = max($date, (string) ($f['actual_start'] ?? $a['actual_start'] ?? $date));
        }
        if ($f) {
            $this->schedule->updateActivity($activityId, $f, $userId);
        }
    }
}
