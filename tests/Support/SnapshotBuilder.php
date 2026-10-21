<?php

declare(strict_types=1);

namespace Lubie\Tests\Support;

use Lubie\Domain\ProjectSnapshot;

/** Fluent builder for in-memory project snapshots (unit tests only). Money in pesewas, % in hundredths. */
final class SnapshotBuilder
{
    private array $project = ['id' => 1, 'code' => 'T-1', 'name' => 'Test project', 'status' => 'active',
        'planned_start' => '2026-01-01', 'planned_finish' => '2026-12-31', 'payment_period_days' => 30];
    private array $acts = [];
    private array $hist = [];
    private array $exp = [];
    private ?int $budget = null;
    private array $ms = [];
    private array $certs = [];
    private array $res = [];
    private array $pending = [];
    private array $submitted = [];
    private array $critical = [];
    private ?string $lastApproved = null;

    public static function project(array $p = []): self
    {
        $b = new self();
        $b->project = $p + $b->project;
        return $b;
    }

    /** @param ?int $cost GH₵ whole units for readability; converted to pesewas */
    public function activity(int $id, string $start, string $finish, ?int $cost, ?float $pct = null, ?string $pctDate = null, array $extra = []): self
    {
        $this->acts[] = $extra + ['id' => $id, 'code' => 'A' . $id, 'name' => 'Activity ' . $id, 'planned_start' => $start, 'planned_finish' => $finish,
            'actual_start' => null, 'actual_finish' => null, 'cost' => $cost === null ? null : $cost * 100,
            'pct' => $pct === null ? null : (int) round($pct * 100), 'pct_date' => $pctDate, 'has_progress' => $pct !== null];
        if ($pct !== null && $pctDate !== null) {
            $this->hist[] = ['activity_id' => $id, 'record_date' => $pctDate, 'pct' => (int) round($pct * 100)];
            $this->lastApproved = max($this->lastApproved ?? '', $pctDate);
        }
        return $this;
    }

    public function history(int $activityId, string $date, float $pct): self
    {
        $this->hist[] = ['activity_id' => $activityId, 'record_date' => $date, 'pct' => (int) round($pct * 100)];
        usort($this->hist, static fn ($a, $b) => strcmp($a['record_date'], $b['record_date']));
        $this->lastApproved = max($this->lastApproved ?? '', $date);
        return $this;
    }

    public function spend(string $date, int $ghs, string $category = 'material'): self
    {
        $this->exp[] = ['date' => $date, 'amount' => $ghs * 100, 'category' => $category];
        return $this;
    }

    public function budget(?int $ghs): self
    {
        $this->budget = $ghs === null ? null : $ghs * 100;
        return $this;
    }

    public function milestone(int $id, string $due, ?string $achieved = null): self
    {
        $this->ms[] = ['id' => $id, 'name' => 'Milestone ' . $id, 'due_date' => $due, 'achieved_date' => $achieved, 'activity_id' => null];
        return $this;
    }

    public function certificate(int $id, string $submitted, int $valuation, ?int $certified, int $paid): self
    {
        $this->certs[] = ['id' => $id, 'cert_no' => 'IPC-' . $id, 'valuation' => $valuation * 100, 'certified' => $certified === null ? null : $certified * 100,
            'paid' => $paid * 100, 'submitted_date' => $submitted, 'certified_date' => null, 'status' => 'certified'];
        return $this;
    }

    public function resource(int $id, string $category, float $balance, ?float $reorder, ?string $equipmentStatus = null): self
    {
        $this->res[] = ['id' => $id, 'name' => 'Resource ' . $id, 'category' => $category, 'unit' => 'bag', 'balance' => $balance,
            'reorder_level' => $reorder, 'equipment_status' => $equipmentStatus];
        return $this;
    }

    public function pendingBudget(int $id, int $version, int $ghs): self
    {
        $this->pending[] = ['id' => $id, 'version_no' => $version, 'amount' => $ghs * 100];
        return $this;
    }

    public function submitted(int $id, int $activityId, string $date): self
    {
        $this->submitted[] = ['id' => $id, 'activity_id' => $activityId, 'record_date' => $date];
        return $this;
    }

    public function critical(int $id, int $activityId, string $date, string $reason = 'Work stoppage'): self
    {
        $this->critical[] = ['id' => $id, 'activity_id' => $activityId, 'reason' => $reason, 'record_date' => $date];
        return $this;
    }

    public function build(): ProjectSnapshot
    {
        return new ProjectSnapshot($this->project, $this->acts, $this->hist, $this->exp, $this->budget, $this->ms, $this->certs,
            $this->res, $this->pending, $this->submitted, $this->critical, $this->lastApproved);
    }
}
