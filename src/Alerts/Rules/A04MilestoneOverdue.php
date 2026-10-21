<?php

declare(strict_types=1);

namespace Lubie\Alerts\Rules;

use Lubie\Alerts\AlertRule;
use Lubie\Alerts\Condition;
use Lubie\Alerts\Thresholds;
use Lubie\Domain\KpiResult;
use Lubie\Domain\ProjectSnapshot;
use Lubie\Support\Dates;

/** A4: milestone not achieved after its due date. Client gets an in-app notice (no SMS). */
final class A04MilestoneOverdue implements AlertRule
{
    public function evaluate(ProjectSnapshot $s, KpiResult $k, Thresholds $t, string $today): array
    {
        $out = [];
        foreach ($s->milestones as $m) {
            if ($m['achieved_date'] === null && $today > $m['due_date']) {
                $out[] = new Condition('A4', "A4:milestone:{$m['id']}", 'high', 'milestone', $m['id'],
                    'Milestone overdue', "Milestone '{$m['name']}' was due on " . Dates::uk($m['due_date']) . " and is not yet achieved.",
                    ['pm', 'site', 'client'], ['pm'], true);
            }
        }
        return $out;
    }
}
