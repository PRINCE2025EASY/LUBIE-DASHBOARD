<?php

declare(strict_types=1);

namespace Lubie\Alerts\Rules;

use Lubie\Alerts\AlertRule;
use Lubie\Alerts\Condition;
use Lubie\Alerts\Thresholds;
use Lubie\Domain\KpiResult;
use Lubie\Domain\ProjectSnapshot;
use Lubie\Support\Dates;

/** A2: today after planned start, no actual start and no approved progress. */
final class A02LateStart implements AlertRule
{
    public function evaluate(ProjectSnapshot $s, KpiResult $k, Thresholds $t, string $today): array
    {
        $out = [];
        foreach ($k->activities as $a) {
            if ($a['status'] === 'late_start') {
                $out[] = new Condition('A2', "A2:activity:{$a['id']}", 'medium', 'activity', $a['id'],
                    "Activity {$a['code']} has not started", "{$s->code()} {$a['code']} {$a['name']} was planned to start on " . Dates::uk($a['planned_start']) . ".",
                    ['pm', 'site']);
            }
        }
        return $out;
    }
}
