<?php

declare(strict_types=1);

namespace Lubie\Alerts\Rules;

use Lubie\Alerts\AlertRule;
use Lubie\Alerts\Condition;
use Lubie\Alerts\Thresholds;
use Lubie\Domain\KpiResult;
use Lubie\Domain\ProjectSnapshot;

/** A6b: AC above budget by more than y % (default 10, Director's SMS rule). */
final class A06bCriticalOverrun implements AlertRule
{
    public function evaluate(ProjectSnapshot $s, KpiResult $k, Thresholds $t, string $today): array
    {
        $c = $k->budgetConsumedPct;
        $limit = 100 + $t->get('budget_overrun_sms_pct');
        if ($c === null || $c <= $limit) {
            return [];
        }
        return [new Condition('A6b', "A6b:project:{$s->id()}", 'critical', 'project', $s->id(),
            'Critical budget overrun', "{$s->code()} is over budget by " . round($c - 100, 1) . '% (limit ' . $t->get('budget_overrun_sms_pct') . '%).',
            ['director', 'pm', 'qs'], ['director', 'pm', 'qs'])];
    }
}
