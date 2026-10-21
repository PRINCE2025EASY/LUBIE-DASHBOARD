<?php

declare(strict_types=1);

namespace Lubie\Services;

use Lubie\Audit\AuditLogger;
use Lubie\Db\Database;
use Lubie\Http\HttpException;
use Lubie\Repositories\CostRepository;
use Lubie\Repositories\ProjectRepository;
use Lubie\Repositories\ScheduleRepository;
use Lubie\Security\AuthUser;
use Lubie\Security\Authorizer;
use Lubie\Support\Clock;
use Lubie\Support\Money;
use Lubie\Validation\Validator;

/**
 * Budgets with Director-approved revisions (FR-14, FR-31), expenditure (FR-15) and
 * valuations/payments (FR-28). Baselines are never overwritten; corrections are voids.
 */
final class CostService
{
    public function __construct(
        private Database $db,
        private CostRepository $repo,
        private ProjectRepository $projects,
        private ScheduleRepository $schedule,
        private Authorizer $authz,
        private AuditLogger $audit,
        private ChangeTracker $changes,
        private AlertService $alerts,
        private Clock $clock,
    ) {
    }

    // ---------------- Budgets ----------------

    /** @return array{current:?array, versions:list<array<string, mixed>>} */
    public function budgets(AuthUser $u, int $projectId): array
    {
        $this->authz->requireProject($u, $projectId);
        $versions = $this->repo->budgets($projectId);
        $current = null;
        foreach ($versions as $v) {
            if ((int) ($v['is_current'] ?? 0) === 1) {
                $current = $v;
            }
        }
        return ['current' => $current, 'versions' => $versions];
    }

    /** @param array<string, mixed> $in */
    public function requestBudget(AuthUser $u, int $projectId, array $in): int
    {
        $this->authz->requireProject($u, $projectId);
        $d = Validator::of($in)->money('amount', true, true)->string('reason', 300, true, 3)->validated();
        $submit = filter_var($in['submit'] ?? true, FILTER_VALIDATE_BOOL);
        $id = $this->db->transaction(function () use ($u, $projectId, $d, $submit) {
            $ver = $this->repo->nextVersion($projectId);
            $id = $this->repo->insertBudget($projectId, $ver, (string) Money::toString($d['amount']), $d['reason'], $submit ? 'pending' : 'draft', $u->id, $this->clock->nowSql());
            $this->audit->log($u->id, 'create', 'budget', (string) $id, null, ['version' => $ver, 'amount' => Money::toString($d['amount']), 'status' => $submit ? 'pending' : 'draft']);
            $this->changes->touch($projectId);
            return $id;
        });
        $this->alerts->evaluate($projectId);
        return $id;
    }

    public function submitBudget(AuthUser $u, int $id): void
    {
        $b = $this->repo->budget($id) ?? throw HttpException::notFound();
        $this->authz->requireProject($u, (int) $b['project_id']);
        if ((int) $b['requested_by'] !== $u->id) {
            throw HttpException::forbidden('Only the person who prepared this draft can submit it.');
        }
        if ($this->repo->setBudgetStatus($id, 'draft', 'pending') !== 1) {
            throw HttpException::conflict('Only drafts can be submitted.');
        }
        $this->audit->log($u->id, 'submit', 'budget', (string) $id);
        $this->changes->touch((int) $b['project_id']);
        $this->alerts->evaluate((int) $b['project_id']);
    }

    public function decideBudget(AuthUser $u, int $id, bool $approve, ?string $note): void
    {
        $b = $this->repo->budget($id) ?? throw HttpException::notFound();
        $pid = (int) $b['project_id'];
        $this->authz->requireProject($u, $pid);
        if ($b['status'] !== 'pending') {
            throw HttpException::conflict('This revision is not waiting for a decision.');
        }
        if ((int) $b['requested_by'] === $u->id) {
            throw HttpException::conflict('You cannot decide on a revision you requested (separation of duties).');
        }
        $note = $note !== null ? mb_substr(trim($note), 0, 300) : null;
        if (!$approve && ($note === null || mb_strlen($note) < 3)) {
            throw HttpException::validation(['note' => 'Give a reason for rejecting the revision.']);
        }
        $this->db->transaction(function () use ($u, $id, $pid, $approve, $note, $b) {
            if ($approve) {
                $this->repo->approveBudget($id, $pid, $u->id, $this->clock->nowSql(), $note);
            } elseif ($this->repo->rejectBudget($id, $u->id, $this->clock->nowSql(), $note) !== 1) {
                throw HttpException::conflict('This revision was decided by someone else.');
            }
            $this->audit->log($u->id, $approve ? 'approve' : 'reject', 'budget', (string) $id, ['status' => 'pending'], ['status' => $approve ? 'approved' : 'rejected', 'amount' => $b['amount'], 'note' => $note]);
            $this->changes->touch($pid);
        });
        $this->alerts->evaluate($pid);
    }

    /** @return list<array<string, mixed>> */
    public function pendingForDirector(): array
    {
        return $this->repo->pendingBudgetsAll();
    }

    // ---------------- Expenditure ----------------

    /** @return array{data:list<array<string, mixed>>, meta:array<string, int>} */
    public function expenditures(AuthUser $u, int $projectId, int $page, int $perPage): array
    {
        $this->authz->requireProject($u, $projectId);
        $perPage = max(1, min(100, $perPage));
        $page = max(1, $page);
        return ['data' => $this->repo->expenditures($projectId, $perPage, ($page - 1) * $perPage),
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $this->repo->countExpenditures($projectId)]];
    }

    /** @param array<string, mixed> $in */
    public function recordExpenditure(AuthUser $u, int $projectId, array $in): int
    {
        $this->authz->requireProject($u, $projectId);
        $this->assertOpen($projectId);
        $d = Validator::of($in)->date('expense_date', true, $this->clock->today())->money('amount', true, true)
            ->int('category_id', 1, 65535)->string('description', 300, true, 3)->string('reference_no', 50, false)
            ->string('supplier_name', 150, false)->int('activity_id', 1, PHP_INT_MAX, false)->validated();
        if (!filter_var($in['confirmed'] ?? false, FILTER_VALIDATE_BOOL)) {
            // single-source field (document review): require an explicit confirmation step
            throw HttpException::validation(['confirmed' => 'Check the amount and date, then confirm.']);
        }
        if (!in_array($d['category_id'], array_map('intval', array_column($this->repo->categories(), 'id')), true)) {
            throw HttpException::validation(['category_id' => 'Choose a category from the list.']);
        }
        if ($d['activity_id'] !== null) {
            $a = $this->schedule->activity($d['activity_id']);
            if ($a === null || (int) $a['project_id'] !== $projectId) {
                throw HttpException::validation(['activity_id' => 'Choose an activity of this project.']);
            }
        }
        if ($d['reference_no'] !== null && $this->repo->referenceExists($projectId, $d['reference_no'])) {
            throw HttpException::conflict('This voucher or receipt number is already recorded for the project.');
        }
        $d['amount'] = Money::toString($d['amount']);
        $id = $this->db->transaction(function () use ($u, $projectId, $d) {
            $id = $this->repo->insertExpenditure($projectId, $d, $u->id);
            $this->audit->log($u->id, 'create', 'expenditure', (string) $id, null, $d);
            $this->changes->touch($projectId);
            return $id;
        });
        $this->alerts->evaluate($projectId);
        return $id;
    }

    public function voidExpenditure(AuthUser $u, int $id, string $reason): void
    {
        $e = $this->repo->expenditure($id) ?? throw HttpException::notFound();
        $pid = (int) $e['project_id'];
        $this->authz->requireProject($u, $pid);
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) {
            throw HttpException::validation(['void_reason' => 'Give the reason for voiding this entry.']);
        }
        $this->db->transaction(function () use ($u, $id, $pid, $reason, $e) {
            if ($this->repo->voidExpenditure($id, mb_substr($reason, 0, 300), $u->id) !== 1) {
                throw HttpException::conflict('This entry is already void.');
            }
            $this->audit->log($u->id, 'void', 'expenditure', (string) $id, ['status' => 'active', 'amount' => $e['amount']], ['status' => 'voided', 'reason' => $reason]);
            $this->changes->touch($pid);
        });
        $this->alerts->evaluate($pid);
    }

    /** @return list<array<string, mixed>> */
    public function categories(): array
    {
        return $this->repo->categories();
    }

    // ---------------- Valuations and payments ----------------

    /** @return list<array<string, mixed>> */
    public function certificates(AuthUser $u, int $projectId): array
    {
        $this->authz->requireProject($u, $projectId);
        $p = $this->projects->find($projectId);
        $today = $this->clock->today();
        return array_map(fn (array $c) => Presenter::certificate($c, $today, (int) $p['payment_period_days']) + ['payments' => $this->repo->payments((int) $c['id'])],
            $this->repo->certificates($projectId));
    }

    /** @param array<string, mixed> $in */
    public function createCertificate(AuthUser $u, int $projectId, array $in): int
    {
        $this->authz->requireProject($u, $projectId);
        $d = Validator::of($in)->string('cert_no', 20)->money('valuation_amount', true, true)->date('submitted_date', true, $this->clock->today())
            ->string('remarks', 300, false)->validated();
        if ($this->repo->certNoExists($projectId, $d['cert_no'])) {
            throw HttpException::conflict('This certificate number already exists for the project.');
        }
        $id = $this->db->transaction(function () use ($u, $projectId, $d) {
            $id = $this->repo->insertCertificate($projectId, $d['cert_no'], (string) Money::toString($d['valuation_amount']), $d['submitted_date'], $d['remarks'], $u->id);
            $this->audit->log($u->id, 'create', 'certificate', (string) $id, null, ['cert_no' => $d['cert_no'], 'valuation' => Money::toString($d['valuation_amount'])]);
            $this->changes->touch($projectId);
            return $id;
        });
        $this->alerts->evaluate($projectId);
        return $id;
    }

    /** @param array<string, mixed> $in */
    public function certify(AuthUser $u, int $id, array $in): void
    {
        $c = $this->repo->certificate($id) ?? throw HttpException::notFound();
        $pid = (int) $c['project_id'];
        $this->authz->requireProject($u, $pid);
        $d = Validator::of($in)->money('amount_certified', true, true)->date('certified_date', true, $this->clock->today())->validated();
        if ($d['certified_date'] < (string) $c['submitted_date']) {
            throw HttpException::validation(['certified_date' => 'Certification cannot be before the submission date.']);
        }
        $this->db->transaction(function () use ($u, $id, $pid, $d) {
            if ($this->repo->certify($id, (string) Money::toString($d['amount_certified']), $d['certified_date']) !== 1) {
                throw HttpException::conflict('This certificate is already certified.');
            }
            $this->audit->log($u->id, 'certify', 'certificate', (string) $id, ['status' => 'submitted'], ['status' => 'certified', 'amount' => Money::toString($d['amount_certified'])]);
            $this->changes->touch($pid);
        });
        $this->alerts->evaluate($pid);
    }

    /** @param array<string, mixed> $in */
    public function recordPayment(AuthUser $u, int $id, array $in): int
    {
        $c = $this->repo->certificate($id) ?? throw HttpException::notFound();
        $pid = (int) $c['project_id'];
        $this->authz->requireProject($u, $pid);
        if ($c['amount_certified'] === null) {
            throw HttpException::conflict('Certify the valuation before recording payments.');
        }
        $d = Validator::of($in)->date('paid_date', true, $this->clock->today())->money('amount', true, true)->string('reference_no', 50)->validated();
        $outstanding = (int) Money::toPesewas((string) $c['amount_certified']) - (int) Money::toPesewas((string) $c['paid']);
        if ($d['amount'] > $outstanding) {
            throw HttpException::validation(['amount' => 'The payment is more than the outstanding balance of ' . Money::format($outstanding) . '.']);
        }
        if ($this->repo->paymentRefExists($id, $d['reference_no'])) {
            throw HttpException::conflict('This payment reference is already recorded.');
        }
        $tid = $this->db->transaction(function () use ($u, $id, $pid, $d, $outstanding) {
            $tid = $this->repo->insertPayment($id, $d['paid_date'], (string) Money::toString($d['amount']), $d['reference_no'], $u->id);
            $status = $d['amount'] === $outstanding ? 'paid' : 'part_paid';
            $this->repo->setCertificateStatus($id, $status);
            $this->audit->log($u->id, 'create', 'payment', (string) $tid, null, ['certificate_id' => $id, 'amount' => Money::toString($d['amount']), 'status' => $status]);
            $this->changes->touch($pid);
            return $tid;
        });
        $this->alerts->evaluate($pid);
        return $tid;
    }

    private function assertOpen(int $projectId): void
    {
        $p = $this->projects->find($projectId);
        if ($p === null || $p['status'] === 'archived') {
            throw HttpException::conflict('This project is archived.');
        }
    }
}
