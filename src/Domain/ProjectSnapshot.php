<?php

declare(strict_types=1);

namespace Lubie\Domain;

/**
 * Everything the KPI and alert services need about one project at one date,
 * loaded once (ProjectSnapshotRepository) and shared by dashboards, reports and alerts.
 * Money is integer pesewas; percentages are integer hundredths (85.50 % = 8550).
 */
final class ProjectSnapshot
{
    /**
     * @param array<string, mixed> $project
     * @param list<array{id:int, code:string, name:string, planned_start:string, planned_finish:string, actual_start:?string, actual_finish:?string, cost:?int, pct:?int, pct_date:?string, has_progress:bool}> $activities
     * @param list<array{activity_id:int, record_date:string, pct:int}> $approvedHistory approved records, oldest first
     * @param list<array{date:string, amount:int, category:string}> $expenditures active expenditure
     * @param list<array{id:int, name:string, due_date:string, achieved_date:?string, activity_id:?int}> $milestones
     * @param list<array{id:int, cert_no:string, valuation:int, certified:?int, paid:int, submitted_date:string, certified_date:?string, status:string}> $certificates
     * @param list<array{id:int, name:string, category:string, unit:string, balance:float, reorder_level:?float, equipment_status:?string}> $resources
     * @param list<array{id:int, version_no:int, amount:int}> $pendingBudgets
     * @param list<array{id:int, activity_id:int, record_date:string}> $submittedProgress
     * @param list<array{id:int, activity_id:int, reason:string, record_date:string}> $criticalReports
     */
    public function __construct(
        public readonly array $project,
        public readonly array $activities,
        public readonly array $approvedHistory,
        public readonly array $expenditures,
        public readonly ?int $currentBudget,
        public readonly array $milestones,
        public readonly array $certificates,
        public readonly array $resources,
        public readonly array $pendingBudgets,
        public readonly array $submittedProgress,
        public readonly array $criticalReports,
        public readonly ?string $lastApprovedProgressDate,
    ) {
    }

    public function id(): int
    {
        return (int) $this->project['id'];
    }

    public function code(): string
    {
        return (string) $this->project['code'];
    }

    public function paymentPeriodDays(): int
    {
        return (int) $this->project['payment_period_days'];
    }
}
