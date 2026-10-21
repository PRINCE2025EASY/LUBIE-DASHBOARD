<?php

declare(strict_types=1);

namespace Lubie\Alerts\Rules;

use Lubie\Alerts\AlertRule;
use Lubie\Alerts\Condition;
use Lubie\Alerts\Thresholds;
use Lubie\Domain\KpiResult;
use Lubie\Domain\ProjectSnapshot;

/** A7: CPI below c (default 0.90) when EV > 0. */
final class A07LowCpi implements AlertRule
{
    public function evaluate(ProjectSnapshot $s, KpiResult $k, Thresholds $t, string $today): array
    {
        if ($k->cpi === null || ($k->ev ?? 0) <= 0 || $k->cpi >= $t->get('cpi_min')) {
            return [];
        }
        return [new Condition('A7', "A7:project:{$s->id()}", 'warning', 'project', $s->id(),
            "Cost performance low (CPI {$k->cpi})", "{$s->code()} earns GH₵ {$k->cpi} of work per GH₵ 1 spent (limit " . $t->get('cpi_min') . ').', ['pm', 'qs'])];
    }
}
