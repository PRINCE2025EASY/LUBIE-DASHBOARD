<?php

declare(strict_types=1);

namespace Lubie\Alerts\Rules;

use Lubie\Alerts\AlertRule;
use Lubie\Alerts\Condition;
use Lubie\Alerts\Thresholds;
use Lubie\Domain\KpiResult;
use Lubie\Domain\ProjectSnapshot;

/** A6: AC above 100 % of the current budget (definition; in-app only). */
final class A06BudgetExceeded implements AlertRule
{
    public function evaluate(ProjectSnapshot $s, KpiResult $k, Thresholds $t, string $today): array
    {
        $c = $k->budgetConsumedPct;
        if ($c === null || $c <= 100) {
            return [];
        }
        return [new Condition('A6', "A6:project:{$s->id()}", 'critical', 'project', $s->id(),
            'Budget exceeded', "{$s->code()} expenditure is {$c}% of the current approved budget.", ['pm', 'qs', 'director'])];
    }
}
