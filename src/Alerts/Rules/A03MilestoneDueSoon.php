<?php

declare(strict_types=1);

namespace Lubie\Alerts\Rules;

use Lubie\Alerts\AlertRule;
use Lubie\Alerts\Condition;
use Lubie\Alerts\Thresholds;
use Lubie\Domain\KpiResult;
use Lubie\Domain\ProjectSnapshot;
use Lubie\Support\Dates;
use Lubie\Services\KpiService;

/** A3: milestone not achieved and due within n days (default 7). */
final class A03MilestoneDueSoon implements AlertRule
{
    public function evaluate(ProjectSnapshot $s, KpiResult $k, Thresholds $t, string $today): array
    {
        $out = [];
        foreach ($s->milestones as $m) {
            $left = KpiService::days($today, $m['due_date']);
            if ($m['achieved_date'] === null && $left >= 0 && $left <= $t->get('milestone_due_days')) {
                $out[] = new Condition('A3', "A3:milestone:{$m['id']}", 'information', 'milestone', $m['id'],
                    $left === 0 ? 'Milestone due today' : ($left === 1 ? 'Milestone due tomorrow' : "Milestone due in {$left} days"), "{$s->code()} milestone '{$m['name']}' is due on " . Dates::uk($m['due_date']) . ".", ['pm', 'site']);
            }
        }
        return $out;
    }
}
