<?php

declare(strict_types=1);

namespace Lubie\Alerts\Rules;

use Lubie\Alerts\AlertRule;
use Lubie\Alerts\Condition;
use Lubie\Alerts\Thresholds;
use Lubie\Domain\KpiResult;
use Lubie\Domain\ProjectSnapshot;

/** A10: material balance below its reorder level. */
final class A10MaterialLow implements AlertRule
{
    public function evaluate(ProjectSnapshot $s, KpiResult $k, Thresholds $t, string $today): array
    {
        $out = [];
        foreach ($s->resources as $r) {
            if ($r['category'] === 'material' && $r['reorder_level'] !== null && $r['balance'] < $r['reorder_level']) {
                $out[] = new Condition('A10', "A10:resource:{$r['id']}", 'information', 'resource', $r['id'],
                    "{$r['name']} below reorder level", "Balance {$r['balance']} {$r['unit']} is below the reorder level of {$r['reorder_level']}.", ['site', 'pm']);
            }
        }
        return $out;
    }
}
