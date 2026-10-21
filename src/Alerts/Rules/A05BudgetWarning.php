<?php

declare(strict_types=1);

namespace Lubie\Alerts\Rules;

use Lubie\Alerts\AlertRule;
use Lubie\Alerts\Condition;
use Lubie\Alerts\Thresholds;
use Lubie\Domain\KpiResult;
use Lubie\Domain\ProjectSnapshot;

/** A5: AC at or above x % of the current budget (default 80). Escalates to A6 above 100 %. */
final class A05BudgetWarning implements AlertRule
{
    public function evaluate(ProjectSnapshot $s, KpiResult $k, Thresholds $t, string $today): array
    {
        $c = $k->budgetConsumedPct;
        if ($c === null || $c < $t->get('budget_warning_pct') || $c > 100) {
            return [];
        }
        return [new Condition('A5', "A5:project:{$s->id()}", 'warning', 'project', $s->id(),
            "Budget {$c}% used", "{$s->code()} has spent {$c}% of the current approved budget.", ['pm', 'qs'])];
    }
}
