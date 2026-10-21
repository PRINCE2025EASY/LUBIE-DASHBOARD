<?php

declare(strict_types=1);

namespace Lubie\Alerts\Rules;

use Lubie\Alerts\AlertRule;
use Lubie\Alerts\Condition;
use Lubie\Alerts\Thresholds;
use Lubie\Domain\KpiResult;
use Lubie\Domain\ProjectSnapshot;

/** A14: equipment reported faulty. */
final class A14EquipmentFaulty implements AlertRule
{
    public function evaluate(ProjectSnapshot $s, KpiResult $k, Thresholds $t, string $today): array
    {
        $out = [];
        foreach ($s->resources as $r) {
            if ($r['category'] === 'equipment' && $r['equipment_status'] === 'faulty') {
                $out[] = new Condition('A14', "A14:resource:{$r['id']}", 'information', 'resource', $r['id'],
                    "{$r['name']} reported faulty", "{$s->code()}: {$r['name']} is marked faulty.", ['pm', 'site']);
            }
        }
        return $out;
    }
}
