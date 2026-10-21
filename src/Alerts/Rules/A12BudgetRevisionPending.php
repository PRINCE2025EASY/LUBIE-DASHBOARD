<?php

declare(strict_types=1);

namespace Lubie\Alerts\Rules;

use Lubie\Alerts\AlertRule;
use Lubie\Alerts\Condition;
use Lubie\Alerts\Thresholds;
use Lubie\Domain\KpiResult;
use Lubie\Domain\ProjectSnapshot;
use Lubie\Support\Money;

/** A12: budget revision waiting for Director approval. */
final class A12BudgetRevisionPending implements AlertRule
{
    public function evaluate(ProjectSnapshot $s, KpiResult $k, Thresholds $t, string $today): array
    {
        return array_map(static fn (array $b) => new Condition('A12', "A12:budget:{$b['id']}", 'high', 'budget', $b['id'],
            "Budget revision v{$b['version_no']} awaiting approval",
            "{$s->code()} revision to " . Money::format($b['amount']) . ' needs a Director decision.', ['director', 'pm', 'qs'], ['director']), $s->pendingBudgets);
    }
}
